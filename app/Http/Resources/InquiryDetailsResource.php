<?php

namespace App\Http\Resources;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\InquiryStone;
use App\Models\User;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An inquiry as its client sees it: the one project it is, the status it is
 * shown at, its three role statuses, the history of how they moved, its
 * payment records, and the documents the team has shared with the client. Names, quantities and dates:
 * no database ids, nothing about who changed a status or who handles the
 * inquiry, and no file paths or disks.
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
     * @return array{reference: string, status: string, salesStatus: string|null, paymentStatus: string|null, processStatus: string|null, createdAt: string, contractTotal: string|null, history: list<array{track: string, status: string, at: string}>, payments: list<array{id: string, kind: string, amount: string, currentBalance: string|null, remainingBalance: string|null, at: string}>, documents: list<array{id: string, status: string|null, revision: int|null, at: string, files: list<array{id: string, type: string, name: string, sizeBytes: int, mimeType: string}>}>, project: array<string, mixed>, conversation: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();

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
            'documents' => $this->documents($viewer),
            'project' => $this->project(),
            'conversation' => $this->conversationLink,
        ];
    }

    /**
     * The client's activity log: every change to sales, payment and logistics,
     * oldest first, a stage starting included. Who made a change is not sent.
     *
     * The lead is the team's own business and is left out, except that the
     * client has to see their inquiry being cancelled: an inquiry cancelled by
     * its lead being lost (sales not rejected) ends with an `inquiry`
     * `cancelled` entry at the time the lead was lost.
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

        if ($this->status === InquiryStatus::Cancelled && $this->sales_status !== SalesStatus::Rejected && $this->lead?->status === LeadStatus::Lost) {
            $entries[] = [
                'track' => 'inquiry',
                'status' => InquiryStatus::Cancelled->value,
                'at' => $this->lead->status_changed_at->toIso8601String(),
            ];
        }

        return array_values($entries);
    }

    /**
     * The payment records, oldest first: the total contract amount billed when
     * payment moved to awaiting, then each payment (or refund) as accounting
     * received it: its own amount, the balance owed before it and the balance
     * remaining after it. Nothing before payment has started.
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

        foreach ($this->paymentTransactions as $transaction) {
            $amount = (int) round((float) $transaction->amount * 100);
            $before = $balance;
            $balance = $balance === null ? null : $balance + ($transaction->kind === 'refund' ? $amount : -$amount);

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
     * statement, and each payment's receipts and invoice. Staff are also sent
     * the job order, which is internal and never sent to the client. Only the latest
     * quotation is sent, as an older revision is superseded, except to whoever
     * may move the sales status and so sends the quotations: they are sent every
     * revision, to see how the quote moved. A group is headed by the
     * update's status and date. Paths and disks are never sent.
     *
     * @return list<array{id: string, status: string|null, revision: int|null, at: string, files: list<array{id: string, type: string, name: string, sizeBytes: int, mimeType: string}>}>
     */
    private function documents(User $viewer): array
    {
        $updates = $this->statusHistory->keyBy('id');
        $position = 0;
        $latestQuote = $this->files->where('document_type', DocumentType::Quotation)->max('revision');
        $seesEveryRevision = $viewer->can(Permission::InquiriesStatusSales->value);

        return array_values($this->files
            ->filter(fn (InquiryFile $file): bool => ($file->document_type->isSharedWithClient()
                    || ($file->document_type === DocumentType::JobOrder && ! $viewer->isClient()))
                && ($file->document_type !== DocumentType::Quotation || $seesEveryRevision || $file->revision === $latestQuote))
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
                            'name' => $file->original_name,
                            'sizeBytes' => $file->size_bytes,
                            'mimeType' => $file->mime_type,
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
     * @return array<string, mixed>
     */
    private function project(): array
    {
        $arrivalAt = $this->schedule?->arrival_at->setTimezone(ScheduleService::timezone());
        $isVisit = $this->services->isNotEmpty() && $this->services->every(fn ($service): bool => $service->is_standalone);

        return [
            'name' => $this->name,
            'services' => $this->services->map(fn ($service): array => [
                'name' => $service->name,
                'image' => ProjectCardResource::resolveThumbnailUrl($service->image_path),
            ])->values()->all(),
            'applications' => $this->categories->map(fn ($category): string => $category->name)->values()->all(),
            'stones' => $this->stones->map(fn (InquiryStone $row): array => [
                'id' => $row->stone->slug,
                'name' => $row->stone->name,
                'thumbnail' => ProjectCardResource::resolveThumbnailUrl($row->stone->thumbnail_path),
                'quantity' => $row->quantity,
                'edge' => $row->edgeProfile->name,
                'finish' => $row->finish->name,
            ])->values()->all(),
            'requirements' => [
                'location' => $this->location,
                'landmark' => $this->landmark,
                'areaSqm' => $this->area_sqm === null ? null : (float) $this->area_sqm,
                'neededBy' => $arrivalAt?->toDateString(),
                // Only a visit asks for a time; any other project's schedule
                // holds the default time, which the client never typed.
                'neededByTime' => $isVisit ? $arrivalAt?->format('H:i') : null,
                'notes' => $this->notes,
                'attachments' => $this->files
                    ->filter(fn (InquiryFile $file): bool => $file->document_type === DocumentType::ClientAttachment)
                    ->values()
                    ->map(fn (InquiryFile $file, int $position): array => [
                        'id' => 'file-'.($position + 1),
                        'name' => $file->original_name,
                        'sizeBytes' => $file->size_bytes,
                        'mimeType' => $file->mime_type,
                    ])->all(),
            ],
        ];
    }
}
