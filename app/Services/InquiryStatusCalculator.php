<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;

/**
 * The status an inquiry is shown at, from its lead's status and its role
 * statuses. Pure: the same four answers always give the same status. A NULL
 * role status is a stage that has not started, and is never shown; a NULL lead
 * is an inquiry not linked to a lead yet.
 *
 * The first rule that matches wins:
 * 1. cancelled when its lead was lost or sales was rejected;
 * 2. closed when logistics has completed;
 * 3. the logistics status once sales has uploaded the job order (and
 *    logistics has started, which approval guarantees);
 * 4. the sales status once sales has started;
 * 5. pending otherwise.
 *
 * Accounting's status is not part of it: it has a badge of its own.
 *
 * SQL is the same rules as a CASE expression, for filtering and sorting in
 * the database (the Inquiry display-status scopes). The two are tested to agree.
 */
class InquiryStatusCalculator
{
    public static function calculate(
        ?LeadStatus $lead,
        ?SalesStatus $sales,
        ?ProcessStatus $process,
        bool $hasJobOrder,
    ): InquiryStatus {
        if ($lead === LeadStatus::Lost || $sales === SalesStatus::Rejected) {
            return InquiryStatus::Cancelled;
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
     * model's `files` relation. An inquiry with no lead has no lost lead.
     *
     * One literal string, with nothing interpolated, so it is safe to put into
     * raw SQL as it is. `job_order` is DocumentType::JobOrder's value and `lost`
     * LeadStatus::Lost's; the test
     * that the PHP and SQL rules agree on every combination would catch the two
     * drifting apart.
     */
    public const SQL = <<<'SQL'
        CASE
            WHEN inquiries.sales_status = 'rejected' OR EXISTS (
                SELECT 1 FROM leads
                WHERE leads.id = inquiries.lead_id
                    AND leads.status = 'lost'
            ) THEN 'cancelled'
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
