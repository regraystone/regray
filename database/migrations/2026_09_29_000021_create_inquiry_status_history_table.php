<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->setOnDelete('set null');
            $table->string('note', 500)->nullable();
            $table->timestampTz('changed_at');

            $table->index(['inquiry_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_status_history');
    }
};
