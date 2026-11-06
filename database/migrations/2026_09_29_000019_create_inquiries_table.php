<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One inquiry is one project: what the client asked for and where, the lead it
 * belongs to, and the three role statuses it moves through. A role's status
 * exists only once its stage has started; until then it is NULL.
 *
 * - `client_id`: the client's account. NULL for a direct (walk-in) client,
 *   who has none; `client_record_id` is the client either way (`clients`).
 * - `delivery_method`: how the project reaches the client. A pickup has no site
 *   to name, so its `location` is NULL, and a CHECK holds the two facts together.
 *   A landmark is optional on either.
 * - `lead_id`: marketing's lead for the client (`leads`). NULL until the
 *   client's Regray Team thread is claimed, which links it, and for a direct
 *   client, who has no lead.
 * - `marketing_status`: marketing's decision on this inquiry, won or lost,
 *   NULL while it waits for one. Won starts its sales; lost shows it
 *   cancelled. The lead's own status is the tally of its inquiries'.
 * - `sales_status`: from marketing winning the inquiry, starting at
 *   negotiation. Negotiation, site_visited and on_hold while sales talks with
 *   the client; quoted once the quotation is sent, and revision for each
 *   revised one; won once the client has approved it; rejected when the client
 *   turns it down or cancels before that; for_refund when, after approving,
 *   the client asks for their money back.
 * - `payment_status`: from the client's approval (won), set by hand as
 *   payments come in, and, once sales is for_refund, as refunds go out
 *   (partially_refunded, then refunded).
 * - `process_status`: from the first payment on an inquiry with its job
 *   order.
 *
 * The status shown for the inquiry is calculated from them, never stored
 * (App\Services\InquiryStatusCalculator). Each CHECK holds one column to its
 * own values, and passes a NULL; how the statuses move together (payment and
 * process starting together, for one) is the app's rule, not the database's.
 *
 * `quote_revision` counts the quotations issued and `quoted_total` is the
 * latest one's total. `contract_total` is the total contract amount accounting
 * bills when payment moves to awaiting, NULL until then; payments are counted
 * against it. When the project is wanted is not stored here: it is the
 * `arrival_at` of the inquiry's schedule. `completed_at` is when logistics
 * moved to `completed` (final), set once and NULL until then; the Inquiry
 * list's Due column reads it against the deadline.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS inquiry_reference_seq START 1');

        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('client_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('client_record_id')->nullable()->constrained('clients')->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('marketing_status', 20)->nullable();
            $table->timestampTz('marketing_status_changed_at')->nullable();
            $table->string('name', 100);
            $table->string('delivery_method', 20)->default('delivery');
            $table->string('location', 255)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->decimal('area_sqm', 10, 2)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->boolean('stone_advice_requested')->default(false);
            $table->string('sales_status', 20)->nullable();
            $table->timestampTz('sales_status_changed_at')->nullable();
            $table->string('payment_status', 20)->nullable();
            $table->timestampTz('payment_status_changed_at')->nullable();
            $table->string('process_status', 20)->nullable();
            $table->timestampTz('process_status_changed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->decimal('quoted_total', 12, 2)->nullable();
            $table->smallInteger('quote_revision')->nullable();
            $table->decimal('contract_total', 12, 2)->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('client_id');
            $table->index('client_record_id');
            $table->index('lead_id');
            $table->index('sales_status');
            $table->index('payment_status');
            $table->index('process_status');
        });

        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_sales_status CHECK (sales_status IN ('negotiation','site_visited','on_hold','quoted','revision','won','rejected','for_refund'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_payment_status CHECK (payment_status IN ('awaiting','partial','fully_paid','partially_refunded','refunded'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_process_status CHECK (process_status IN ('order_received','measure','sourcing','production','quality_check','ready','dispatched','installation','completed','on_hold','delayed'))");
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_quoted_total CHECK (quoted_total > 0)');
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_quote_revision CHECK (quote_revision >= 1)');
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_contract_total CHECK (contract_total > 0)');
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_marketing_status CHECK (marketing_status IN ('won','lost'))");
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_delivery_location CHECK ((delivery_method = 'delivery' AND location IS NOT NULL) OR (delivery_method = 'pickup' AND location IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
        DB::statement('DROP SEQUENCE IF EXISTS inquiry_reference_seq');
    }
};
