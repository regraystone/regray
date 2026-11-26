<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Inquire page's table questions (Table shape, Table base design) are
 * answered by picture, so a question's listed choices can now carry more than
 * a name. The Choices page under Services maintains them.
 *
 * `detail_field_options`:
 * - `description`: a short line shown once the choice is picked, as a
 *   service's description is.
 * - `thumbnail_path`: its picture, on the public disk under
 *   question-choices/ (seeded or uploaded alike). A question whose
 *   choices have pictures is asked with picture cards rather than a dropdown.
 * - `is_custom`: "Custom shape" and the like. Picking it asks the client to
 *   describe it, and the answer keeps that description in `text_value`
 *   beside the option. At most one per question.
 * - `is_advisory`: "Not sure yet, advise me", as edge profiles and finishes
 *   have. Never listed on a furniture page. At most one per question.
 * - `size_kind`: for a table shape, how its size is asked: `diameter` (a
 *   round table) or `width_length`; NULL asks for it in the client's own
 *   words (an organic or custom shape).
 * - `archived_at`: taken off the Choices page. The row stays, as sent
 *   answers reference it; archiving also switches it off.
 *
 * `detail_field_option_service`: the services a choice is offered for. A
 * choice with no rows here is offered for every service that asks its
 * question, so Table base design lists only the bases suited to a center,
 * side or dining table while Custom and Not sure are offered for all three.
 *
 * `edge_profiles.description` and `finishes.description`: the line the Inquire
 * page shows under a stone row's edge profile or finish once it is picked.
 *
 * `inquiry_detail_answers` keeps exactly one value per answer, except a custom
 * choice, which keeps its option and the client's description of it.
 *
 * `furniture.table_shape_option_id` and `table_base_design_option_id`: a
 * table's own shape and base design, set on the Products page, which its
 * page's Inquire button picks for the client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_field_options', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_advisory')->default(false);
            $table->string('size_kind', 20)->nullable();
            $table->timestampTz('archived_at')->nullable();
        });

        DB::statement("ALTER TABLE detail_field_options ADD CONSTRAINT chk_detail_field_options_size_kind CHECK (size_kind IN ('diameter','width_length'))");
        // A suggestion is a sample value, never a custom or advisory choice; and a choice is one kind at a time.
        DB::statement('ALTER TABLE detail_field_options ADD CONSTRAINT chk_detail_field_options_kind CHECK (
            NOT (is_suggestion AND (is_custom OR is_advisory)) AND NOT (is_custom AND is_advisory) AND NOT (links_to_stone AND (is_custom OR is_advisory))
        )');
        DB::statement('CREATE UNIQUE INDEX idx_detail_field_options_custom ON detail_field_options (detail_field_id) WHERE is_custom AND archived_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX idx_detail_field_options_advisory ON detail_field_options (detail_field_id) WHERE is_advisory AND archived_at IS NULL');

        Schema::create('detail_field_option_service', function (Blueprint $table) {
            $table->foreignId('detail_field_option_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['detail_field_option_id', 'service_id']);
            $table->index('service_id');
        });

        Schema::table('edge_profiles', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
        });

        Schema::table('finishes', function (Blueprint $table) {
            $table->string('description', 255)->nullable();
        });

        Schema::table('furniture', function (Blueprint $table) {
            $table->foreignId('table_shape_option_id')->nullable()->constrained('detail_field_options')->nullOnDelete();
            $table->foreignId('table_base_design_option_id')->nullable()->constrained('detail_field_options')->nullOnDelete();
        });

        DB::statement('ALTER TABLE inquiry_detail_answers DROP CONSTRAINT chk_inquiry_detail_answers_one_value');
        DB::statement('ALTER TABLE inquiry_detail_answers ADD CONSTRAINT chk_inquiry_detail_answers_one_value CHECK (
            num_nonnulls(text_value, number_value, option_id, boolean_value) = 1
            OR (option_id IS NOT NULL AND text_value IS NOT NULL AND number_value IS NULL AND boolean_value IS NULL)
        )');
    }

    public function down(): void
    {
        // A custom choice's description goes with the column's second use.
        DB::statement('UPDATE inquiry_detail_answers SET text_value = NULL WHERE option_id IS NOT NULL');
        DB::statement('ALTER TABLE inquiry_detail_answers DROP CONSTRAINT chk_inquiry_detail_answers_one_value');
        DB::statement('ALTER TABLE inquiry_detail_answers ADD CONSTRAINT chk_inquiry_detail_answers_one_value CHECK (num_nonnulls(text_value, number_value, option_id, boolean_value) = 1)');

        Schema::table('furniture', function (Blueprint $table) {
            $table->dropConstrainedForeignId('table_base_design_option_id');
            $table->dropConstrainedForeignId('table_shape_option_id');
        });

        Schema::table('finishes', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('edge_profiles', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::dropIfExists('detail_field_option_service');

        DB::statement('DROP INDEX IF EXISTS idx_detail_field_options_advisory');
        DB::statement('DROP INDEX IF EXISTS idx_detail_field_options_custom');
        DB::statement('ALTER TABLE detail_field_options DROP CONSTRAINT chk_detail_field_options_kind');
        DB::statement('ALTER TABLE detail_field_options DROP CONSTRAINT chk_detail_field_options_size_kind');

        Schema::table('detail_field_options', function (Blueprint $table) {
            $table->dropColumn(['description', 'thumbnail_path', 'is_custom', 'is_advisory', 'size_kind', 'archived_at']);
        });
    }
};
