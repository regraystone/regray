<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A message notification waiting to be sent: one recipient, one thread.
 *
 * - `message_id` is the first message the recipient has not seen, which
 *   started the timer; `due_at` is when the notification and its email go.
 * - `sent_at` is set once they went. `closed_at` is set when the recipient
 *   opens the thread: a row closed before it was sent is cancelled, and is
 *   never sent.
 * - At most one open row per recipient and thread (the partial unique index):
 *   later messages add nothing until the recipient opens the thread, sent or
 *   not. The next message after that starts a new row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('due_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampTz('created_at');

            $table->index('conversation_id');
            $table->index('created_at');
        });

        DB::statement('CREATE UNIQUE INDEX pending_notifications_open_unique ON pending_notifications (user_id, conversation_id) WHERE closed_at IS NULL');
        DB::statement('CREATE INDEX pending_notifications_due_index ON pending_notifications (due_at) WHERE sent_at IS NULL AND closed_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_notifications');
    }
};
