<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;

/**
 * The status an inquiry is shown at, from marketing's decision on it and its
 * role statuses. Pure: the same four answers always give the same status. A
 * NULL role status is a stage that has not started, and is never shown; a NULL
 * marketing status is an inquiry still waiting for marketing.
 *
 * The first rule that matches wins:
 * 1. cancelled when marketing lost it, sales was rejected, or accounting
 *    has refunded all of it (a full refund rejects sales too, so this only
 *    matters for one refunded before it did);
 * 2. for_refund while sales has asked for a refund and it is not all back;
 * 3. closed when logistics has completed;
 * 4. the logistics status once sales has uploaded the job order (and
 *    logistics has started, which the first payment does);
 * 5. the sales status once sales has started;
 * 6. pending otherwise.
 *
 * Accounting's status counts only once fully refunded: otherwise it has a
 * badge of its own, which reads refunded beside the cancelled status.
 *
 * SQL is the same rules as a CASE expression, for filtering and sorting in
 * the database (the Inquiry display-status scopes). The two are tested to agree.
 */
class InquiryStatusCalculator
{
    public static function calculate(
        ?LeadStatus $marketing,
        ?SalesStatus $sales,
        ?ProcessStatus $process,
        bool $hasJobOrder,
        ?PaymentStatus $payment = null,
    ): InquiryStatus {
        if ($marketing === LeadStatus::Lost || $sales === SalesStatus::Rejected || $payment === PaymentStatus::Refunded) {
            return InquiryStatus::Cancelled;
        }

        if ($sales === SalesStatus::ForRefund) {
            return InquiryStatus::ForRefund;
        }

        if ($process === ProcessStatus::Completed) {
            return InquiryStatus::Closed;
        }

        if ($hasJobOrder && $process !== null) {
            return InquiryStatus::from($process->value);
        }

        if ($sales !== null) {
            return InquiryStatus::from($sales->value);
        }

        return InquiryStatus::Pending;
    }

    /**
     * The same rules over the `inquiries` table, as a CASE expression that
     * gives the display status's value. NULL is a stage that has not started:
     * a comparison with it is never true, so it falls through to the next rule.
     * A soft-deleted job order file does not count, as it does not for the
     * model's `files` relation.
     *
     * One literal string, with nothing interpolated, so it is safe to put into
     * raw SQL as it is. `job_order` is DocumentType::JobOrder's value, `lost`
     * LeadStatus::Lost's and `refunded` PaymentStatus::Refunded's; the test
     * that the PHP and SQL rules agree on every combination would catch the two
     * drifting apart.
     */
    public const SQL = <<<'SQL'
        CASE
            WHEN inquiries.sales_status = 'rejected' OR inquiries.marketing_status = 'lost' OR inquiries.payment_status = 'refunded' THEN 'cancelled'
            WHEN inquiries.sales_status = 'for_refund' THEN 'for_refund'
            WHEN inquiries.process_status = 'completed' THEN 'closed'
            WHEN inquiries.process_status IS NOT NULL AND EXISTS (
                SELECT 1 FROM inquiry_files
                WHERE inquiry_files.inquiry_id = inquiries.id
                    AND inquiry_files.document_type = 'job_order'
                    AND inquiry_files.deleted_at IS NULL
            ) THEN inquiries.process_status
            WHEN inquiries.sales_status IS NOT NULL THEN inquiries.sales_status
            ELSE 'pending'
        END
        SQL;
}
