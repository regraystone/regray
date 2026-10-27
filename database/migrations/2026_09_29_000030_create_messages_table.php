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
 * - An inquiry card is posted once per conversation, inquiry and status, so a
 *   retried listener cannot post the same change twice.
 * - A project card announces a snapshot of one project: its own status
 *   (`announced_status`), its quotation's and its payment's. It is posted
 *   once per conversation, project and snapshot, for the same reason.
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
            $table->foreignId('inquiry_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('announced_status', 20)->nullable();
            $table->string('announced_quotation_status', 20)->nullable();
            $table->string('announced_payment_status', 20)->nullable();
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
            $table->index('inquiry_project_id');
            $table->unique(['conversation_id', 'client_key'], 'idx_messages_conversation_client_key');
        });

        DB::statement("
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND system_event IS NULL AND system_user_id IS NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND inquiry_project_id IS NULL AND announced_quotation_status IS NULL AND announced_payment_status IS NULL)
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND announced_status IS NOT NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND inquiry_project_id IS NULL AND announced_quotation_status IS NULL AND announced_payment_status IS NULL)
                OR (type = 'schedule_card' AND body IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL AND inquiry_id IS NULL AND system_event IS NULL AND system_user_id IS NULL
                    AND inquiry_project_id IS NULL AND announced_quotation_status IS NULL AND announced_payment_status IS NULL)
                OR (type = 'project_card' AND body IS NULL AND inquiry_project_id IS NOT NULL
                    AND announced_status IS NOT NULL AND announced_status IN ('pending','ongoing','closed','cancelled')
                    AND announced_quotation_status IS NOT NULL AND announced_payment_status IS NOT NULL
                    AND inquiry_id IS NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL)
                OR (type = 'system' AND body IS NULL AND system_event IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL
                    AND inquiry_project_id IS NULL AND announced_quotation_status IS NULL AND announced_payment_status IS NULL)
            )
        ");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_system_event CHECK (system_event IS NULL OR system_event IN ('assigned','released','reassigned'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_announced_quotation_status CHECK (announced_quotation_status IS NULL OR announced_quotation_status IN ('pending','closed','revision','closeable','rejected'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_announced_payment_status CHECK (announced_payment_status IS NULL OR announced_payment_status IN ('awaiting','partial','fully_paid','refunded'))");
        DB::statement("CREATE UNIQUE INDEX idx_messages_inquiry_card_once ON messages (conversation_id, inquiry_id, announced_status) WHERE type = 'inquiry_card'");
        DB::statement("CREATE UNIQUE INDEX idx_messages_schedule_card_once ON messages (conversation_id, schedule_id, announced_status) WHERE type = 'schedule_card'");
        DB::statement("CREATE UNIQUE INDEX idx_messages_project_card_once ON messages (conversation_id, inquiry_project_id, announced_status, announced_quotation_status, announced_payment_status) WHERE type = 'project_card'");

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
