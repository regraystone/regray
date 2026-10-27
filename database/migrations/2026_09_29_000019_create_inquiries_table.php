<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One inquiry is one project: what the client asked for and where, and the
 * four role statuses it moves through. A role's status exists only once its
 * stage has started; until then it is NULL.
 *
 * - `marketing_status`: from creation. Pending until marketing has engaged the
 *   client (won) or dropped the inquiry (lost).
 * - `sales_status`: from marketing won, starting at negotiation. Negotiation,
 *   site_visited and on_hold while sales talks with the client; quoted once
 *   the client has approved the quotation; rejected when the client turns it
 *   down or cancels.
 * - `payment_status` and `process_status`: both from the client's approval,
 *   at awaiting and received. Accounting's is set by hand as payments come in;
 *   logistics' moves past received only once the job order is in.
 *
 * The status shown for the inquiry is calculated from them, never stored
 * (App\Services\InquiryStatusCalculator). Each CHECK holds one column to its
 * own values, and passes a NULL; how the statuses move together (payment and
 * process starting together, for one) is the app's rule, not the database's.
 *
 * `quote_revision` counts the quotations issued and `quoted_total` is the
 * latest one's total. When the project is wanted is not stored here: it is the
 * `arrival_at` of the inquiry's schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS inquiry_reference_seq START 1');

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('location', 255);
            $table->string('landmark', 150)->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('marketing_status', 20)->default('pending');
            $table->timestampTz('marketing_status_changed_at');
            $table->string('sales_status', 20)->nullable();
            $table->timestampTz('sales_status_changed_at')->nullable();
            $table->string('payment_status', 20)->nullable();
            $table->timestampTz('payment_status_changed_at')->nullable();
            $table->string('process_status', 20)->nullable();
            $table->timestampTz('process_status_changed_at')->nullable();
            $table->decimal('quoted_total', 12, 2)->nullable();
            $table->smallInteger('quote_revision')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('client_id');
            $table->index('marketing_status');
            $table->index('sales_status');
            $table->index('payment_status');
            $table->index('process_status');
        });

        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_marketing_status CHECK (marketing_status IN ('pending','won','lost'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_sales_status CHECK (sales_status IN ('negotiation','site_visited','on_hold','quoted','rejected'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_payment_status CHECK (payment_status IN ('awaiting','partial','fully_paid'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_process_status CHECK (process_status IN ('received','measure','sourcing','production','quality_check','ready','dispatched','installation','completed','on_hold','delayed'))");
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_quoted_total CHECK (quoted_total > 0)');
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_quote_revision CHECK (quote_revision >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        DB::statement('DROP SEQUENCE IF EXISTS inquiry_reference_seq');
    }
};
