<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->smallInteger('sort_order');
            $table->string('location', 255);
            $table->string('landmark', 150)->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->date('deadline_date')->nullable();
            $table->time('deadline_time')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_id', 'sort_order']);
            $table->index('deadline_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_projects');
    }
};
