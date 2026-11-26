<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\Service;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The library of choices the list questions pick from, from data/choices.php:
 * each one's name, description, picture, kind and how a table shape asks the
 * size. Run again, it updates them in place (found by slug, or by name),
 * and every question option listing one follows (Choice::mirror()). Which services a choice is offered
 * for needs the services, so ServiceSeeder links them (linkServices()).
 *
 * @phpstan-type ChoiceData array{slug: string, name: string, description?: string, image?: string, kind?: string, size?: string, services?: list<string>}
 */
class ChoiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::data() as $sort => $data) {
            $kind = $data['kind'] ?? null;

            // By slug, or by name: a choice the library migration made from a question's option has a slug of its name.
            $choice = Choice::query()->where('slug', $data['slug'])->first()
                ?? Choice::query()->whereRaw('lower(name) = ?', [mb_strtolower($data['name'])])->first()
                ?? new Choice;

            $choice->fill([
                'slug' => $data['slug'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'thumbnail_path' => $data['image'] ?? null,
                'is_custom' => $kind === 'custom',
                'is_advisory' => $kind === 'advisory',
                'links_to_stone' => $kind === 'stone',
                'size_kind' => $data['size'] ?? null,
                'is_active' => true,
                'sort_order' => $sort,
            ])->save();
        }
    }

    /**
     * Offer each choice for the services data/choices.php names (`services`,
     * by slug); one that names none is offered for every service that asks a
     * question listing it.
     */
    public function linkServices(): void
    {
        $serviceIds = Service::query()->pluck('id', 'slug');

        foreach (self::data() as $data) {
            $ids = array_map(
                fn (string $slug): int => $serviceIds[$slug] ?? throw new RuntimeException("Choice {$data['slug']} names the unknown service {$slug}."),
                $data['services'] ?? [],
            );

            Choice::query()->where('slug', $data['slug'])->first()?->offerFor($ids);
        }
    }

    /** @return list<ChoiceData> */
    public static function data(): array
    {
        /** @var list<ChoiceData> */
        return require database_path('seeders/data/choices.php');
    }
}
