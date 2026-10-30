<?php

namespace App\Services;

use App\Data\WrittenFiles;
use App\Enums\DocumentType;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\User;
use App\Policies\InquiryFileUploads;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Staff move an inquiry's sales, payment or logistics status by hand, from the
 * Inquiry list's Update Status dialog. Whether the user may move the track at
 * all is the caller's (InquiryPolicy::updateStatus); this checks the move
 * itself, under the inquiry's row lock, and writes it:
 *
 * 1. the target is allowed from the current status (InquiryStatusRules);
 * 2. the note is there, at most 500 characters;
 * 3. every file the target needs is there, one where one is asked for;
 * 4. the amount: a quoted total above 0, or an amount received that keeps a
 *    partial payment under the quoted total and brings a full payment to it;
 * 5. the files are stored through the uploader rule (InquiryFileUploads), each
 *    pointing at the update's history row, so they read as one group;
 * 6. the move goes through InquiryStatusService::transition(), with the note,
 *    and so posts the card.
 *
 * Quoting is a new quotation revision with its total. The job order may come
 * with it or later (attachJobOrder()); either way it starts logistics at
 * order_received. A payment is a `payment_transactions` row.
 *
 * Has to run inside a transaction: the caller removes the written files if it
 * fails (`$written`).
 */
class InquiryStatusUpdateService
{
    public function __construct(
        public readonly InquiryRepositoryInterface $inquiryRepository,
        public readonly InquiryStatusService $inquiryStatusService,
        public readonly InquiryFileUploads $fileUploads,
    ) {}

    /**
     * @param  array<string, list<UploadedFile>>  $files  By document type.
     *
     * @throws ModelNotFoundException When there is no such inquiry.
     * @throws ValidationException When the move or what it carries is refused.
     */
    public function update(
        User $by,
        string $reference,
        InquiryStatusTrack $track,
        string $to,
        string $note,
        array $files,
        ?string $amount,
        WrittenFiles $written,
    ): Inquiry {
        $inquiry = $this->inquiryRepository->findByReferenceForUpdate($reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        $this->assertMove($inquiry, $track, $to);
        $this->assertNote($note);
        $this->assertFiles($track, $to, $files);
        $amount = $this->assertAmount($inquiry, $track, $to, $amount);

        $target = $track->status($to);

        if ($track === InquiryStatusTrack::Sales && InquiryStatusRules::isQuote($to)) {
            $this->inquiryRepository->recordQuote($inquiry, ($inquiry->quote_revision ?? 0) + 1, (string) $amount);
        }

        if ($target === PaymentStatus::Awaiting) {
            $this->inquiryRepository->recordContractTotal($inquiry, (string) $amount);
        }

        if ($target === PaymentStatus::Partial || $target === PaymentStatus::FullyPaid) {
            $this->inquiryRepository->addPayment($inquiry, (string) $amount, $by->id, Date::now());
        }

        $history = $this->inquiryStatusService->transition($inquiry, $track, $target, $by, trim($note));

        foreach (InquiryStatusRules::requiredFiles($track, $to) as $group) {
            foreach ($group['slots'] as $slot) {
                foreach ($files[$slot['type']->value] ?? [] as $file) {
                    $this->storeFile($inquiry, $by, $slot['type'], $file, $history, $written);
                }
            }
        }

        if ($target === SalesStatus::Won && ($files[DocumentType::JobOrder->value] ?? []) !== [] && $inquiry->process_status === null) {
            $this->inquiryStatusService->transition($inquiry, InquiryStatusTrack::Process, ProcessStatus::OrderReceived, $by, trim($note));
        }

        return $inquiry;
    }

    /**
     * Sales uploads the job order after winning without one: it starts
     * logistics at order_received, and the file is that update's group. Only
     * once, on a won inquiry whose logistics has not started.
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws ModelNotFoundException When there is no such inquiry.
     * @throws ValidationException When the job order cannot be uploaded now, or is not one file.
     */
    public function attachJobOrder(User $by, string $reference, string $note, array $files, WrittenFiles $written): Inquiry
    {
        $inquiry = $this->inquiryRepository->findByReferenceForUpdate($reference)
            ?? throw (new ModelNotFoundException)->setModel(Inquiry::class, [$reference]);

        if (! InquiryStatusRules::canUploadJobOrder($inquiry)) {
            throw ValidationException::withMessages(['files.job_order' => $inquiry->process_status === null
                ? 'The job order comes once the client has approved the quotation.'
                : 'The job order is already in.']);
        }

        $this->assertNote($note);

        if (count($files) !== 1) {
            throw ValidationException::withMessages(['files.job_order' => 'Add one job order.']);
        }

        $history = $this->inquiryStatusService->transition($inquiry, InquiryStatusTrack::Process, ProcessStatus::OrderReceived, $by, trim($note));
        $this->storeFile($inquiry, $by, DocumentType::JobOrder, $files[0], $history, $written);

        return $inquiry;
    }

    private function assertMove(Inquiry $inquiry, InquiryStatusTrack $track, string $to): void
    {
        $current = $inquiry->{$track->column()}?->value;

        if ($to === $current && ! ($track === InquiryStatusTrack::Sales && $to === SalesStatus::Revision->value)) {
            throw ValidationException::withMessages(['to' => 'Choose a status other than the current one.']);
        }

        if (! InquiryStatusRules::canMove($inquiry, $track) || ! in_array($to, InquiryStatusRules::allowedTargets($track, $current, $inquiry->quote_revision !== null), true)) {
            throw ValidationException::withMessages(['to' => $track === InquiryStatusTrack::Payment && $inquiry->sales_status !== SalesStatus::Won
                ? 'Payment starts once the client has approved the quotation.'
                : 'This inquiry cannot move to that status.']);
        }
    }

    private function assertNote(string $note): void
    {
        $length = mb_strlen(trim($note));

        if ($length === 0) {
            throw ValidationException::withMessages(['note' => 'Add a note explaining this change.']);
        }

        if ($length > InquiryStatusRules::NOTE_MAX_LENGTH) {
            throw ValidationException::withMessages(['note' => 'Keep the note to 500 characters.']);
        }
    }

    /** @param  array<string, list<UploadedFile>>  $files */
    private function assertFiles(InquiryStatusTrack $track, string $to, array $files): void
    {
        $required = [];
        $errors = [];

        foreach (InquiryStatusRules::requiredFiles($track, $to) as $group) {
            foreach ($group['slots'] as $slot) {
                $type = $slot['type']->value;
                $count = count($files[$type] ?? []);
                $required[] = $type;

                if ($count === 0 && $slot['required']) {
                    $errors["files.{$type}"] = "Add the {$slot['label']}.";
                } elseif (! $slot['multiple'] && $count > 1) {
                    $errors["files.{$type}"] = "Add one {$slot['label']} only.";
                }
            }
        }

        foreach (array_keys($files) as $type) {
            if (! in_array($type, $required, true) && $files[$type] !== []) {
                $errors["files.{$type}"] = 'This status does not take that file.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The amount the move carries, checked against the quoted total.
     *
     * @throws ValidationException
     */
    private function assertAmount(Inquiry $inquiry, InquiryStatusTrack $track, string $to, ?string $amount): ?string
    {
        $field = InquiryStatusRules::extraField($track, $to);

        if ($field === null) {
            return null;
        }

        $centavos = $amount === null ? 0 : (int) round((float) $amount * 100);

        if ($centavos <= 0) {
            throw ValidationException::withMessages([$field => match ($field) {
                InquiryStatusRules::QUOTED_TOTAL => 'Enter the quoted total, above 0.',
                InquiryStatusRules::CONTRACT_TOTAL => 'Enter the total contract amount, above 0.',
                default => 'Enter the amount received, above 0.',
            }]);
        }

        if ($field === InquiryStatusRules::AMOUNT_RECEIVED) {
            // An inquiry billed before the contract total was asked for is counted against its quote.
            $total = (int) round((float) ($inquiry->contract_total ?? $inquiry->quoted_total) * 100);
            $paid = $this->inquiryRepository->paidCentavos($inquiry) + $centavos;
            $peso = fn (int $value): string => '₱'.number_format($value / 100, 2);

            if ($to === PaymentStatus::FullyPaid->value && $paid !== $total) {
                throw ValidationException::withMessages([$field => "Full payment has to bring the payments to the contract total of {$peso($total)}; this would make {$peso($paid)}."]);
            }

            if ($to === PaymentStatus::Partial->value && $paid >= $total) {
                throw ValidationException::withMessages([$field => "A partial payment has to leave a balance: this would make {$peso($paid)} of {$peso($total)}."]);
            }
        }

        return number_format($centavos / 100, 2, '.', '');
    }

    /**
     * One of the update's files on the private disk under
     * inquiries/{reference}/, recorded as part of the update's group. The
     * stored name is generated; the uploader's own name is kept only as
     * `original_name`.
     */
    private function storeFile(Inquiry $inquiry, User $by, DocumentType $type, UploadedFile $file, InquiryStatusHistory $history, WrittenFiles $written): void
    {
        $this->fileUploads->authorize($by, $type, $inquiry);

        $disk = config('inquiry.disk');
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid()->toString().($extension === '' ? '' : '.'.$extension);
        $path = Storage::disk($disk)->putFileAs("inquiries/{$inquiry->reference}", $file, $name);

        if ($path === false) {
            throw new RuntimeException("Could not store {$file->getClientOriginalName()}.");
        }

        $written->record($path);

        $this->inquiryRepository->addFile($inquiry, [
            'document_type' => $type,
            'status_history_id' => $history->id,
            'revision' => $type === DocumentType::Quotation ? $inquiry->quote_revision : null,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => $by->id,
        ]);
    }
}
