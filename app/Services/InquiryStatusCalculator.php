<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\MarketingStatus;
use App\Enums\ProcessStatus;
use App\Enums\SalesStatus;

/**
 * The status an inquiry is shown at, from its role statuses. Pure: the same
 * four answers always give the same status. A NULL role status is a stage that
 * has not started, and is never shown.
 *
 * The first rule that matches wins:
 * 1. cancelled when marketing lost the inquiry or sales was rejected;
 * 2. closed when logistics has completed;
 * 3. the logistics status once sales has uploaded the job order (and
 *    logistics has started, which approval guarantees);
 * 4. the sales status once sales has started;
 * 5. pending otherwise.
 *
 * Accounting's status is not part of it: it has a badge of its own.
 *
 * sql() is the same rules as a CASE expression, for filtering and sorting in
 * the database (Inquiry::scopeWithDisplayStatus). The two are tested to agree.
 */
class InquiryStatusCalculator
{
    public static function calculate(
        MarketingStatus $marketing,
        ?SalesStatus $sales,
        ?ProcessStatus $process,
        bool $hasJobOrder,
    ): InquiryStatus {
        if ($marketing === MarketingStatus::Lost || $sales === SalesStatus::Rejected) {
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
     * model's `files` relation.
     */
    public static function sql(string $table = 'inquiries'): string
    {
        $jobOrder = DocumentType::JobOrder->value;

        return <<<SQL
            CASE
                WHEN {$table}.marketing_status = 'lost' OR {$table}.sales_status = 'rejected' THEN 'cancelled'
                WHEN {$table}.process_status = 'completed' THEN 'closed'
                WHEN {$table}.process_status IS NOT NULL AND EXISTS (
                    SELECT 1 FROM inquiry_files
                    WHERE inquiry_files.inquiry_id = {$table}.id
                        AND inquiry_files.document_type = '{$jobOrder}'
                        AND inquiry_files.deleted_at IS NULL
                ) THEN {$table}.process_status
                WHEN {$table}.sales_status IS NOT NULL THEN {$table}.sales_status
                ELSE 'pending'
            END
            SQL;
    }
}
