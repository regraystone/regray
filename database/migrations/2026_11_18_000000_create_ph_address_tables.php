<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Philippines' places, from the PSGC (Philippine Standard Geographic
 * Code), so an address is picked from them rather than typed: every level is
 * a PSGC code. PhAddressSeeder fills them from database/seeders/data/psgc.
 *
 * - `ph_regions`: the 17 regions. `designation` is the official one ("Region
 *   IV-A", "NCR").
 * - `ph_provinces`: each under its region.
 * - `ph_cities`: the cities and municipalities. `province_code` is NULL where
 *   the city is in no province: the National Capital Region has none, and two
 *   independent cities elsewhere (Isabela City, Cotabato City) sit in none.
 *   Highly urbanized cities with a province around them are listed under it.
 * - `ph_barangays`: each under its city or municipality. About 42,000, so
 *   they are only ever read for one city, never sent whole.
 * - `ph_postal_codes`: the PHLPost ZIP codes each city or municipality is
 *   served by; a city can have several, and a few have none listed.
 *
 * A name search folds case and accents (`unaccent` is not assumed, so the
 * search lowers the name and the seeder stores a folded copy in `search_name`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ph_regions', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('name', 100);
            $table->string('designation', 100);
            $table->string('search_name', 100);
        });

        Schema::create('ph_provinces', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('name', 100);
            $table->string('search_name', 100);
            $table->string('region_code', 9);

            $table->foreign('region_code')->references('code')->on('ph_regions')->restrictOnDelete();
            $table->index(['region_code', 'search_name']);
        });

        Schema::create('ph_cities', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('name', 100);
            $table->string('search_name', 100);
            $table->string('region_code', 9);
            $table->string('province_code', 9)->nullable();
            $table->boolean('is_city');

            $table->foreign('region_code')->references('code')->on('ph_regions')->restrictOnDelete();
            $table->foreign('province_code')->references('code')->on('ph_provinces')->restrictOnDelete();
            $table->index(['province_code', 'search_name']);
            $table->index(['region_code', 'search_name']);
        });

        Schema::create('ph_barangays', function (Blueprint $table) {
            $table->string('code', 9)->primary();
            $table->string('name', 100);
            $table->string('search_name', 100);
            $table->string('city_code', 9);

            $table->foreign('city_code')->references('code')->on('ph_cities')->restrictOnDelete();
            $table->index(['city_code', 'search_name']);
        });

        Schema::create('ph_postal_codes', function (Blueprint $table) {
            $table->string('postal_code', 4);
            $table->string('city_code', 9);

            $table->primary(['city_code', 'postal_code']);
            $table->foreign('city_code')->references('code')->on('ph_cities')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE ph_postal_codes ADD CONSTRAINT chk_ph_postal_codes_format CHECK (postal_code ~ '^[0-9]{4}$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('ph_postal_codes');
        Schema::dropIfExists('ph_barangays');
        Schema::dropIfExists('ph_cities');
        Schema::dropIfExists('ph_provinces');
        Schema::dropIfExists('ph_regions');
    }
};
