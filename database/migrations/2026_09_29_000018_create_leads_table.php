<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing's record of one client. A lead is created when a marketing user is
 * given the client's Regray Team thread, and the client's inquiries point to it
 * (`inquiries.lead_id`).
 *
 * - `status`: idle until marketing has engaged the client (won) or dropped them
 *   (lost). Won and lost are final; a client who comes back after a lost lead
 *   gets a new lead.
 * - `marketing_user_id`: who holds it. Reassigning the client's thread moves an
 *   idle lead to the new holder.
 * - `idx_leads_client_open`: a client has at most one open (idle or won) lead,
 *   the one their new inquiries link to.
 *
 * Its status is never an inquiry status: the inquiry's display status reads it
 * (a lost lead shows its inquiries as cancelled), but it is not stored there.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS lead_reference_seq START 1');

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('marketing_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('idle');
            $table->timestampTz('status_changed_at');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('status');
            $table->index('marketing_user_id');
            $table->index('client_id');
        });

        DB::statement("ALTER TABLE leads ADD CONSTRAINT chk_leads_status CHECK (status IN ('idle','won','lost'))");
        DB::statement("CREATE UNIQUE INDEX idx_leads_client_open ON leads (client_id) WHERE status IN ('idle','won')");
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
        DB::statement('DROP SEQUENCE IF EXISTS lead_reference_seq');
    }
};
