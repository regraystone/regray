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
 * - `service_id`: what the client asked for. The request requires it; a row
 *   written outside the form may have none.
 * - `delivery_method`: how the project reaches the client, asked only when its
 *   service is `delivery_or_pickup` (`services.fulfillment`): `delivery` or
 *   `pickup`. NULL for an `installed` service, which is done on site.
 * - `address_*`: where the work goes, copied when the inquiry is sent (or
 *   edited) so a later edit of the client's address book never changes it:
 *   the region, province (NULL where the region has none, as in NCR), city or
 *   municipality and barangay, each by its PSGC code and its name at the time,
 *   the postal code (NULL for a city with none listed) and the pin's
 *   coordinates. A delivery and an installation have one; a pickup has none.
 *   A CHECK holds the method and the address together; whether the service
 *   asks for a method at all is the request's rule (StoreInquiryRequest).
 * - `brand_no_preference`: the client chose No preference for brands. Their
 *   brands are otherwise rows of `inquiry_brands`; the request takes one or
 *   the other, never both.
 * - `lead_id`: marketing's lead for the client (`leads`). NULL until the
 *   client's Regray Team thread is claimed, which links it, and for a direct
 *   client, who has no lead.
 * - `created_by`: the staff member who opened the inquiry for the client,
 *   NULL when the client sent it. Marketing and sales read the inquiries they
 *   opened (Inquiry::scopeVisibleTo()).
 * - `owner_user_id`: the sales user who owns a walk-in inquiry: the sales
 *   member who opened it, or the one marketing named when it opened it. Set
 *   only on a walk-in (a client record, no account and no lead), by a CHECK;
 *   a trigger holds it to a sales user and refuses to change it once set.
 *   Two inquiries on one walk-in may have different owners. NULL on an
 *   inquiry for a client account, which marketing's lead stands for instead.
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
 *
 * `inquiry_detail_answers` holds the project-scope answers its service asks
 * (`service_detail_fields`), one row per question: only the column matching
 * the question's `answer_type` is set (`text_value`, `number_value`,
 * `option_id` for a closed option, `boolean_value`), which a CHECK holds to
 * exactly one. Stone-scope answers stay on `inquiry_stones`, since they vary
 * per stone row.
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
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Restrict, not set null: the owner never changes once set (trigger below).
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('marketing_status', 20)->nullable();
            $table->timestampTz('marketing_status_changed_at')->nullable();
            $table->string('name', 100);
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('brand_no_preference')->default(false);
            $table->string('delivery_method', 20)->nullable();
            $table->string('address_region_code', 9)->nullable();
            $table->string('address_region_name', 100)->nullable();
            $table->string('address_province_code', 9)->nullable();
            $table->string('address_province_name', 100)->nullable();
            $table->string('address_city_code', 9)->nullable();
            $table->string('address_city_name', 100)->nullable();
            $table->string('address_barangay_code', 9)->nullable();
            $table->string('address_barangay_name', 100)->nullable();
            $table->string('address_postal_code', 4)->nullable();
            $table->decimal('address_lat', 9, 6)->nullable();
            $table->decimal('address_lng', 9, 6)->nullable();
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
            $table->index('created_by');
            $table->index('owner_user_id');
            $table->index('service_id');
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
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_delivery_method CHECK (delivery_method IN ('delivery','pickup'))");
        // The address is whole or absent: its region, city and barangay come together, with their names.
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_address_whole CHECK (
            (address_region_code IS NULL AND address_region_name IS NULL AND address_province_code IS NULL AND address_province_name IS NULL
                AND address_city_code IS NULL AND address_city_name IS NULL AND address_barangay_code IS NULL AND address_barangay_name IS NULL
                AND address_postal_code IS NULL AND address_lat IS NULL AND address_lng IS NULL)
            OR (address_region_code IS NOT NULL AND address_region_name IS NOT NULL AND address_city_code IS NOT NULL AND address_city_name IS NOT NULL
                AND address_barangay_code IS NOT NULL AND address_barangay_name IS NOT NULL
                AND (address_province_code IS NULL) = (address_province_name IS NULL)
                AND (address_lat IS NULL) = (address_lng IS NULL))
        )');
        // A pickup has no address; a delivery, and an installation (no method), has one.
        DB::statement("ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_delivery_address CHECK ((delivery_method IS NOT DISTINCT FROM 'pickup') = (address_city_code IS NULL))");

        // An owner only on a walk-in: a client record with no account and no lead.
        DB::statement('ALTER TABLE inquiries ADD CONSTRAINT chk_inquiries_owner_walk_in CHECK (
            owner_user_id IS NULL OR (client_record_id IS NOT NULL AND client_id IS NULL AND lead_id IS NULL)
        )');

        /*
         * The owner is a sales user, and stays who it was once set. Roles are
         * Spatie's (`model_has_roles`, `roles`), which only users hold.
         */
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION inquiries_owner_guard() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'UPDATE' AND OLD.owner_user_id IS NOT NULL AND NEW.owner_user_id IS DISTINCT FROM OLD.owner_user_id THEN
                    RAISE EXCEPTION 'The owner of inquiry % cannot change once set.', OLD.reference USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.owner_user_id IS NOT NULL
                    AND (TG_OP = 'INSERT' OR OLD.owner_user_id IS NULL)
                    AND NOT EXISTS (
                        SELECT 1 FROM model_has_roles held JOIN roles role ON role.id = held.role_id
                        WHERE held.model_id = NEW.owner_user_id AND role.name = 'sales'
                    ) THEN
                    RAISE EXCEPTION 'The owner of an inquiry must be a sales user.' USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END
            $$
            SQL);
        DB::statement('CREATE TRIGGER trg_inquiries_owner BEFORE INSERT OR UPDATE OF owner_user_id ON inquiries FOR EACH ROW EXECUTE FUNCTION inquiries_owner_guard()');

        Schema::create('inquiry_detail_answers', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('detail_field_id')->constrained()->restrictOnDelete();
            $table->string('text_value', 255)->nullable();
            $table->decimal('number_value', 12, 2)->nullable();
            $table->foreignId('option_id')->nullable()->constrained('detail_field_options')->restrictOnDelete();
            $table->boolean('boolean_value')->nullable();

            $table->primary(['inquiry_id', 'detail_field_id']);
            $table->index('detail_field_id');
            $table->index('option_id');
        });

        DB::statement('ALTER TABLE inquiry_detail_answers ADD CONSTRAINT chk_inquiry_detail_answers_one_value CHECK (num_nonnulls(text_value, number_value, option_id, boolean_value) = 1)');

        // The brands the client would like, in no order; none with brand_no_preference.
        Schema::create('inquiry_brands', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();

            $table->primary(['inquiry_id', 'brand_id']);
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiry_brands');
        Schema::dropIfExists('inquiry_detail_answers');
        Schema::dropIfExists('inquiries');
        DB::statement('DROP FUNCTION IF EXISTS inquiries_owner_guard()');
        DB::statement('DROP SEQUENCE IF EXISTS inquiry_reference_seq');
    }
};
