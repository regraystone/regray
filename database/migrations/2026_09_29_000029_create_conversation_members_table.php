<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_members', function (Blueprint $table) {
            $table->foreignId('conversation_id')->cascadeOnDelete();
            $table->foreignId('user_id')->cascadeOnDelete();
            $table->timestampTz('joined_at');
            $table->foreignId('last_read_message_id')->nullable()->setOnDelete('set null');
            $table->timestampTz('muted_at')->nullable();

            $table->primary(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_members');
    }
};
