<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every status an inquiry's roles have been at, in order, one track per role:
 * `sales`, `payment` and `process`. Each track begins, from nothing, when its
 * stage starts: sales at negotiation when the inquiry's lead is won, payment
 * at awaiting and process at received when the client approves the quotation.
 * A new inquiry has no entry until then. Marketing's status is the lead's, and
 * its history is `lead_status_history`.
 *
 * The status CHECKs are the union of every role's values. Which values belong
 * to which track is the app's rule, not the database's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiry_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->string('track', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestampTz('changed_at');

            $table->index(['inquiry_id', 'track', 'changed_at']);
        });

        $statuses = "'negotiation','site_visited','on_hold','quoted','revision','won','rejected','for_refund','awaiting','partial','fully_paid','partially_refunded','refunded','order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','completed','delayed'";

        DB::statement("ALTER TABLE inquiry_status_history ADD CONSTRAINT chk_inquiry_status_history_track CHECK (track IN ('sales','payment','process'))");
        DB::statement("ALTER TABLE inquiry_status_history ADD CONSTRAINT chk_inquiry_status_history_to_status CHECK (to_status IN ({$statuses}))");
        DB::statement("ALTER TABLE inquiry_status_history ADD CONSTRAINT chk_inquiry_status_history_from_status CHECK (from_status IS NULL OR from_status IN ({$statuses}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_status_history');
    }
};
