<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->cascadeOnDelete();
            $table->foreignId('sender_id')->nullable()->setOnDelete('set null');
            $table->string('type', 20);
            $table->text('body')->nullable();
            $table->foreignId('inquiry_id')->nullable()->setOnDelete('set null');
            $table->foreignId('agenda_id')->nullable()->setOnDelete('set null');
            $table->string('announced_status', 20)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('edited_at')->nullable();
            $table->softDeletesTz();

            $table->index(['conversation_id', 'id']);
            $table->index('inquiry_id');
            $table->index('agenda_id');
        });

        DB::statement("
            ALTER TABLE messages ADD CONSTRAINT chk_messages_type CHECK (
                (type = 'text' AND body IS NOT NULL AND inquiry_id IS NULL AND agenda_id IS NULL AND announced_status IS NULL)
                OR (type = 'inquiry_card' AND body IS NULL AND inquiry_id IS NOT NULL AND agenda_id IS NULL AND announced_status IS NOT NULL)
                OR (type = 'agenda_card' AND body IS NULL AND inquiry_id IS NULL AND agenda_id IS NOT NULL AND announced_status IS NOT NULL)
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS chk_messages_type');
        Schema::dropIfExists('messages');
    }
};
