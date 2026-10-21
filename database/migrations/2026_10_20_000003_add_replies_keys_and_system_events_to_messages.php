<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replies, send deduplication and system events.
     *
     * - `reply_to_id` is the message being answered.
     * - `client_key` is the sender's own id for a send, so a retried send lands
     *   once: unique per conversation.
     * - `system` messages record a claim, a release or a reassignment of a
     *   Regray Team thread, and `system_user_id` is who it was about.
     * - An inquiry card is posted once per conversation, inquiry and status, so
     *   a retried listener cannot post the same change twice.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->uuid('client_key')->nullable();
            $table->string('system_event', 30)->nullable();
            $table->foreignId('system_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['conversation_id', 'client_key'], 'idx_messages_conversation_client_key');
        });

        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');
        DB::statement("
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND system_event IS NULL AND system_user_id IS NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL)
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND announced_status IS NOT NULL AND schedule_id IS NULL AND system_event IS NULL AND system_user_id IS NULL)
                OR (type = 'schedule_card' AND body IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL AND inquiry_id IS NULL AND system_event IS NULL AND system_user_id IS NULL)
                OR (type = 'system' AND body IS NULL AND system_event IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL)
            )
        ");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT chk_messages_system_event CHECK (system_event IS NULL OR system_event IN ('assigned','released','reassigned'))");
        DB::statement("CREATE UNIQUE INDEX idx_messages_inquiry_card_once ON messages (conversation_id, inquiry_id, announced_status) WHERE type = 'inquiry_card'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_messages_inquiry_card_once');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_system_event');
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');

        DB::table('messages')->where('type', 'system')->delete();

        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('idx_messages_conversation_client_key');
            $table->dropConstrainedForeignId('system_user_id');
            $table->dropColumn('system_event');
            $table->dropColumn('client_key');
            $table->dropConstrainedForeignId('reply_to_id');
        });

        DB::statement("
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND inquiry_id IS NULL AND schedule_id IS NULL AND announced_status IS NULL)
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND schedule_id IS NULL AND announced_status IS NOT NULL)
                OR (type = 'schedule_card' AND body IS NULL AND inquiry_id IS NULL AND schedule_id IS NOT NULL AND announced_status IS NOT NULL)
            )
        ");
    }
};
