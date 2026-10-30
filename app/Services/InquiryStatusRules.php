<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;
use App\Models\Inquiry;

/**
 * Where each status track may move by hand, and what a move has to carry. The
 * Update Status dialog mirrors this (resources/js/lib/status-rules.ts); this is
 * the one the server applies.
 *
 * - **Sales:** any value but the current one, once sales has started (a won
 *   lead starts it). `quoted` is the quotation sent to the client and
 *   `revision` each revised one, again and again if need be; both carry the
 *   quotation and the quoted total, and each is a new quotation revision.
 *   `won` is the client's approval, and carries the job order. `revision` and
 *   `won` only once the inquiry has been quoted. `won` and `rejected` are
 *   final.
 * - **Job order:** sales uploads it with the win; it starts logistics at
 *   order_received. A won inquiry from before it was required, which has
 *   none, can still take it on its own, once.
 * - **Payment:** starts NULL, and only once the inquiry is won. Forward
 *   only: awaiting (with the billing statement and the total contract
 *   amount), then partial or fully paid (each with receipts, an invoice and
 *   the amount received, counted against the contract total). `fully_paid` is
 *   final.
 * - **Logistics:** any value but the current one, once it has started (the
 *   job order starts it at order_received). `completed` is final. No files.
 */
final class InquiryStatusRules
{
    public const NOTE_MAX_LENGTH = 500;

    public const QUOTED_TOTAL = 'quotedTotal';

    public const AMOUNT_RECEIVED = 'amountReceived';

    public const CONTRACT_TOTAL = 'contractTotal';

    /** @var array<string, list<string>> */
    private const PAYMENT_NEXT = [
        '' => ['awaiting'],
        'awaiting' => ['partial', 'fully_paid'],
        'partial' => ['fully_paid'],
        'fully_paid' => [],
        // Reached only by the refund flow still to come, and final.
        'refunded' => [],
    ];

    /**
     * The values a track may move to from `$current`, never including it but
     * for a revision after a revision. `$hasBeenQuoted` (a quotation was ever
     * sent) opens sales' revision and won.
     *
     * @return list<string>
     */
    public static function allowedTargets(InquiryStatusTrack $track, ?string $current, bool $hasBeenQuoted = false): array
    {
        if ($current !== null && in_array($current, self::finals($track), true)) {
            return [];
        }

        if ($track === InquiryStatusTrack::Payment) {
            return self::PAYMENT_NEXT[$current ?? ''] ?? [];
        }

        if ($current === null) {
            return [];
        }

        $values = array_map(fn (\BackedEnum $case): string => (string) $case->value, ($track->enum())::cases());

        if ($track !== InquiryStatusTrack::Sales) {
            return array_values(array_filter($values, fn (string $value): bool => $value !== $current));
        }

        $afterQuote = [SalesStatus::Revision->value, SalesStatus::Won->value];

        return array_values(array_filter($values, fn (string $value): bool => ($value !== $current || $value === SalesStatus::Revision->value)
            // A revision revises, and the client approves, a quotation they have been sent.
            && ($hasBeenQuoted || ! in_array($value, $afterQuote, true))));
    }

    /**
     * Whether the inquiry's track can move by hand at all: a target exists,
     * and payment waits for the client's approval.
     */
    public static function canMove(Inquiry $inquiry, InquiryStatusTrack $track): bool
    {
        if ($track === InquiryStatusTrack::Payment && ($inquiry->sales_status !== SalesStatus::Won || $inquiry->quoted_total === null)) {
            return false;
        }

        return self::allowedTargets($track, $inquiry->{$track->column()}?->value, $inquiry->quote_revision !== null) !== [];
    }

    /**
     * The files a move takes, as labelled groups of slots. A slot is one file
     * unless `multiple`, and has to be there unless not `required`.
     *
     * @return list<array{label: string, slots: list<array{type: DocumentType, label: string, multiple: bool, required: bool}>}>
     */
    public static function requiredFiles(InquiryStatusTrack $track, string $to): array
    {
        return match (true) {
            $track === InquiryStatusTrack::Sales && self::isQuote($to) => [[
                'label' => 'Quotation',
                'slots' => [
                    ['type' => DocumentType::Quotation, 'label' => 'quotation', 'multiple' => false, 'required' => true],
                ],
            ]],
            $track === InquiryStatusTrack::Sales && $to === SalesStatus::Won->value => [[
                'label' => 'Job order',
                'slots' => [
                    ['type' => DocumentType::JobOrder, 'label' => 'job order', 'multiple' => false, 'required' => true],
                ],
            ]],
            $track === InquiryStatusTrack::Payment && $to === PaymentStatus::Awaiting->value => [[
                'label' => 'Billing statement',
                'slots' => [
                    ['type' => DocumentType::BillingStatement, 'label' => 'billing statement', 'multiple' => false, 'required' => true],
                ],
            ]],
            $track === InquiryStatusTrack::Payment && in_array($to, [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true) => [[
                'label' => 'Collection of receipts',
                'slots' => [
                    ['type' => DocumentType::Receipt, 'label' => 'receipts', 'multiple' => true, 'required' => true],
                    ['type' => DocumentType::Invoice, 'label' => 'invoice', 'multiple' => false, 'required' => true],
                ],
            ]],
            default => [],
        };
    }

    /**
     * Whether sales may upload the job order now: the inquiry is won and
     * logistics has not started, so there is none yet.
     */
    public static function canUploadJobOrder(Inquiry $inquiry): bool
    {
        return $inquiry->sales_status === SalesStatus::Won && $inquiry->process_status === null;
    }

    /**
     * Whether staff may still change the inquiry's project (Edit): until it
     * is closed or cancelled.
     */
    public static function isOpen(Inquiry $inquiry): bool
    {
        return ! in_array($inquiry->status, [InquiryStatus::Closed, InquiryStatus::Cancelled], true);
    }

    /** The amount a move has to carry, by its request key, or null. */
    public static function extraField(InquiryStatusTrack $track, string $to): ?string
    {
        return match (true) {
            $track === InquiryStatusTrack::Sales && self::isQuote($to) => self::QUOTED_TOTAL,
            $track === InquiryStatusTrack::Payment && $to === PaymentStatus::Awaiting->value => self::CONTRACT_TOTAL,
            $track === InquiryStatusTrack::Payment && in_array($to, [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true) => self::AMOUNT_RECEIVED,
            default => null,
        };
    }

    /** Whether a sales move sends a quotation: the first, or a revised one. */
    public static function isQuote(string $to): bool
    {
        return in_array($to, [SalesStatus::Quoted->value, SalesStatus::Revision->value], true);
    }

    /** @return list<string> */
    private static function finals(InquiryStatusTrack $track): array
    {
        return match ($track) {
            InquiryStatusTrack::Sales => [SalesStatus::Rejected->value, SalesStatus::Won->value],
            InquiryStatusTrack::Payment => [PaymentStatus::FullyPaid->value, PaymentStatus::Refunded->value],
            InquiryStatusTrack::Process => [ProcessStatus::Completed->value],
        };
    }
}
