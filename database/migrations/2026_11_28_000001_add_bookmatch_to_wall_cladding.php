<?php

use App\Models\Choice;
use App\Models\DetailField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wall Cladding asks whether its slabs are bookmatched (mirrored so the
 * veins meet), and how: No bookmatch, Vertical, Horizontal or Custom layout,
 * which the client describes. It is asked, not required, once for the
 * project, and each choice but the first says it depends on matching slabs
 * in stock and on the wall.
 *
 * A database already in use gets the question here; a new one gets it from
 * DetailFieldSeeder and ServiceSeeder (data/detail_fields.php,
 * data/choices.php, data/services.php). A library choice already there by
 * slug or by name is listed as it is, and a database with a Bookmatch
 * question of its own, or no Wall Cladding, is left alone.
 */
return new class extends Migration
{
    private const SERVICE = 'wall-cladding';

    private const FIELD = 'bookmatch';

    private const AVAILABILITY = 'Depends on matching slabs in stock and on your wall.';

    /** @var list<array{slug: string, name: string, description: string, is_custom: bool}> */
    private const CHOICES = [
        ['slug' => 'no-bookmatch', 'name' => 'No bookmatch', 'is_custom' => false,
            'description' => 'The slabs are laid one after another, each with its own natural veining.'],
        ['slug' => 'bookmatch-vertical', 'name' => 'Vertical', 'is_custom' => false,
            'description' => 'Two slabs from the same block, opened like a book side by side, so the veins mirror each other. '.self::AVAILABILITY],
        ['slug' => 'bookmatch-horizontal', 'name' => 'Horizontal', 'is_custom' => false,
            'description' => 'Two slabs from the same block, one above the other, so the veins mirror each other. '.self::AVAILABILITY],
        ['slug' => 'bookmatch-custom', 'name' => 'Custom layout', 'is_custom' => true,
            'description' => 'Have a layout in mind? Describe it and we will work it out with you. '.self::AVAILABILITY],
    ];

    public function up(): void
    {
        $serviceId = DB::table('services')->where('slug', self::SERVICE)->value('id');

        if ($serviceId === null || DB::table('detail_fields')->where('slug', self::FIELD)->exists()) {
            return;
        }

        DB::transaction(function () use ($serviceId): void {
            $choiceIds = array_map(function (array $data): int {
                $choice = Choice::query()->where('slug', $data['slug'])->first()
                    ?? Choice::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first();

                if ($choice === null) {
                    $choice = new Choice;
                    $choice->forceFill([
                        ...$data,
                        'is_advisory' => false,
                        'links_to_stone' => false,
                        'is_active' => true,
                        'sort_order' => (int) Choice::query()->max('sort_order') + 1,
                    ])->save();
                }

                return $choice->id;
            }, self::CHOICES);

            $field = DetailField::query()->create([
                'slug' => self::FIELD,
                'name' => 'Bookmatch',
                'scope' => 'project',
                'answer_type' => 'choice',
                'allows_custom' => false,
                'sort_order' => (int) DetailField::query()->max('sort_order') + 1,
            ]);

            $field->writeChoices($choiceIds);

            DB::table('service_detail_fields')->insert([
                'service_id' => $serviceId,
                'detail_field_id' => $field->id,
                'sort_order' => (int) DB::table('service_detail_fields')->where('service_id', $serviceId)->max('sort_order') + 1,
                'is_required' => false,
            ]);
        });
    }

    /**
     * Wall Cladding no longer asks it. The question and its choices go only
     * while no inquiry has answered it.
     */
    public function down(): void
    {
        $fieldId = DB::table('detail_fields')->where('slug', self::FIELD)->value('id');

        if ($fieldId === null) {
            return;
        }

        DB::table('service_detail_fields')->where('detail_field_id', $fieldId)->delete();

        if (DB::table('inquiry_detail_answers')->where('detail_field_id', $fieldId)->exists()) {
            return;
        }

        DB::transaction(function () use ($fieldId): void {
            DB::table('detail_field_options')->where('detail_field_id', $fieldId)->delete();
            DB::table('detail_fields')->where('id', $fieldId)->delete();
            DB::table('choices')
                ->whereIn('slug', array_column(self::CHOICES, 'slug'))
                ->whereNotExists(fn ($query) => $query->from('detail_field_options')->whereColumn('detail_field_options.choice_id', 'choices.id'))
                ->delete();
        });
    }
};
