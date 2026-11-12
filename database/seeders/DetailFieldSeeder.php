<?php

namespace Database\Seeders;

use App\Models\DetailField;
use App\Models\DetailFieldOption;
use Illuminate\Database\Seeder;

/**
 * The catalog of questions a service can ask, and their listed answers, from
 * data/detail_fields.php. Run again, it updates them in place; an option no
 * longer listed is made inactive rather than deleted, since sent answers may
 * reference it.
 */
class DetailFieldSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{slug: string, name: string, scope: string, answer_type: string, allows_custom: bool, suggestions?: list<array{0: string, 1: string}>, options?: list<array{0: string, 1: string, links_to_stone?: bool}>}> $fields */
        $fields = require database_path('seeders/data/detail_fields.php');

        foreach ($fields as $sort => $data) {
            $field = DetailField::query()->updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'scope' => $data['scope'],
                'answer_type' => $data['answer_type'],
                'allows_custom' => $data['allows_custom'],
                'sort_order' => $sort,
            ]);

            $listed = [
                ...array_map(fn (array $option): array => [...$option, 'is_suggestion' => true], $data['suggestions'] ?? []),
                ...array_map(fn (array $option): array => [...$option, 'is_suggestion' => false], $data['options'] ?? []),
            ];

            foreach ($listed as $optionSort => $option) {
                DetailFieldOption::query()->updateOrCreate(['detail_field_id' => $field->id, 'slug' => $option[0]], [
                    'name' => $option[1],
                    'sort_order' => $optionSort,
                    'is_active' => true,
                    'is_suggestion' => $option['is_suggestion'],
                    'links_to_stone' => $option['links_to_stone'] ?? false,
                ]);
            }

            DetailFieldOption::query()
                ->where('detail_field_id', $field->id)
                ->whereNotIn('slug', array_map(fn (array $option): string => $option[0], $listed))
                ->update(['is_active' => false]);
        }
    }
}
