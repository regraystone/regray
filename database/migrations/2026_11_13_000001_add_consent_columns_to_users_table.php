<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Where a user stands now: which terms they agreed to and when, and whether
 * they want promotional emails. Every change is also written to
 * `user_consents`, which is the record; these columns are what a query reads.
 *
 * All nullable: an account made before this, or made by staff (a walk-in
 * client, a staff member), has agreed to nothing through the sign-up form.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('terms_accepted_at')->nullable();
            $table->string('terms_version', 30)->nullable();
            $table->timestampTz('marketing_emails_opted_in_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version', 'marketing_emails_opted_in_at']);
        });
    }
};
