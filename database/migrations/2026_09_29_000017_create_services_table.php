<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a client can ask for, in two levels: the service types of the first
     * dropdown of "What do you need?" on the Inquire page, and the services of the
     * second, each with the description and features shown under it, both in
     * `sort_order`. One no longer offered is made inactive, because sent inquiries
     * reference it. ServiceTypeSeeder and ServiceSeeder fill them.
     *
     * `requires_stones` is false for a service that asks for no stones
     * (Installation Only): the Inquire page skips the stones question for it.
     *
     * What else a service asks is data, not code: `detail_fields` is the
     * catalog of questions (scope `stone`, answered on each stone row, or
     * `project`, answered once under "Other details"), `detail_field_options`
     * the listed answers of the ones that have their own (a closed option the
     * client must pick, or a suggestion shown beside a free entry), and
     * `service_detail_fields` which questions each service asks, in order.
     * Edge profile and finish keep their own tables (`edge_profiles`,
     * `finishes`); DetailFieldSeeder fills the catalog.
     */
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150)->unique();
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->text('description');
            $table->boolean('requires_stones')->default(true);
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['service_type_id', 'is_active', 'sort_order']);
        });

        Schema::create('service_features', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('sort_order');
            $table->string('text', 255);

            $table->primary(['service_id', 'sort_order']);
        });

        Schema::create('detail_fields', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('scope', 10);
            $table->string('answer_type', 10);
            $table->boolean('allows_custom')->default(false);
            $table->smallInteger('sort_order');
        });

        DB::statement("ALTER TABLE detail_fields ADD CONSTRAINT chk_detail_fields_scope CHECK (scope IN ('stone','project'))");
        DB::statement("ALTER TABLE detail_fields ADD CONSTRAINT chk_detail_fields_answer_type CHECK (answer_type IN ('text','number','choice','boolean'))");

        Schema::create('detail_field_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_field_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 100);
            $table->string('name', 150);
            $table->smallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_suggestion')->default(false);
            $table->boolean('links_to_stone')->default(false);

            $table->unique(['detail_field_id', 'slug']);
        });

        // "Same as stone" at most once per question.
        DB::statement('CREATE UNIQUE INDEX idx_detail_field_options_links_to_stone ON detail_field_options (detail_field_id) WHERE links_to_stone');

        Schema::create('service_detail_fields', function (Blueprint $table) {
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('detail_field_id')->constrained()->restrictOnDelete();
            $table->smallInteger('sort_order');
            $table->boolean('is_required')->default(true);

            $table->primary(['service_id', 'detail_field_id']);
            $table->index('detail_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_detail_fields');
        DB::statement('DROP INDEX IF EXISTS idx_detail_field_options_links_to_stone');
        Schema::dropIfExists('detail_field_options');
        Schema::dropIfExists('detail_fields');
        Schema::dropIfExists('service_features');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_types');
    }
};
