<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->timestampTz('created_at');

            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_attachments');
    }
};
