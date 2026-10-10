<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_project_services', function (Blueprint $table) {
            $table->foreignId('inquiry_project_id')->cascadeOnDelete();
            $table->foreignId('service_id')->restrictOnDelete();

            $table->primary(['inquiry_project_id', 'service_id']);
            $table->index('service_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_project_services');
    }
};
