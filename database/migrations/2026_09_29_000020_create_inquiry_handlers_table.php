<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_handlers', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->primary()->cascadeOnDelete();
            $table->foreignId('user_id')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->setOnDelete('set null');
            $table->timestampTz('assigned_at');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_handlers');
    }
};
