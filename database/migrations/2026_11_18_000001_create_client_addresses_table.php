<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A client's address book (Profile → Addresses): up to five places, one of
 * them the default the Inquire page starts from.
 *
 * Every level is a PSGC code (`ph_*`), with its name as it was picked, so a
 * card reads without a join: the region, the province (NULL where the region
 * has none, as in NCR), the city or municipality and the barangay, the postal
 * code (NULL only for a city with none listed) and the map pin. `label` is the
 * client's own name for it ("Home", "Office", "Site").
 *
 * One default at most per account (`idx_client_addresses_one_default`); the
 * app keeps exactly one while the account has any (ClientAddressService). At
 * most five per account is the app's rule too, checked under the account's
 * row lock. The rows go with the account.
 *
 * An inquiry never points here: it copies the address when it is sent, so an
 * edit or a delete in the book leaves sent inquiries as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50);
            $table->string('region_code', 9);
            $table->string('region_name', 100);
            $table->string('province_code', 9)->nullable();
            $table->string('province_name', 100)->nullable();
            $table->string('city_code', 9);
            $table->string('city_name', 100);
            $table->string('barangay_code', 9);
            $table->string('barangay_name', 100);
            $table->string('postal_code', 4)->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('user_id');
            $table->foreign('region_code')->references('code')->on('ph_regions')->restrictOnDelete();
            $table->foreign('province_code')->references('code')->on('ph_provinces')->restrictOnDelete();
            $table->foreign('city_code')->references('code')->on('ph_cities')->restrictOnDelete();
            $table->foreign('barangay_code')->references('code')->on('ph_barangays')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX idx_client_addresses_one_default ON client_addresses (user_id) WHERE is_default');
        DB::statement('ALTER TABLE client_addresses ADD CONSTRAINT chk_client_addresses_label CHECK (char_length(btrim(label)) > 0)');
        DB::statement('ALTER TABLE client_addresses ADD CONSTRAINT chk_client_addresses_province CHECK ((province_code IS NULL) = (province_name IS NULL))');
        DB::statement('ALTER TABLE client_addresses ADD CONSTRAINT chk_client_addresses_pin CHECK ((lat IS NULL) = (lng IS NULL))');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
