<?php

namespace App\Http\Resources;

use App\Enums\DetailFieldScope;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\SalesStatus;
use App\Models\Brand;
use App\Models\DetailField;
use App\Models\Inquiry;
use App\Models\InquiryDetailAnswer;
use App\Models\InquiryFile;
use App\Models\InquiryHandler;
use App\Models\InquiryStatusHistory;
use App\Models\InquiryStone;
use App\Models\StoneVariant;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Policies\InquiryFileVisibility;
use App\Services\ScheduleService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * An inquiry as its client sees it: the one project it is, the status it is
 * shown at, its three role statuses, the history of how they moved, its
 * payment records, the documents the team has shared with the client, and
 * the staff assigned to it. Names, quantities and dates: no database ids,
 * nothing about who changed a status, nothing about the staff beyond a name
 * and a role, and no file paths or disks. Each file instead carries the URL
 * it is previewed and downloaded by (`inquiry.files.show`).
 *
 * Nothing about its lead is sent: a lost lead shows only as the inquiry being
 * cancelled. Whoever sends quotations (sales, and the super-admin) is sent
 * every quotation revision rather than the latest.
 *
 * @mixin Inquiry
 */
class InquiryDetailsResource extends JsonResource
{
    /** @var array{id: int, name: string}|null */
    private ?array $conversationLink = null;

    /**
     * The inquiry's group chat, when the viewer is in it.
     *
     * @param  array{id: int, name: string}|null  $conversation
     */
    public function withConversation(?array $conversation): static
    {
        $this->conversationLink = $conversation;

        return $this;
    }

    /**
     * @return array{reference: string, status: string, salesStatus: string|null, paymentStatus: string|null, processStatus: string|null, createdAt: string, contractTotal: string|null, history: list<array{track: string, status: string, at: string}>, payments: list<array{id: string, kind: string, amount: string, currentBalance: string|null, remainingBalance: string|null, at: string}>, documents: list<array{id: string, status: string|null, revision: int|null, at: string, files: list<array{id: string, type: string, name: string, sizeBytes: int, mimeType: string, url: string, previewable: bool, uploadedAt: string, format: string}>}>, assignedStaff: list<array{name: string, roleLabel: string|null}>, project: array<string, mixed>, conversation: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $files = (new InquiryFileVisibility)->filter($viewer, $this->files);

        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            // Null until the role's stage has started.
            'salesStatus' => $this->sales_status?->value,
            'paymentStatus' => $this->payment_status?->value,
            'processStatus' => $this->process_status?->value,
            'createdAt' => $this->created_at->toIso8601String(),
            // The total contract amount, once accounting has billed it.
            'contractTotal' => $this->contract_total,
            'history' => $this->history(),
            'payments' => $this->payments(),
            'documents' => $this->documents($files),
            'assignedStaff' => $this->assignedStaff(),
            'project' => $this->project($files),
            'actions' => (new InquiryActions)->for($this->resource),
            'conversation' => $this->conversationLink,
        ];
    }

    /**
     * The client's activity log: every change to sales, payment and logistics,
     * oldest first, a stage starting included. Who made a change is not sent:
     * who handles the inquiry is assignedStaff()'s, not the log's.
     *
     * Marketing's decision is the team's own business and is left out, except
     * that the client has to see their inquiry being cancelled: an inquiry
     * marketing lost (or the client cancelled before sales started) ends with
     * an `inquiry` `cancelled` entry at the time it was lost.
     *
     * @return list<array{track: string, status: string, at: string}>
     */
    private function history(): array
    {
        $entries = $this->statusHistory
            ->map(fn (InquiryStatusHistory $change): array => [
                'track' => $change->track->value,
                'status' => $change->to_status,
                'at' => $change->changed_at->toIso8601String(),
            ])
            ->values()
            ->all();

        if ($this->sales_status !== SalesStatus::Rejected && $this->marketing_status === LeadStatus::Lost) {
            $entries[] = [
                'track' => 'inquiry',
                'status' => InquiryStatus::Cancelled->value,
                'at' => $this->marketing_status_changed_at->toIso8601String(),
            ];
        }

        return array_values($entries);
    }

    /**
     * The staff handling the inquiry now (`inquiry_handlers`), first assigned
     * first, then by name. A name and a role only, as a client already reads a
     * name on a Regray Team thread; never how to reach them, nor who assigned
     * them. Neither marketing holding the lead nor whoever opened the inquiry
     * is listed: only an assignment makes someone its staff.
     *
     * @return list<array{name: string, roleLabel: string|null}>
     */
    private function assignedStaff(): array
    {
        return array_values($this->handlers
            ->sortBy([
                fn (InquiryHandler $a, InquiryHandler $b): int => $a->assigned_at <=> $b->assigned_at,
                fn (InquiryHandler $a, InquiryHandler $b): int => strcasecmp($a->user->name, $b->user->name),
            ])
            ->map(fn (InquiryHandler $handler): array => [
                'name' => $handler->user->name,
                'roleLabel' => $handler->user->primaryRole()?->label(),
            ])
            ->all());
    }

    /**
     * The payment records, oldest first: the total contract amount billed when
     * payment moved to awaiting, then each payment (or refund) as accounting
     * received it: its own amount, the balance owed before it and the balance
     * remaining after it. A refund reads instead against what the studio held
     * of the client's money before it and still holds after it. Nothing before
     * payment has started.
     *
     * An inquiry billed before the contract total was asked for has no contract
     * entry, and its balance is counted against the quoted total.
     *
     * @return list<array{id: string, kind: string, amount: string, currentBalance: string|null, remainingBalance: string|null, at: string}>
     */
    private function payments(): array
    {
        $records = [];
        $money = fn (int $centavos): string => number_format($centavos / 100, 2, '.', '');
        $totalSource = $this->contract_total ?? $this->quoted_total;
        $total = $totalSource === null ? null : (int) round((float) $totalSource * 100);
        $billedAt = $this->statusHistory
            ->first(fn (InquiryStatusHistory $change): bool => $change->track === InquiryStatusTrack::Payment && $change->to_status === PaymentStatus::Awaiting->value)
            ?->changed_at;

        if ($this->contract_total !== null && $billedAt !== null) {
            $records[] = [
                'kind' => 'contract',
                'amount' => $money($total),
                'currentBalance' => null,
                'remainingBalance' => null,
                'at' => $billedAt->toIso8601String(),
            ];
        }

        $balance = $total;
        $held = 0;

        foreach ($this->paymentTransactions as $transaction) {
            $amount = (int) round((float) $transaction->amount * 100);

            // A refund reads against what the studio still holds of the client's
            // money; a payment against the balance still owed.
            if ($transaction->kind === 'refund') {
                $records[] = [
                    'kind' => 'refund',
                    'amount' => $money($amount),
                    'currentBalance' => $money($held),
                    'remainingBalance' => $money($held - $amount),
                    'at' => $transaction->received_at->toIso8601String(),
                ];
                $held -= $amount;

                continue;
            }

            $before = $balance;
            $balance = $balance === null ? null : $balance - $amount;
            $held += $amount;

            $records[] = [
                'kind' => $transaction->kind,
                'amount' => $money($amount),
                'currentBalance' => $before === null ? null : $money($before),
                'remainingBalance' => $balance === null ? null : $money($balance),
                'at' => $transaction->received_at->toIso8601String(),
            ];
        }

        return array_map(
            // Keys for the page's list, not database ids.
            fn (array $record, int $index): array => ['id' => 'payment-'.($index + 1), ...$record],
            $records,
            array_keys($records),
        );
    }

    /**
     * The documents the team has shared with the client, one group per status
     * update, oldest first: the quotation with its revision, the billing
     * statement, and each payment's receipts and invoice. Which of them this
     * reader sees (the job order for staff only, older quotation revisions
     * for sales only) is InquiryFileVisibility's, which the file route asks
     * too. A group is headed by the update's status and date.
     *
     * @param  Collection<int, InquiryFile>  $files  the files this reader may see
     * @return list<array{id: string, status: string|null, revision: int|null, at: string, files: list<array{id: string, type: string, name: string, sizeBytes: int, mimeType: string, url: string, previewable: bool, uploadedAt: string, format: string}>}>
     */
    private function documents(Collection $files): array
    {
        $updates = $this->statusHistory->keyBy('id');
        $position = 0;

        return array_values($files
            ->filter(fn (InquiryFile $file): bool => $file->document_type !== DocumentType::ClientAttachment)
            ->sortBy(fn (InquiryFile $file): string => $file->created_at->format('U.u').'-'.str_pad((string) $file->id, 12, '0', STR_PAD_LEFT))
            ->groupBy(fn (InquiryFile $file): string => $file->status_history_id === null ? 'file-'.$file->id : 'update-'.$file->status_history_id)
            ->values()
            ->map(function ($files, int $index) use ($updates, &$position): array {
                /** @var InquiryFile $first */
                $first = $files->first();
                $update = $first->status_history_id === null ? null : $updates->get($first->status_history_id);

                return [
                    // Keys for the page's list, not database ids.
                    'id' => 'group-'.($index + 1),
                    'status' => $update?->to_status,
                    'revision' => $files->firstWhere('document_type', DocumentType::Quotation)?->revision,
                    'at' => ($update === null ? $first->created_at : $update->changed_at)->toIso8601String(),
                    'files' => array_values($files->map(function (InquiryFile $file) use (&$position): array {
                        $position++;

                        return [
                            'id' => 'document-'.$position,
                            'type' => $file->document_type->value,
                            ...$this->storedFile($file),
                        ];
                    })->all()),
                ];
            })
            ->all());
    }

    /**
     * The target deadline is the arrival of the inquiry's schedule, in the
     * studio's zone, so it reads back as the date and time the client typed.
     * The files are the client's own attachments; staff documents are not shown.
     *
     * Each stone carries the stone-scope answers its service asks, labelled,
     * in the service's order (`details`), and null for the rest.
     * `isStandardSlab` says whether its size and thickness are one of the
     * slabs the stone is listed in (null where no thickness is asked). `answers` are
     * the project-scope ones, labelled and already worded: "Same as stone"
     * reads as the first stone's name. `requiresStones` is false for a service
     * that asks for no stones, whose page has no Stones section.
     *
     * `brands` are the brands the client would like, with `brandNoPreference`
     * when they had none. `requirements.fulfillment` is how the work reaches
     * them, worded ("Pickup", "Delivery to …", "Installation at …"), with the
     * method (null for an installed service) and the address it was sent
     * with (null for a pickup).
     *
     * @param  Collection<int, InquiryFile>  $files  the files this reader may see
     * @return array<string, mixed>
     */
    private function project(Collection $files): array
    {
        $arrivalAt = $this->schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $questions = $this->service === null ? new EloquentCollection : $this->service->detailFields;
        $stoneQuestions = $questions->filter(fn (DetailField $field): bool => $field->scope === DetailFieldScope::Stone);
        $firstStone = $this->stones->first()?->stone->name;

        return [
            'name' => $this->name,
            'serviceType' => $this->service?->serviceType->name,
            'service' => $this->service?->name,
            'requiresStones' => $this->service === null || $this->service->requires_stones,
            'stoneAdviceRequested' => $this->stone_advice_requested,
            'stones' => $this->stones->map(fn (InquiryStone $row): array => [
                'id' => $row->stone->slug,
                'name' => $row->stone->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                'quantity' => $row->quantity,
                'slabSize' => $row->slabSizeLabel(),
                'thickness' => $row->thicknessLabel(),
                'isStandardSlab' => self::isStandardSlab($row),
                'edge' => $row->edgeProfile?->name,
                'finish' => $row->finish?->name,
                'details' => array_values($stoneQuestions
                    ->map(fn (DetailField $field): array => ['label' => $field->name, 'value' => match ($field->slug) {
                        DetailField::SLAB_SIZE => $row->slabSizeLabel(),
                        DetailField::THICKNESS => $row->thicknessLabel(),
                        DetailField::EDGE_PROFILE => $row->edgeProfile?->name,
                        DetailField::FINISH => $row->finish?->name,
                        default => null,
                    }])
                    ->filter(fn (array $detail): bool => $detail['value'] !== null)
                    ->all()),
            ])->values()->all(),
            'answers' => array_values($this->detailAnswers
                ->sortBy(fn (InquiryDetailAnswer $answer): int => $questions->firstWhere('id', $answer->detail_field_id)?->serviceSortOrder() ?? 0)
                ->map(fn (InquiryDetailAnswer $answer): array => [
                    'label' => $answer->detailField->name,
                    'value' => $answer->displayValue($firstStone),
                ])
                ->all()),
            'brands' => array_values($this->brands->map(fn (Brand $brand): array => ['id' => $brand->slug, 'name' => $brand->name])->all()),
            'brandNoPreference' => $this->brand_no_preference,
            'requirements' => [
                'deliveryMethod' => $this->delivery_method?->value,
                'address' => $this->address()?->toPayload(),
                'fulfillment' => $this->fulfillmentSummary(),
                'neededBy' => $arrivalAt?->toDateString(),
                'notes' => $this->notes,
                'attachments' => $files
                    ->filter(fn (InquiryFile $file): bool => $file->document_type === DocumentType::ClientAttachment)
                    ->values()
                    ->map(fn (InquiryFile $file, int $position): array => [
                        'id' => 'file-'.($position + 1),
                        ...$this->storedFile($file),
                    ])->all(),
            ],
        ];
    }

    /**
     * What a file row shows and opens: the uploader's name, size and type,
     * when it was uploaded, and the route it is served by, keyed by its
     * generated `stored_name`. Never a path, disk, hash or database id.
     *
     * @return array{name: string, sizeBytes: int, mimeType: string, url: string, previewable: bool, uploadedAt: string, format: string}
     */
    private function storedFile(InquiryFile $file): array
    {
        return [
            'name' => $file->original_name,
            'sizeBytes' => $file->size_bytes,
            'mimeType' => $file->mime_type,
            'url' => route('inquiry.files.show', [$this->reference, $file->stored_name], absolute: false),
            'previewable' => $file->isPreviewable(),
            'uploadedAt' => $file->created_at->toIso8601String(),
            'format' => $file->formatLabel(),
        ];
    }

    /**
     * Whether a row asked for one of its stone's own slabs: its size and
     * thickness are a variant's. A variant with no size (pieces without set
     * sizes) counts only for a row that gave none. Null for a row whose
     * service asks no thickness, which has no slab to compare. Custom sizes
     * and thicknesses are allowed; this only tells them apart for staff.
     */
    private static function isStandardSlab(InquiryStone $row): ?bool
    {
        $thickness = $row->thicknessLabel();

        if ($thickness === null) {
            return null;
        }

        $size = $row->slabSizeLabel();

        return $row->stone->variants->contains(
            fn (StoneVariant $variant): bool => $variant->thickness->label === $thickness && $variant->size?->label === $size,
        );
    }
}
