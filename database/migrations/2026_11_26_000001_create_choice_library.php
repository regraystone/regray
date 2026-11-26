<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The choices of the list questions become one library (the Choices page
 * under Services) that a question picks from, so a choice is written once,
 * with its picture and description, and listed by as many questions as need
 * it ("Not sure yet, advise me", "Custom Design").
 *
 * `choices`: the library. Its name is unique, ignoring case, so the
 * Questions page's picker never shows two alike. It holds what the client
 * sees (name, description, picture, kind, how a table shape asks the size),
 * whether it is offered at all, and its place in the library.
 *
 * `choice_service`: the services it is offered for; none is every service
 * that asks a question listing it.
 *
 * `detail_field_options.choice_id`: a question's listing of a library choice.
 * The option keeps its own slug, order, per-question state (`archived_at`:
 * taken off the question) and the answers that chose it, and mirrors the
 * choice's name, description, picture, kind and services, so the Inquire
 * page and sent answers read options as before. A suggestion (a sample value
 * beside a free entry) is no choice and has none.
 *
 * Each existing choice joins the library: options of one name, ignoring case,
 * become one choice, which takes the first one's details (by its question's
 * order, then its own) and its services, and those options mirror it. Until
 * now a choice taken off its question was only switched off; such an option
 * is now marked taken off (`archived_at`), and a choice no question lists any
 * longer is archived with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choices', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->string('thumbnail_path', 255)->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('is_advisory')->default(false);
            $table->boolean('links_to_stone')->default(false);
            $table->string('size_kind', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->smallInteger('sort_order')->default(0);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('CREATE UNIQUE INDEX idx_choices_name ON choices (lower(name))');
        DB::statement("ALTER TABLE choices ADD CONSTRAINT chk_choices_size_kind CHECK (size_kind IN ('diameter','width_length'))");
        DB::statement('ALTER TABLE choices ADD CONSTRAINT chk_choices_kind CHECK (NOT (is_custom AND is_advisory) AND NOT (links_to_stone AND (is_custom OR is_advisory)))');

        Schema::create('choice_service', function (Blueprint $table) {
            $table->foreignId('choice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['choice_id', 'service_id']);
            $table->index('service_id');
        });

        Schema::table('detail_field_options', function (Blueprint $table) {
            $table->foreignId('choice_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('choice_id');
        });

        DB::statement('CREATE UNIQUE INDEX idx_detail_field_options_choice ON detail_field_options (detail_field_id, choice_id) WHERE choice_id IS NOT NULL');

        $this->fillLibrary();
    }

    public function down(): void
    {
        Schema::table('detail_field_options', function (Blueprint $table) {
            $table->dropConstrainedForeignId('choice_id');
        });

        Schema::dropIfExists('choice_service');
        Schema::dropIfExists('choices');
    }

    /** Every list question's choice, joined into the library by name. */
    private function fillLibrary(): void
    {
        $options = DB::table('detail_field_options')
            ->join('detail_fields', 'detail_fields.id', '=', 'detail_field_options.detail_field_id')
            ->where('detail_field_options.is_suggestion', false)
            ->where('detail_fields.answer_type', 'choice')
            ->whereNotIn('detail_fields.slug', ['edge_profile', 'finish'])
            ->orderBy('detail_fields.sort_order')
            ->orderBy('detail_field_options.sort_order')
            ->orderBy('detail_field_options.id')
            ->get(['detail_field_options.*', 'detail_fields.slug as field_slug']);

        $slugs = [];
        $sort = 0;
        $listed = fn (stdClass $option): bool => $option->is_active && $option->archived_at === null;

        foreach ($options->groupBy(fn (stdClass $option): string => mb_strtolower(trim($option->name))) as $alike) {
            $first = $alike->first($listed) ?? $alike->first();
            $base = Str::limit(Str::slug($first->name), 96, '') ?: 'choice';
            $slug = $base;

            for ($number = 2; in_array($slug, $slugs, true); $number++) {
                $slug = "{$base}-{$number}";
            }

            $slugs[] = $slug;
            $now = now();
            $choiceId = DB::table('choices')->insertGetId([
                'slug' => $slug,
                'name' => trim($first->name),
                'description' => $first->description,
                'thumbnail_path' => $first->thumbnail_path,
                'is_custom' => $first->is_custom,
                'is_advisory' => $first->is_advisory,
                'links_to_stone' => $first->links_to_stone,
                'size_kind' => $first->size_kind,
                'is_active' => true,
                'sort_order' => $sort++,
                // On no question any more: kept for its answers, off the library's page.
                'archived_at' => $alike->contains($listed) ? null : $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('choice_service')->insertUsing(
                ['choice_id', 'service_id'],
                DB::table('detail_field_option_service')->where('detail_field_option_id', $first->id)->selectRaw('?, service_id', [$choiceId]),
            );

            // One per question: a second option of the same name on a question stays its own.
            foreach ($alike->unique('detail_field_id') as $option) {
                DB::table('detail_field_options')->where('id', $option->id)->update([
                    'choice_id' => $choiceId,
                    'name' => trim($first->name),
                    'description' => $first->description,
                    'thumbnail_path' => $first->thumbnail_path,
                    'links_to_stone' => $first->links_to_stone,
                    'is_active' => true,
                    'archived_at' => $listed($option) ? null : ($option->archived_at ?? $now),
                    // Kind and size stay the option's own: a question keeps one custom and one advise-me.
                ]);
                DB::table('detail_field_option_service')->where('detail_field_option_id', $option->id)->delete();
                DB::table('detail_field_option_service')->insertUsing(
                    ['detail_field_option_id', 'service_id'],
                    DB::table('choice_service')->where('choice_id', $choiceId)->selectRaw('?, service_id', [$option->id]),
                );
            }
        }
    }
};
