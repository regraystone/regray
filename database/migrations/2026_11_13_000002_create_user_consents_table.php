<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row each time a user agrees to or withdraws a consent: the terms at
 * sign-up, promotional emails at sign-up and on every change after. Rows are
 * only ever added, so the table answers what a user had agreed to on any date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->boolean('granted');
            // The terms' version for a terms consent; null for emails.
            $table->string('version', 30)->nullable();
            $table->string('source', 30);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('created_at');

            $table->index(['user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
