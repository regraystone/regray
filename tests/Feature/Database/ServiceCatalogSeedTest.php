<?php

use App\Enums\DetailAnswerType;
use App\Models\DetailField;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\StoneType;
use Database\Seeders\DetailFieldSeeder;
use Database\Seeders\FurnitureTypeSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ServiceStoneTypeSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Support\Facades\DB;

/*
 * What the services ask is data: the seeders write each service's questions,
 * features and stone types, and running them again changes nothing.
 */

beforeEach(function (): void {
    $this->seed([StoneSeeder::class, ServiceSeeder::class, ServiceStoneTypeSeeder::class, FurnitureTypeSeeder::class]);
});

/** Each service's questions, in the order asked, a stone-scope one marked. */
function askedQuestions(): array
{
    return Service::query()->with('detailFields')->orderBy('service_type_id')->orderBy('sort_order')->get()
        ->mapWithKeys(fn (Service $service): array => [$service->slug => $service->detailFields
            ->map(fn (DetailField $field): string => $field->slug.($field->scope->value === 'stone' ? ' (stone)' : '').($field->isRequiredByService() ? '' : ' (optional)'))
            ->all()])
        ->all();
}

it('asks each service its own questions, in order', function (): void {
    expect(askedQuestions())->toBe([
        // Slabs supplied alone may come uncut, so their edge is optional.
        'slab-supply-only' => ['quantity (stone)', 'slab_size (stone)', 'thickness (stone)', 'edge_profile (stone) (optional)', 'finish (stone)'],
        'slab-supply-and-installation' => ['quantity (stone)', 'slab_size (stone)', 'thickness (stone)', 'edge_profile (stone)', 'finish (stone)'],
        'installation-only' => [],
        'countertop-backsplash' => ['area_sqm', 'edge_profile (stone)', 'finish (stone)', 'fascia'],
        'wall-cladding' => ['area_sqm', 'finish (stone)'],
        'flooring' => ['area_sqm', 'finish (stone)'],
        // A step's front edge (its nosing) is profiled.
        'staircase' => ['area_sqm', 'edge_profile (stone)', 'finish (stone)'],
        'kitchen-islands' => ['area_sqm', 'edge_profile (stone)', 'finish (stone)', 'waterfall_effect'],
        'lavatories' => ['area_sqm', 'edge_profile (stone)', 'finish (stone)'],
        // A small piece is measured by its size, not in square metres.
        'center-tables' => ['size', 'edge_profile (stone)', 'finish (stone)'],
        'side-tables' => ['size', 'edge_profile (stone)', 'finish (stone)'],
        'dining-tables' => ['area_sqm', 'seaters', 'edge_profile (stone)', 'finish (stone)', 'table_base'],
        'pedestal' => ['size', 'edge_profile (stone)', 'finish (stone)'],
        'other-customized-furniture' => ['area_sqm', 'size', 'finish (stone)'],
    ])
        ->and(Service::query()->where('requires_stones', false)->pluck('slug')->all())->toBe(['installation-only'])
        ->and(DetailField::query()->where('slug', 'area_sqm')->value('name'))->toBe('Project area (sqm)')
        ->and(DetailField::query()->where('slug', 'fascia')->first()?->only(['name', 'answer_type']))->toEqual(['name' => 'Fascia', 'answer_type' => DetailAnswerType::Boolean])
        // The slab size and thickness list each stone's own slabs, not suggestions of their own.
        ->and(DetailField::query()->whereIn('slug', ['slab_size', 'thickness'])->withCount('options')->pluck('options_count')->all())->toBe([0, 0]);
});

it('keeps each service\'s features as rows, in order', function (): void {
    $features = ServiceFeature::query()
        ->where('service_id', Service::query()->where('slug', 'slab-supply-only')->value('id'))
        ->orderBy('sort_order')
        ->pluck('text')
        ->all();

    expect($features)->toBe(['Every stone type Regray carries', 'Sourcing based on your project requirements', 'Nationwide supply'])
        ->and(Service::query()->whereDoesntHave('features')->pluck('slug')->all())->toBe([]);
});

it('links each stone type to the services it is offered for, and none to Installation Only', function (): void {
    $links = Service::query()->with('stoneTypes')->orderBy('service_type_id')->orderBy('sort_order')->get()
        ->mapWithKeys(fn (Service $service): array => [$service->slug => $service->stoneTypes->pluck('slug')->sort()->values()->all()])
        ->all();
    $all = StoneType::query()->pluck('slug')->sort()->values()->all();
    $countertop = ['granite', 'marble', 'monumental', 'onyx', 'patagonia-quartzite', 'quartzite'];
    // Travertine is too porous for a kitchen, but makes bathroom tops and tables.
    $tables = ['granite', 'marble', 'monumental', 'onyx', 'patagonia-quartzite', 'quartzite', 'travertine'];

    expect($links)->toBe([
        'slab-supply-only' => $all,
        // Cobblestone and crazy cut are laid through Flooring and Wall Cladding.
        'slab-supply-and-installation' => array_values(array_diff($all, ['cobblestone', 'crazy-cut'])),
        'installation-only' => [],
        'countertop-backsplash' => $countertop,
        'wall-cladding' => ['crazy-cut', 'granite', 'marble', 'monumental', 'onyx', 'patagonia-quartzite', 'quartzite', 'travertine'],
        'flooring' => ['cobblestone', 'crazy-cut', 'granite', 'marble', 'monumental', 'quartzite', 'travertine'],
        'staircase' => ['granite', 'marble', 'monumental', 'quartzite', 'travertine'],
        'kitchen-islands' => $countertop,
        'lavatories' => $tables,
        'center-tables' => $tables,
        'side-tables' => $tables,
        'dining-tables' => $tables,
        'pedestal' => ['marble', 'monumental', 'onyx', 'patagonia-quartzite', 'quartzite', 'travertine'],
        'other-customized-furniture' => $tables,
    ]);
});

it('changes nothing when the seeders run again, and retires what is no longer listed', function (): void {
    $retired = Service::query()->create([
        'service_type_id' => Service::query()->value('service_type_id'), 'slug' => 'seating', 'name' => 'Seating', 'description' => 'Old', 'sort_order' => 9,
    ]);
    FurnitureType::factory()->create(['slug' => 'seating', 'name' => 'Seating']);
    $counts = fn (): array => array_map(fn (string $table): int => DB::table($table)->count(), [
        'services', 'service_features', 'service_detail_fields', 'detail_fields', 'detail_field_options', 'service_stone_type', 'furniture_types',
    ]);
    $before = $counts();

    $this->seed([DetailFieldSeeder::class, ServiceSeeder::class, ServiceStoneTypeSeeder::class, FurnitureTypeSeeder::class]);

    expect($counts())->toBe($before)
        ->and($retired->fresh()->is_active)->toBeFalse()
        ->and(FurnitureType::query()->where('is_active', true)->orderBy('sort_order')->pluck('slug')->all())
        ->toBe(['center-side-tables', 'dining-tables', 'pedestal', 'other-customized-furniture'])
        ->and(FurnitureType::query()->where('slug', 'seating')->value('is_active'))->toBeFalse();
});
