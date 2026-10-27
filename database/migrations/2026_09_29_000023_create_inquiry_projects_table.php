<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The projects an inquiry is broken into. Each has its own status: its
 * quotations, job orders and payments hang off it, and the inquiry's status is
 * read from its projects'.
 */
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
            // The schedule that holds the project's target deadline.
            $table->foreignId('schedule_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->string('status', 20);
            $table->timestampTz('status_changed_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['inquiry_id', 'sort_order']);
            $table->index(['inquiry_id', 'status']);
        });

        DB::statement("ALTER TABLE inquiry_projects ADD CONSTRAINT chk_inquiry_projects_status CHECK (status IN ('pending','ongoing','closed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_projects');
    }
};
