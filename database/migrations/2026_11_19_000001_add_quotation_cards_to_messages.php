<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A quotation card: one quotation document Sales sent, posted beside the
 * inquiry card that announced it (PostQuotationCard).
 *
 * - `inquiry_file_id` is the quotation it is about. A card is a record of that
 *   document, so the file cannot be deleted from under it (files are only ever
 *   soft-deleted).
 * - `card_snapshot` is the document as it was when the card was posted: its
 *   name, stored name, size and revision, and the inquiry's name, reference and
 *   material count. A later edit of the inquiry does not rewrite an old card.
 * - One card per conversation and document, so a retried listener posts it
 *   once while every new revision, a new document, posts its own.
 *
 * Every other type keeps both columns NULL, so the type CHECK is replaced
 * whole with the new branch added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('inquiry_file_id')->nullable()->after('schedule_id')->constrained('inquiry_files')->restrictOnDelete();
            $table->jsonb('card_snapshot')->nullable()->after('announced_reason');

            $table->index('inquiry_file_id');
        });

        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');
        DB::statement(self::typeCheck(withQuotationCard: true));
        DB::statement("CREATE UNIQUE INDEX idx_messages_quotation_card_once ON messages (conversation_id, inquiry_file_id) WHERE type = 'quotation_card'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_messages_quotation_card_once');
        DB::statement("DELETE FROM messages WHERE type = 'quotation_card'");
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['inquiry_file_id']);
            $table->dropIndex(['inquiry_file_id']);
            $table->dropColumn(['inquiry_file_id', 'card_snapshot']);
        });

        DB::statement(self::typeCheck(withQuotationCard: false));
    }

    /** The type CHECK from the create migration, with the quotation card's branch when asked. */
    private static function typeCheck(bool $withQuotationCard): string
    {
        $noSnapshot = 'announced_sales_status IS NULL AND announced_payment_status IS NULL AND announced_process_status IS NULL';
        $noScheduleSnapshot = 'schedule_revision IS NULL AND announced_arrival_at IS NULL AND announced_reason IS NULL';
        $noQuotation = $withQuotationCard ? 'AND inquiry_file_id IS NULL AND card_snapshot IS NULL' : '';
        $quotationCard = $withQuotationCard
            ? "OR (type = 'quotation_card' AND body IS NULL AND inquiry_id IS NOT NULL AND inquiry_file_id IS NOT NULL AND card_snapshot IS NOT NULL
                    AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot})"
            : '';

        return "
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND system_event IS NULL AND system_user_id IS NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot} {$noQuotation})
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND announced_status IS NOT NULL
                    AND announced_status IN ('pending','cancelled','closed','negotiation','site_visited','on_hold','quoted','revision','won','order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','delayed','for_refund','refunded')
                    AND {$noScheduleSnapshot} {$noQuotation})
                OR (type = 'schedule_card' AND body IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL AND inquiry_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND {$noSnapshot}
                    AND announced_status IN ('pending','confirmed','rescheduled','served','cancelled','expired')
                    AND schedule_revision IS NOT NULL AND announced_arrival_at IS NOT NULL {$noQuotation})
                OR (type = 'system' AND body IS NULL AND system_event IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot} {$noQuotation})
                {$quotationCard}
            )
        ";
    }
};
