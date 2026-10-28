<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Direct chats, groups and Regray Team (`support`) threads.
 *
 * - `inquiry_id` binds a group to one inquiry: it is that inquiry's group chat,
 *   and the inquiry's status cards post into it. An inquiry has at most one
 *   (`idx_conversations_inquiry_group`), and an inquiry with a group chat
 *   cannot be deleted.
 * - `support_client_id` says whose Regray Team thread it is and is unique, so a
 *   client can never have two.
 * - `assigned_to` and `assigned_at` say who has claimed a support thread, and
 *   are set together or not at all. A deleted staff member hands the thread
 *   back to the pool rather than blocking the delete: the foreign key nulls
 *   `assigned_to`, and the trigger below nulls `assigned_at` with it, so the
 *   pairing rule still holds afterwards.
 * - `pool_notified_message_id`: on a Regray Team thread nobody has claimed, the
 *   newest client message the support pool has been emailed about. Only ever
 *   moved forward. `messages` points back at this table, so this column's
 *   foreign key is added by the messages migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->string('name', 150)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->foreignId('inquiry_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('direct_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('support_client_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable();
            $table->foreignId('pool_notified_message_id')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('assigned_to');
        });

        DB::statement("CREATE UNIQUE INDEX idx_conversations_inquiry_group ON conversations (inquiry_id) WHERE type = 'group'");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_type CHECK (type IN ('direct','group','support'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_direct_key CHECK ((type = 'direct') = (direct_key IS NOT NULL))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_inquiry CHECK (inquiry_id IS NULL OR type = 'group')");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_support_client CHECK ((type = 'support') = (support_client_id IS NOT NULL))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_assigned_support CHECK (assigned_to IS NULL OR type = 'support')");
        DB::statement('ALTER TABLE conversations ADD CONSTRAINT chk_conversations_assigned_pair CHECK ((assigned_to IS NULL) = (assigned_at IS NULL))');

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION conversations_clear_assigned_at() RETURNS trigger AS $$
            BEGIN
                IF NEW.assigned_to IS NULL THEN
                    NEW.assigned_at := NULL;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_conversations_clear_assigned_at
                BEFORE UPDATE OF assigned_to ON conversations
                FOR EACH ROW EXECUTE FUNCTION conversations_clear_assigned_at();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
        DB::unprepared('DROP FUNCTION IF EXISTS conversations_clear_assigned_at()');
    }
};
