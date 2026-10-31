<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing's record of one client, kept for good. A lead is created when a
 * marketing user is given the client's Regray Team thread (or the client's
 * inquiry arrives while someone holds it), and every inquiry of the client
 * points to it (`inquiries.lead_id`).
 *
 * - `client_id`: unique, so a client has one lead.
 * - `marketing_user_id`: who holds it. It follows the client's thread: a claim
 *   or a reassignment moves the lead to the new holder.
 *
 * It has no status of its own. Marketing decides each inquiry won or lost
 * (`inquiries.marketing_status`), and the lead shows the tally of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS lead_reference_seq START 1');

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('client_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('marketing_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('marketing_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        DB::statement('DROP SEQUENCE IF EXISTS lead_reference_seq');
    }
};
