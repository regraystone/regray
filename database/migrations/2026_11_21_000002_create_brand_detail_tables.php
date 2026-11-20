<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a brand's page says about the house, set on the admin's Brands page,
 * one row per item and in the order the page shows them (`sort_order`, from 0):
 *
 * - `brand_facts`: a label and its value ("Made in" / "Treia, Italy"), up to 6;
 * - `brand_makes`: what it makes ("Kitchens"), up to 8, no two alike whatever
 *   their case;
 * - `brand_socials`: its links, one per network (SocialPlatform: facebook,
 *   twitter, x, instagram, tiktok), up to one each.
 *
 * The limits hold in the database too: `sort_order` is unique per brand and
 * below the limit, so a brand can't have more rows than that. The facts and
 * what it makes were hard-coded by slug in public-links.ts (BRAND_DETAILS),
 * and every brand's page showed Regray's own Facebook, Instagram and TikTok;
 * the six seeded brands are given those, copied in from
 * database/seeders/data/brands.php, where they now live.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->string('value', 120);
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['brand_id', 'sort_order'], 'idx_brand_facts_order');
        });

        Schema::create('brand_makes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['brand_id', 'sort_order'], 'idx_brand_makes_order');
        });

        Schema::create('brand_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('url', 255);
            $table->smallInteger('sort_order');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique(['brand_id', 'sort_order'], 'idx_brand_socials_order');
            $table->unique(['brand_id', 'platform'], 'idx_brand_socials_platform');
        });

        DB::statement('ALTER TABLE brand_facts ADD CONSTRAINT brand_facts_sort_order_range CHECK (sort_order BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE brand_makes ADD CONSTRAINT brand_makes_sort_order_range CHECK (sort_order BETWEEN 0 AND 7)');
        DB::statement('CREATE UNIQUE INDEX idx_brand_makes_name ON brand_makes (brand_id, lower(name))');
        DB::statement('ALTER TABLE brand_socials ADD CONSTRAINT brand_socials_sort_order_range CHECK (sort_order BETWEEN 0 AND 4)');
        DB::statement("ALTER TABLE brand_socials ADD CONSTRAINT brand_socials_platform_check CHECK (platform IN ('facebook', 'twitter', 'x', 'instagram', 'tiktok'))");

        /** @var list<array{slug: string, facts?: list<array{label: string, value: string}>, makes?: list<string>, socials?: list<array{platform: string, url: string}>}> $brands */
        $brands = require database_path('seeders/data/brands.php');
        $now = now();

        foreach ($brands as $data) {
            $brandId = DB::table('brands')->where('slug', $data['slug'])->value('id');

            if ($brandId === null) {
                continue;
            }

            $row = fn (int $order, array $columns): array => ['brand_id' => $brandId, ...$columns, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now];

            DB::table('brand_facts')->insert(array_map($row, array_keys($data['facts'] ?? []), $data['facts'] ?? []));
            DB::table('brand_makes')->insert(array_map(fn (int $order, string $name): array => $row($order, ['name' => $name]), array_keys($data['makes'] ?? []), $data['makes'] ?? []));
            DB::table('brand_socials')->insert(array_map($row, array_keys($data['socials'] ?? []), $data['socials'] ?? []));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_socials');
        Schema::dropIfExists('brand_makes');
        Schema::dropIfExists('brand_facts');
    }
};
