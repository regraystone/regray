<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The kinds of work a client can ask for under each service: "Stone supply
     * only", "Stone supply with installation" and so on, picked from a
     * dropdown under the service on the inquiry form. ServiceSeeder fills it.
     */
    public function up(): void
    {
        Schema::create('service_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['service_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_options');
    }
};
