<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A member's role in a conversation, and how far it has been delivered.
     *
     * `viewer` is the pool's row on an unassigned Regray Team thread: it exists
     * only to hold a read pointer, and is deleted when the thread is claimed.
     * Access for the pool comes from the policy, never from this row.
     */
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->string('role', 10)->default('member');
            $table->foreignId('last_delivered_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });

        DB::statement("ALTER TABLE conversation_members ADD CONSTRAINT chk_conversation_members_role CHECK (role IN ('member','admin','viewer'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE conversation_members DROP CONSTRAINT IF EXISTS chk_conversation_members_role');

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_delivered_message_id');
            $table->dropColumn('role');
        });
    }
};
