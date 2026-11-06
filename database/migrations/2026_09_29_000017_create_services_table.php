<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a client can ask for: the list "What do you need?" on the Inquire page,
     * in `sort_order`. `needs_furniture` is the service that also asks for a
     * furniture type. One no longer offered is made inactive, because sent
     * inquiries reference it. ServiceSeeder fills it.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150)->unique();
            $table->boolean('needs_furniture')->default(false);
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
