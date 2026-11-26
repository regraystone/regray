<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Questions page under Services archives a question no service asks. An
 * archived one leaves the page and can no longer be chosen for a service, but
 * its row stays: sent inquiries' answers reference it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_fields', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('detail_fields', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
