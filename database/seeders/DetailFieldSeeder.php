<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The catalog of questions a service can ask, from data/detail_fields.php:
 * a free entry's suggestions, and the library choices a list question lists
 * (ChoiceSeeder, data/choices.php), in its order. Run again, it updates them
 * in place; a suggestion no longer listed is made inactive, and a choice no
 * longer listed is taken off its question (DetailField::writeChoices()),
 * rather than deleted, since sent answers may reference it.
 *
 * A suggestion's note ("approx. 0.6 sqm") is kept as its description, and
 * the list reads it after the value.
 *
 * @phpstan-type FieldData array{slug: string, name: string, scope: string, answer_type: string, allows_custom: bool, custom_label?: string, custom_hint?: string, suggestions?: list<array{0: string, 1: string, 2?: string}>, choices?: list<string>}
 */
class DetailFieldSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChoiceSeeder::class);
        $choiceIds = Choice::query()->pluck('id', 'slug');

        foreach (self::data() as $sort => $data) {
            $field = DetailField::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'scope' => $data['scope'],
                'answer_type' => $data['answer_type'],
                'allows_custom' => $data['allows_custom'],
                'custom_label' => $data['custom_label'] ?? null,
                'custom_hint' => $data['custom_hint'] ?? null,
                'sort_order' => $sort,
            ]);

            $suggestions = $data['suggestions'] ?? [];

            foreach ($suggestions as $optionSort => $option) {
                DetailFieldOption::query()->updateOrCreate(['detail_field_id' => $field->id, 'slug' => $option[0]], [
                    'name' => $option[1],
                    'description' => $option[2] ?? null,
                    'sort_order' => $optionSort,
                    'is_active' => true,
                    'is_suggestion' => true,
                    'links_to_stone' => false,
                ]);
            }

            DetailFieldOption::query()
                ->where('detail_field_id', $field->id)
                ->where('is_suggestion', true)
                ->whereNotIn('slug', array_map(fn (array $option): string => $option[0], $suggestions))
                ->update(['is_active' => false]);

            if (isset($data['choices'])) {
                $field->writeChoices(array_map(
                    fn (string $slug): int => $choiceIds[$slug] ?? throw new RuntimeException("{$data['slug']} lists the unknown choice {$slug}."),
                    $data['choices'],
                ));
            }
        }
    }

    /** @return list<FieldData> */
    public static function data(): array
    {
        /** @var list<FieldData> */
        return require database_path('seeders/data/detail_fields.php');
    }
}
