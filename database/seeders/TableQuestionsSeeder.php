<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Brings a database already in use up to the table questions without
 * touching what its admins have changed, which DetailFieldSeeder and
 * ServiceSeeder would overwrite. Run once after the migration that added
 * them (2026_11_25_000001); run again, it changes nothing.
 *
 * - Table shape and each table service's own base design question (Center,
 *   Side and Dining table base design, each listing only that table's
 *   bases) are added listing their library choices (data/detail_fields.php,
 *   data/choices.php), unless they are there already; a library choice they
 *   need is added if it is missing.
 * - An earlier single Table base design question shared by the three, if
 *   any, is archived and no longer asked, with the library choices only it
 *   listed; a piece of furniture that had one of its bases has none until
 *   it is given another.
 * - Center, side and dining tables are asked Table shape, their own base
 *   design and Table base (the base's material), and ask those three first:
 *   the picture questions, then the material's list, then the rest in their
 *   order.
 * - The Pedestal service is switched off (its inquiries keep it).
 * - A choice still on its first seeded picture (under /images/inquiry/,
 *   since moved into storage) gets its picture from the data; one uploaded
 *   on the Choices page stays.
 * - An edge profile or finish with no description gets the seeded one.
 * - A seeded table with no shape or base design of its own gets the one
 *   data/furniture.php gives it.
 *
 * A new database gets all of this from DatabaseSeeder.
 */
class TableQuestionsSeeder extends Seeder
{
    /** Where the first seeded pictures were, before they moved to question-choices/ on the public disk. */
    private const OLD_PICTURES = '/images/inquiry/';

    /** The services that ask about a table, by slug. */
    private const TABLE_SERVICES = ['center-tables', 'side-tables', 'dining-tables'];

    /** The service no longer offered. */
    private const RETIRED_SERVICE = 'pedestal';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->retireSharedBaseQuestion();
            $fields = $this->addQuestions();
            $this->askTables($fields);

            Service::query()->where('slug', self::RETIRED_SERVICE)->update(['is_active' => false]);

            $this->describe();
            $this->shapeFurniture();
        });
    }

    /**
     * The earlier Table base design question every table service shared: no
     * longer asked, archived, and off the furniture that named its bases.
     */
    private function retireSharedBaseQuestion(): void
    {
        $shared = DetailField::query()->where('slug', DetailField::TABLE_BASE_DESIGN)->first();

        if ($shared === null) {
            return;
        }

        DB::table('service_detail_fields')->where('detail_field_id', $shared->id)->delete();
        Furniture::query()->withTrashed()->whereIn('table_base_design_option_id', $shared->options()->select('id'))->update(['table_base_design_option_id' => null]);

        if ($shared->archived_at === null) {
            $shared->forceFill(['archived_at' => now()])->save();
        }

        // Its bases no other question lists leave the library too.
        Choice::query()->notArchived()
            ->whereHas('options', fn ($options) => $options->where('detail_field_id', $shared->id))
            ->whereDoesntHave('options', fn ($options) => $options->where('detail_field_id', '!=', $shared->id))
            ->get()
            ->each(fn (Choice $choice) => $choice->forceFill(['archived_at' => now(), 'is_active' => false])->save());
    }

    /**
     * Table shape and the base design questions, by slug. One that is missing
     * is added listing its choices from data/detail_fields.php; one there
     * already keeps the choices an admin gave it, unless it lists none. A
     * library choice they need that is missing (by slug, or by name, ignoring
     * case) is added from data/choices.php, offered for the services it names.
     *
     * @return array<string, DetailField>
     */
    private function addQuestions(): array
    {
        $choices = collect(ChoiceSeeder::data())->keyBy('slug');
        $questions = array_filter(DetailFieldSeeder::data(), fn (array $question): bool => $question['slug'] === DetailField::TABLE_SHAPE
            || str_starts_with($question['slug'], DetailField::TABLE_BASE_DESIGN.'_'));
        $fields = [];

        foreach ($questions as $question) {
            $field = DetailField::query()->firstOrCreate(['slug' => $question['slug']], [
                'name' => $question['name'],
                'scope' => $question['scope'],
                'answer_type' => $question['answer_type'],
                'allows_custom' => $question['allows_custom'],
                'sort_order' => ((int) DetailField::query()->max('sort_order')) + 1,
            ]);

            if ($field->wasRecentlyCreated || ! $field->options()->where('is_suggestion', false)->whereNull('archived_at')->exists()) {
                $field->writeChoices(array_map(
                    fn (string $slug): int => $this->libraryChoice($choices[$slug] ?? throw new RuntimeException("data/choices.php has no {$slug}.")),
                    $question['choices'] ?? [],
                ));
            }

            $fields[$field->slug] = $field;
        }

        // A choice still on its first seeded picture points at a file that is gone.
        foreach (Choice::query()->where('thumbnail_path', 'like', self::OLD_PICTURES.'%')->get() as $choice) {
            $choice->update(['thumbnail_path' => $choices[$choice->slug]['image'] ?? null]);
        }

        return $fields;
    }

    /**
     * The library's choice, by slug or by name, added from its data when it
     * is missing.
     *
     * @param  array{slug: string, name: string, description?: string, image?: string, kind?: string, size?: string, services?: list<string>}  $data
     */
    private function libraryChoice(array $data): int
    {
        $choice = Choice::query()->where('slug', $data['slug'])->first()
            ?? Choice::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

        if ($choice !== null) {
            return $choice->id;
        }

        $kind = $data['kind'] ?? null;
        $choice = Choice::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail_path' => $data['image'] ?? null,
            'is_custom' => $kind === 'custom',
            'is_advisory' => $kind === 'advisory',
            'links_to_stone' => $kind === 'stone',
            'size_kind' => $data['size'] ?? null,
            'is_active' => true,
            'sort_order' => ((int) Choice::query()->max('sort_order')) + 1,
        ]);
        $choice->offerFor(array_values(Service::query()->whereIn('slug', $data['services'] ?? [])->pluck('id')->all()));

        return $choice->id;
    }

    /**
     * Each table service asks Table shape, its own base design question (as
     * data/services.php names it) and its base material (Table base),
     * required, and asks the three first, as a new database does: the ones
     * answered by picture, then the material's list, then its other
     * questions in the order it already had them.
     *
     * @param  array<string, DetailField>  $fields
     */
    private function askTables(array $fields): void
    {
        /** @var list<array{services: list<array{slug: string, fields: array<string, bool>}>}> $types */
        $types = require database_path('seeders/data/services.php');
        $asks = collect($types)->flatMap(fn (array $type): array => $type['services'])->keyBy('slug');
        $material = DetailField::query()->where('slug', 'table_base')->value('id');

        foreach (Service::query()->whereIn('slug', self::TABLE_SERVICES)->get() as $service) {
            $base = collect(array_keys($asks[$service->slug]['fields'] ?? []))
                ->first(fn (string $slug): bool => str_starts_with($slug, DetailField::TABLE_BASE_DESIGN.'_'))
                ?? throw new RuntimeException("data/services.php gives {$service->slug} no base design question.");
            $first = array_values(array_filter([$fields[DetailField::TABLE_SHAPE]->id, $fields[$base]->id, $material]));

            $asked = fn () => DB::table('service_detail_fields')->where('service_id', $service->id);
            $already = $asked()->pluck('detail_field_id')->all();

            foreach (array_diff($first, $already) as $fieldId) {
                $service->detailFields()->attach($fieldId, ['sort_order' => 0, 'is_required' => true]);
            }

            $rest = $asked()->whereNotIn('detail_field_id', $first)->orderBy('sort_order')->orderBy('detail_field_id')->pluck('detail_field_id')->all();

            foreach ([...$first, ...$rest] as $sort => $fieldId) {
                $asked()->where('detail_field_id', $fieldId)->update(['sort_order' => $sort]);
            }
        }
    }

    /** The seeded tables' shapes and base designs, where a piece has none. */
    private function shapeFurniture(): void
    {
        /** @var list<array{slug: string, table_shape?: string, table_base_design?: string}> $pieces */
        $pieces = require database_path('seeders/data/furniture.php');
        $columns = ['table_shape_option_id' => DetailField::TABLE_SHAPE, 'table_base_design_option_id' => DetailField::TABLE_BASE_DESIGN];

        foreach ($pieces as $piece) {
            foreach ($columns as $column => $field) {
                $slug = $piece[$field] ?? null;
                $id = $slug === null ? null : DetailFieldOption::query()
                    ->whereHas('detailField', fn ($query) => $field === DetailField::TABLE_BASE_DESIGN
                        ? $query->where('slug', 'like', DetailField::TABLE_BASE_DESIGN.'\_%')
                        : $query->where('slug', $field))
                    ->where('slug', $slug)
                    ->orderBy('id')
                    ->value('id');

                if ($id !== null) {
                    Furniture::query()->withTrashed()->where('slug', $piece['slug'])->whereNull($column)->update([$column => $id]);
                }
            }
        }
    }

    /** The seeded descriptions, for the edge profiles and finishes that have none. */
    private function describe(): void
    {
        // It only adds what is missing, so an admin's edges stay as they are.
        $this->callSilent(EdgeProfileSeeder::class);

        /** @var array{finish_descriptions: array<string, string>} $stones */
        $stones = require database_path('seeders/data/stones.php');

        foreach ($stones['finish_descriptions'] as $name => $description) {
            Finish::query()->withTrashed()->where('name', $name)->whereNull('description')->update(['description' => $description]);
        }
    }
}
