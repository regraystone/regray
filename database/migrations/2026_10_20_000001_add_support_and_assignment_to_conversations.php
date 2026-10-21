<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Regray Team thread: one `support` conversation per client, held by a
     * pool of marketing staff until one of them claims it.
     *
     * `support_client_id` says whose thread it is and is unique, so a client can
     * never have two. `assigned_to` and `assigned_at` say who has claimed it, and
     * are set together or not at all. A deleted staff member hands the thread
     * back to the pool rather than blocking the delete: the foreign key nulls
     * `assigned_to`, and the trigger below nulls `assigned_at` with it, so the
     * pairing rule still holds afterwards.
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('support_client_id')->nullable()->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable();

            $table->index('assigned_to');
        });

        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_type');
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_type CHECK (type IN ('direct','group','support'))");
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
        DB::unprepared('DROP TRIGGER IF EXISTS trg_conversations_clear_assigned_at ON conversations');
        DB::unprepared('DROP FUNCTION IF EXISTS conversations_clear_assigned_at()');

        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_assigned_pair');
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_assigned_support');
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_support_client');
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT IF EXISTS chk_conversations_type');

        DB::table('conversations')->where('type', 'support')->delete();

        DB::statement("ALTER TABLE conversations ADD CONSTRAINT chk_conversations_type CHECK (type IN ('direct','group'))");

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['assigned_to']);
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('assigned_at');
            $table->dropConstrainedForeignId('support_client_id');
        });
    }
};
