<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_project_id')->cascadeOnDelete();
            $table->string('disk', 30);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size_bytes');
            $table->char('sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->setOnDelete('set null');
            $table->timestampTz('created_at');
            $table->softDeletesTz();

            $table->index('inquiry_project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_files');
    }
};
