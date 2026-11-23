<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin's Services page archives a service or a service type. An archived
 * one leaves the page and is no longer offered (archiving also sets
 * `is_active` false, which everything the Inquire page reads already checks),
 * but its row stays: sent inquiries reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
