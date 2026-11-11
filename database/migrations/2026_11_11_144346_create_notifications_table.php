<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace bell: one row per notification a user has been sent.
 *
 * - `type` is what the notification is about (NotificationType): a message, an
 *   inquiry or an appointment. It says what `target_id` is: a conversation, an
 *   inquiry or a schedule. Not a foreign key, so a notification outlives what
 *   it points to; the bell then says the item is no longer available.
 * - `body` is the line shown, written in English when the notification is sent.
 * - Rows older than 30 days are pruned (UserNotification::prunable()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('body', 255);
            $table->unsignedBigInteger('target_id');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'id']);
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notifications_type CHECK (type IN ('message','inquiry','schedule'))");
        DB::statement('CREATE INDEX notifications_unread_index ON notifications (user_id) WHERE read_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
