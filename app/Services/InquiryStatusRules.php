<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\User;

/**
 * Where each status track may move by hand, and what a move has to carry. The
 * Update Status dialog mirrors this (resources/js/lib/status-rules.ts); this is
 * the one the server applies.
 *
 * - **Sales:** any value but the current one, once sales has started (marketing
 *   winning the inquiry starts it). `quoted` is the quotation sent to the
 *   client and `revision` each revised one, again and again if need be; both
 *   carry the quotation and the quoted total, and each is a new quotation
 *   revision. `won` is the client's approval, and carries the job order.
 *   `revision` and `won` only once the inquiry has been quoted. `rejected` is
 *   for an inquiry not yet won; once won, the only move is `for_refund`, with
 *   the refund request form, and only while there is money to give back and
 *   the project is not done: a partial or full payment is in, and logistics
 *   has not completed. `rejected` and `for_refund` are final by hand; the
 *   full refund moves `for_refund` to `rejected` on its own.
 * - **Job order:** sales uploads it with the win. A won inquiry from before
 *   it was required, which has none, can still take it on its own, once.
 * - **Payment:** starts NULL, and only once the inquiry is won. Forward
 *   only: awaiting (with the billing statement and the total contract
 *   amount), then partial or fully paid (each with receipts, an invoice and
 *   the amount received, counted against the contract total). `fully_paid` is
 *   final. Once sales is `for_refund`, payment moves only to refunds, from
 *   any state: `partially_refunded` while part of what was paid is still
 *   held, then `refunded` once all of it has gone back, each with the refund
 *   settled file and the amount refunded. `refunded` is final, and cancels
 *   the inquiry (InquiryStatusUpdateService).
 * - **Logistics:** any value but the current one, once it has started: the
 *   first payment (partial or fully paid) on an inquiry with its job order
 *   starts it at order_received. `completed` is final, and nothing moves once
 *   sales is `for_refund`. No files.
 */
final class InquiryStatusRules
{
    public const NOTE_MAX_LENGTH = 500;

    public const LOCKED_AFTER_APPROVAL = 'The client approved the quotation, so this project can no longer be changed.';

    public const QUOTED_TOTAL = 'quotedTotal';

    public const AMOUNT_RECEIVED = 'amountReceived';

    public const CONTRACT_TOTAL = 'contractTotal';

    public const AMOUNT_REFUNDED = 'amountRefunded';

    /** @var array<string, list<string>> */
    private const PAYMENT_NEXT = [
        '' => ['awaiting'],
        'awaiting' => ['partial', 'fully_paid'],
        'partial' => ['fully_paid'],
        'fully_paid' => [],
    ];

    /** @var array<string, list<string>> Payment's moves once sales is for refund. */
    private const REFUND_NEXT = [
        '' => ['partially_refunded', 'refunded'],
        'awaiting' => ['partially_refunded', 'refunded'],
        'partial' => ['partially_refunded', 'refunded'],
        'fully_paid' => ['partially_refunded', 'refunded'],
        'partially_refunded' => ['refunded'],
        'refunded' => [],
    ];

    /**
     * The values a track may move to from `$current`, never including it but
     * for a revision after a revision. `$hasBeenQuoted` (a quotation was ever
     * sent) opens sales' revision and won. `$sales` is the inquiry's sales
     * status, which turns payment to refunds once it is for refund.
     * `$refundable` says whether a won inquiry may go to for refund now
     * (canRequestRefund()).
     *
     * @return list<string>
     */
    public static function allowedTargets(InquiryStatusTrack $track, ?string $current, bool $hasBeenQuoted = false, ?string $sales = null, bool $refundable = true): array
    {
        if ($track === InquiryStatusTrack::Payment && $sales === SalesStatus::ForRefund->value) {
            return self::REFUND_NEXT[$current ?? ''] ?? [];
        }

        if ($current !== null && in_array($current, self::finals($track), true)) {
            return [];
        }

        if ($track === InquiryStatusTrack::Payment) {
            return self::PAYMENT_NEXT[$current ?? ''] ?? [];
        }

        if ($current === null) {
            return [];
        }

        // Once won, the only way on is a refund, and only while one can be made.
        if ($track === InquiryStatusTrack::Sales && $current === SalesStatus::Won->value) {
            return $refundable ? [SalesStatus::ForRefund->value] : [];
        }

        $values = array_map(fn (\BackedEnum $case): string => (string) $case->value, ($track->enum())::cases());

        if ($track !== InquiryStatusTrack::Sales) {
            return array_values(array_filter($values, fn (string $value): bool => $value !== $current));
        }

        $afterQuote = [SalesStatus::Revision->value, SalesStatus::Won->value];

        return array_values(array_filter($values, fn (string $value): bool => ($value !== $current || $value === SalesStatus::Revision->value)
            && $value !== SalesStatus::ForRefund->value
            // A revision revises, and the client approves, a quotation they have been sent.
            && ($hasBeenQuoted || ! in_array($value, $afterQuote, true))));
    }

    /**
     * Whether the inquiry's track can move by hand at all: a target exists,
     * payment waits for the client's approval, and logistics stops once the
     * client has asked for a refund.
     */
    public static function canMove(Inquiry $inquiry, InquiryStatusTrack $track): bool
    {
        if ($track === InquiryStatusTrack::Payment && (! self::isApproved($inquiry) || $inquiry->quoted_total === null)) {
            return false;
        }

        if ($track === InquiryStatusTrack::Process && $inquiry->sales_status === SalesStatus::ForRefund) {
            return false;
        }

        return self::targetsFor($inquiry, $track) !== [];
    }

    /**
     * Where the inquiry's track may move now, its own state taken into account.
     *
     * @return list<string>
     */
    public static function targetsFor(Inquiry $inquiry, InquiryStatusTrack $track): array
    {
        return self::allowedTargets($track, $inquiry->{$track->column()}?->value, $inquiry->quote_revision !== null, $inquiry->sales_status?->value, self::canRequestRefund($inquiry));
    }

    /**
     * Whether sales may ask for a refund: there is money to give back (a
     * partial or full payment is in) and the project is not done (logistics
     * has not completed).
     */
    public static function canRequestRefund(Inquiry $inquiry): bool
    {
        return in_array($inquiry->payment_status, [PaymentStatus::Partial, PaymentStatus::FullyPaid], true)
            && $inquiry->process_status !== ProcessStatus::Completed;
    }

    /** Whether the client has approved the quotation: won, or for refund after it. */
    public static function isApproved(Inquiry $inquiry): bool
    {
        return in_array($inquiry->sales_status, [SalesStatus::Won, SalesStatus::ForRefund], true);
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
            $track === InquiryStatusTrack::Sales && $to === SalesStatus::ForRefund->value => [[
                'label' => 'Refund request',
                'slots' => [
                    ['type' => DocumentType::RefundRequest, 'label' => 'refund request form', 'multiple' => false, 'required' => true],
                ],
            ]],
            $track === InquiryStatusTrack::Payment && self::isRefund($to) => [[
                'label' => 'Refund settled',
                'slots' => [
                    ['type' => DocumentType::RefundSettlement, 'label' => 'refund settled file', 'multiple' => false, 'required' => true],
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
     * Whether sales may upload the job order now: the inquiry is won and has
     * no job order yet.
     */
    public static function canUploadJobOrder(Inquiry $inquiry): bool
    {
        return $inquiry->sales_status === SalesStatus::Won && ! $inquiry->hasJobOrder();
    }

    /**
     * Whether staff may still change the inquiry's project (Edit): until it
     * is closed or cancelled.
     */
    public static function isOpen(Inquiry $inquiry): bool
    {
        return ! in_array($inquiry->status, [InquiryStatus::Closed, InquiryStatus::Cancelled], true);
    }

    /**
     * Whether `$user` is shown pending inquiries (not yet started by sales) on
     * the Inquiry list: the admin and owner, and marketing and sales, who pick
     * them up. Accounting, logistics and technical start later in the flow.
     */
    public static function seesPending(?User $user): bool
    {
        return $user?->hasAnyRole([Role::Admin->value, Role::Owner->value, Role::Marketing->value, Role::Sales->value]) ?? false;
    }

    /**
     * Whether the inquiry's project may still change (Edit): while it is open
     * and the client has not approved the quotation. Once won it may not, by
     * anyone, so a project the client signed off on does not change under them.
     */
    public static function isEditable(Inquiry $inquiry): bool
    {
        return self::isOpen($inquiry) && ! self::isApproved($inquiry);
    }

    /** The amount a move has to carry, by its request key, or null. */
    public static function extraField(InquiryStatusTrack $track, string $to): ?string
    {
        return match (true) {
            $track === InquiryStatusTrack::Sales && self::isQuote($to) => self::QUOTED_TOTAL,
            $track === InquiryStatusTrack::Payment && $to === PaymentStatus::Awaiting->value => self::CONTRACT_TOTAL,
            $track === InquiryStatusTrack::Payment && in_array($to, [PaymentStatus::Partial->value, PaymentStatus::FullyPaid->value], true) => self::AMOUNT_RECEIVED,
            $track === InquiryStatusTrack::Payment && self::isRefund($to) => self::AMOUNT_REFUNDED,
            default => null,
        };
    }

    /** Whether a payment move returns money: a partial or the full refund. */
    public static function isRefund(string $to): bool
    {
        return in_array($to, [PaymentStatus::PartiallyRefunded->value, PaymentStatus::Refunded->value], true);
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
            InquiryStatusTrack::Sales => [SalesStatus::Rejected->value, SalesStatus::ForRefund->value],
            InquiryStatusTrack::Payment => [PaymentStatus::FullyPaid->value, PaymentStatus::Refunded->value],
            InquiryStatusTrack::Process => [ProcessStatus::Completed->value],
        };
    }
}
