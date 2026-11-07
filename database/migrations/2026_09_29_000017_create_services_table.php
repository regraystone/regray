<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a client can ask for, in two levels: the service types of the first
     * dropdown of "What do you need?" on the Inquire page, and the services of the
     * second, each with the description and features shown under it, both in
     * `sort_order`. One no longer offered is made inactive, because sent inquiries
     * reference it. ServiceTypeSeeder and ServiceSeeder fill them.
     */
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150)->unique();
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description');
            $table->json('features');
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['service_type_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_types');
    }
};
