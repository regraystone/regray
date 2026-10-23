<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who is in a conversation, their role, and how far they have read, been
 * delivered and been emailed.
 *
 * - `viewer` is the pool's row on an unassigned Regray Team thread: it exists
 *   only to hold a read pointer, and is deleted when the thread is claimed.
 *   Access for the pool comes from the policy, never from this row.
 * - `last_notified_message_id` is the newest message this member has been
 *   emailed about in this conversation.
 * - The message pointers only ever move forward, through the query builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member');
            $table->timestampTz('joined_at');
            $table->foreignId('last_read_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('last_delivered_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('last_notified_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestampTz('muted_at')->nullable();

            $table->primary(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE conversation_members ADD CONSTRAINT chk_conversation_members_role CHECK (role IN ('member','admin','viewer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
    }
};
