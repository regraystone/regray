<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the unread-message emails have got to, so nobody is told about the
 * same message twice.
 *
 * - `conversation_members.last_notified_message_id`: the newest message this
 *   member has been emailed about in this conversation.
 * - `conversations.pool_notified_message_id`: on a Regray Team thread nobody
 *   has claimed, the newest client message the support pool has been emailed
 *   about. The pool are not members, so they have no row of their own.
 *
 * Both are only ever moved forward, through the query builder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->foreignId('last_notified_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('pool_notified_message_id')->nullable()->constrained('messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pool_notified_message_id');
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_notified_message_id');
        });
    }
};
