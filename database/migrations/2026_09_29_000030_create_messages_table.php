<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Text, cards and system events in a conversation.
 *
 * - `reply_to_id` is the message being answered.
 * - `client_key` is the sender's own id for a send, so a retried send lands
 *   once: unique per conversation.
 * - `system` messages record a claim, a release or a reassignment of a
 *   Regray Team thread, and `system_user_id` is who it was about.
 * - An inquiry card announces a snapshot of one inquiry: the status it is
 *   shown at (`announced_status`) and its three role statuses, each NULL until
 *   its stage has started. It is posted once per conversation, inquiry and snapshot (a
 *   NULL counting as equal to a NULL), so a retried listener cannot post the
 *   same change twice.
 * - A schedule card announces a visit's status in `announced_status`, as it
 *   stood at that change: its arrival (`announced_arrival_at`) and the reason
 *   given (`announced_reason`), so an older card keeps its own time after the
 *   visit moves. It is posted once per conversation, visit, status and
 *   revision (`schedule_revision`, the visit's `revision` at the change), so a
 *   retried listener posts it once while a second adjustment posts a second.
 *
 * `conversations.pool_notified_message_id` points at this table while this
 * table points at `conversations`, so that one foreign key is added here, once
 * both tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->foreignId('inquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('announced_status', 20)->nullable();
            $table->string('announced_sales_status', 20)->nullable();
            $table->string('announced_payment_status', 20)->nullable();
            $table->string('announced_process_status', 20)->nullable();
            $table->unsignedInteger('schedule_revision')->nullable();
            $table->timestampTz('announced_arrival_at')->nullable();
            $table->string('announced_reason', 255)->nullable();
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->uuid('client_key')->nullable();
            $table->string('system_event', 30)->nullable();
            $table->foreignId('system_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('edited_at')->nullable();
            $table->softDeletesTz();

            $table->index(['conversation_id', 'id']);
            $table->index('inquiry_id');
            $table->index('schedule_id');
            $table->unique(['conversation_id', 'client_key'], 'idx_messages_conversation_client_key');
        });

        $noSnapshot = 'announced_sales_status IS NULL AND announced_payment_status IS NULL AND announced_process_status IS NULL';
        $noScheduleSnapshot = 'schedule_revision IS NULL AND announced_arrival_at IS NULL AND announced_reason IS NULL';

        DB::statement("
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND system_event IS NULL AND system_user_id IS NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot})
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND announced_status IS NOT NULL
                    AND announced_status IN ('pending','cancelled','closed','negotiation','site_visited','on_hold','quoted','revision','won','order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','delayed','for_refund','refunded')
                    AND {$noScheduleSnapshot})
                OR (type = 'schedule_card' AND body IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL AND inquiry_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND {$noSnapshot}
                    AND announced_status IN ('pending','confirmed','rescheduled','served','cancelled','expired')
                    AND schedule_revision IS NOT NULL AND announced_arrival_at IS NOT NULL)
                OR (type = 'system' AND body IS NULL AND system_event IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND {$noSnapshot} AND {$noScheduleSnapshot})
            )
        ");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_system_event CHECK (system_event IS NULL OR system_event IN ('assigned','released','reassigned'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_announced_sales_status CHECK (announced_sales_status IN ('negotiation','site_visited','on_hold','quoted','revision','won','rejected','for_refund'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_announced_payment_status CHECK (announced_payment_status IN ('awaiting','partial','fully_paid','partially_refunded','refunded'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_announced_process_status CHECK (announced_process_status IN ('order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','completed','on_hold','delayed'))");
        DB::statement("
            CREATE UNIQUE INDEX idx_messages_inquiry_card_once ON messages (
                conversation_id, inquiry_id, announced_status,
                (COALESCE(announced_sales_status, '')), (COALESCE(announced_payment_status, '')), (COALESCE(announced_process_status, ''))
            ) WHERE type = 'inquiry_card'
        ");
        DB::statement("CREATE UNIQUE INDEX idx_messages_schedule_card_once ON messages (conversation_id, schedule_id, announced_status, schedule_revision) WHERE type = 'schedule_card'");

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('pool_notified_message_id')->references('id')->on('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['pool_notified_message_id']);
        });

        Schema::dropIfExists('messages');
    }
};
