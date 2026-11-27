<?php

use App\Enums\DetailAnswerType;
use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\FurnitureType;
use App\Models\Service;
use App\Models\ServiceFeature;
use App\Models\StoneType;
use Database\Seeders\ChoiceSeeder;
use Database\Seeders\DetailFieldSeeder;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\FurnitureTypeSeeder;
use Database\Seeders\InquiryFormSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ServiceStoneTypeSeeder;
use Database\Seeders\StoneSeeder;
use Database\Seeders\TableQuestionsSeeder;
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
        // A table is asked first its shape (which says how its size is asked), then its own table's base design; no base material.
        // Its seaters list the table sizes they need; furniture is not measured in square metres.
        'center-tables' => ['table_shape', 'table_base_design_center', 'size', 'seaters', 'edge_profile (stone)', 'finish (stone)'],
        'side-tables' => ['table_shape', 'table_base_design_side', 'size', 'seaters', 'edge_profile (stone)', 'finish (stone)'],
        'dining-tables' => ['table_shape', 'table_base_design_dining', 'seaters', 'edge_profile (stone)', 'finish (stone)'],
        'other-customized-furniture' => ['size', 'finish (stone)'],
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
    $lavatories = ['granite', 'marble', 'monumental', 'onyx', 'patagonia-quartzite', 'quartzite', 'travertine'];
    // Monumental is for surfaces, not furniture.
    $tables = ['granite', 'marble', 'onyx', 'patagonia-quartzite', 'quartzite', 'travertine'];

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
        'lavatories' => $lavatories,
        'center-tables' => $tables,
        'side-tables' => $tables,
        'dining-tables' => $tables,
        'other-customized-furniture' => $tables,
    ]);
});

it('changes nothing when the seeders run again, and retires what is no longer listed', function (): void {
    $retired = Service::query()->create([
        'service_type_id' => Service::query()->value('service_type_id'), 'slug' => 'seating', 'name' => 'Seating', 'description' => 'Old', 'sort_order' => 9,
    ]);
    FurnitureType::factory()->create(['slug' => 'seating', 'name' => 'Seating']);
    $counts = fn (): array => array_map(fn (string $table): int => DB::table($table)->count(), [
        'services', 'service_features', 'service_detail_fields', 'detail_fields', 'detail_field_options', 'choices', 'choice_service', 'service_stone_type', 'furniture_types',
    ]);
    $before = $counts();

    $this->seed([DetailFieldSeeder::class, ServiceSeeder::class, ServiceStoneTypeSeeder::class, FurnitureTypeSeeder::class]);

    expect($counts())->toBe($before)
        ->and($retired->fresh()->is_active)->toBeFalse()
        ->and(FurnitureType::query()->where('is_active', true)->orderBy('sort_order')->pluck('slug')->all())
        ->toBe(['center-side-tables', 'dining-tables', 'pedestal', 'other-customized-furniture'])
        ->and(FurnitureType::query()->where('slug', 'seating')->value('is_active'))->toBeFalse();
});

it('brings a database from before the table questions up to them, keeping what its admins changed', function (): void {
    $this->seed(EdgeProfileSeeder::class);
    // As it was: no shape or per-table base designs, no descriptions; and an admin's own changes.
    $tableFields = DetailField::query()->where('slug', 'table_shape')->orWhere('slug', 'like', 'table\_base\_design\_%')->pluck('id');
    DB::table('service_detail_fields')->whereIn('detail_field_id', $tableFields)->delete();
    DetailField::query()->whereIn('id', $tableFields)->delete();
    foreach (Service::query()->whereIn('slug', ['center-tables', 'side-tables', 'dining-tables'])->pluck('id') as $serviceId) {
        $asked = DB::table('service_detail_fields')->where('service_id', $serviceId)->orderBy('sort_order')->pluck('detail_field_id');
        foreach ($asked as $sort => $fieldId) {
            DB::table('service_detail_fields')->where('service_id', $serviceId)->where('detail_field_id', $fieldId)->update(['sort_order' => $sort]);
        }
    }
    EdgeProfile::query()->update(['description' => null]);
    Finish::query()->update(['description' => null]);
    EdgeProfile::query()->where('name', 'Straight Edge')->update(['description' => 'Our own words', 'sort_order' => 40]);
    DB::table('service_detail_fields')
        ->where('service_id', Service::query()->where('slug', 'side-tables')->value('id'))
        ->where('detail_field_id', DetailField::query()->where('slug', 'size')->value('id'))
        ->update(['is_required' => false]);

    $this->seed(TableQuestionsSeeder::class);
    $once = askedQuestions();
    $this->seed(TableQuestionsSeeder::class);

    expect($once['center-tables'])->toBe(['table_shape', 'table_base_design_center', 'size', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and($once['side-tables'])->toBe(['table_shape', 'table_base_design_side', 'size (optional)', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and($once['dining-tables'])->toBe(['table_shape', 'table_base_design_dining', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and(askedQuestions())->toBe($once)
        ->and(DetailFieldOption::query()->whereHas('detailField', fn ($query) => $query->where('slug', 'table_base_design_side'))->orderBy('sort_order')->pluck('slug')->all())
        ->toBe(['c-legs-v1', 'c-legs-v2', 'cylindrical-pedestal', 'hourglass-v2', 'custom-design', 'not-sure'])
        ->and(EdgeProfile::query()->where('name', 'Straight Edge')->first()?->only(['description', 'sort_order']))->toBe(['description' => 'Our own words', 'sort_order' => 40])
        ->and(EdgeProfile::query()->whereNull('description')->count())->toBe(0)
        ->and(Finish::query()->where('name', 'Polished')->value('description'))->not->toBeNull();
});

it('retires the single base design question the tables shared, and the Pedestal service', function (): void {
    $type = Service::query()->where('slug', 'dining-tables')->value('service_type_id');
    $pedestal = Service::query()->create(['service_type_id' => $type, 'slug' => 'pedestal', 'name' => 'Pedestal', 'description' => 'Old', 'sort_order' => 9, 'is_active' => true]);
    $shared = DetailField::query()->create(['slug' => 'table_base_design', 'name' => 'Table base design', 'scope' => 'project', 'answer_type' => 'choice', 'allows_custom' => false, 'sort_order' => 50]);
    $trestle = $shared->options()->create(['slug' => 'trestle', 'name' => 'Trestle', 'sort_order' => 0, 'is_active' => true, 'is_suggestion' => false, 'links_to_stone' => false]);
    $dining = Service::query()->where('slug', 'dining-tables')->firstOrFail();
    $dining->detailFields()->attach($shared->id, ['sort_order' => 1, 'is_required' => true]);
    $piece = Furniture::factory()->create(['table_base_design_option_id' => $trestle->id]);

    $this->seed(TableQuestionsSeeder::class);

    expect($shared->refresh()->archived_at)->not->toBeNull()
        ->and(DB::table('service_detail_fields')->where('detail_field_id', $shared->id)->exists())->toBeFalse()
        ->and($piece->refresh()->table_base_design_option_id)->toBeNull()
        ->and($pedestal->refresh()->is_active)->toBeFalse()
        ->and(askedQuestions()['dining-tables'])->toBe(['table_shape', 'table_base_design_dining', 'seaters', 'edge_profile (stone)', 'finish (stone)']);
});

it('moves a choice off its first seeded picture, keeping one uploaded on the Choices page', function (): void {
    $choice = fn (string $slug): Choice => Choice::query()->where('slug', $slug)->sole();
    $seeded = $choice('round')->thumbnail_path;
    $choice('round')->update(['thumbnail_path' => '/images/inquiry/table-shapes/round.jpg']);
    $choice('oval')->update(['thumbnail_path' => 'question-choices/0b3f5d0e-uploaded.jpg']);

    $this->seed(TableQuestionsSeeder::class);

    expect($choice('round')->thumbnail_path)->toBe($seeded)
        ->and($choice('oval')->thumbnail_path)->toBe('question-choices/0b3f5d0e-uploaded.jpg')
        // The question's option follows its choice.
        ->and(DetailFieldOption::query()->where('choice_id', $choice('round')->id)->value('thumbnail_path'))->toBe($seeded);
});

it('seeds one library of choices, which the table questions share', function (): void {
    $advise = Choice::query()->where('slug', 'not-sure')->sole();
    $listing = fn (Choice $choice): array => DetailFieldOption::query()->where('choice_id', $choice->id)->join('detail_fields', 'detail_fields.id', '=', 'detail_field_options.detail_field_id')->orderBy('detail_fields.sort_order')->pluck('detail_fields.slug')->all();

    expect(Choice::query()->count())->toBe(count(ChoiceSeeder::data()))
        ->and($listing($advise))->toBe(['table_shape', 'table_base_design_center', 'table_base_design_side', 'table_base_design_dining'])
        ->and($listing(Choice::query()->where('slug', 'custom-design')->sole()))->toBe(['table_base_design_center', 'table_base_design_side', 'table_base_design_dining'])
        // Every list question's option is a library choice's, and reads as it does.
        ->and(DetailFieldOption::query()->where('is_suggestion', false)->whereNull('choice_id')->count())->toBe(0)
        ->and(DetailFieldOption::query()->where('choice_id', $advise->id)->pluck('description')->unique()->all())->toBe([$advise->description]);
});

it('keeps each seeded choice\'s picture in storage, under a random name, in a folder the bucket upload sends', function (): void {
    $committed = [];
    preg_match_all('#^!([A-Za-z0-9_-]+)/$#m', (string) file_get_contents(storage_path('app/public/.gitignore')), $committed);
    $images = collect(ChoiceSeeder::data())->pluck('image')->filter()->values();

    expect($images)->not->toBeEmpty();

    foreach ($images as $path) {
        expect($path)->toMatch('#^question-choices/[0-9a-f]{32}\.jpg$#')
            ->and(storage_path('app/public/'.$path))->toBeFile()
            ->and($committed[1])->toContain(explode('/', $path)[0]);
    }

    expect(Choice::query()->whereNotNull('thumbnail_path')->pluck('thumbnail_path')->sort()->values()->all())->toBe($images->sort()->values()->all());
});

it('names a free entry\'s own answer, notes each seaters\' table size, and asks no base material', function (): void {
    $seaters = DetailField::query()->where('slug', 'seaters')->sole();
    $notes = fn (string $slug): ?array => Service::query()->where('slug', $slug)->first(['delivery_note', 'schedule_note', 'lead_days'])?->only(['delivery_note', 'schedule_note', 'lead_days']);

    expect(DetailField::query()->where('slug', 'size')->first(['custom_label', 'custom_hint'])?->only(['custom_label', 'custom_hint']))
        ->toBe(['custom_label' => 'Custom size', 'custom_hint' => 'Enter your desired size.'])
        // 6, 8 and 12 for now, each with its table size.
        ->and($seaters->suggestions()->map(fn (DetailFieldOption $option): string => "{$option->name} – {$option->description}")->all())->toBe(['6 – approx. 1.6 sqm', '8 – approx. 2.2 sqm', '12 – approx. 3.3 sqm'])
        // A table's base design is a plain list: no Table base question, nor its Wood and Steel.
        ->and(DetailField::query()->where('slug', 'table_base')->exists())->toBeFalse()
        ->and(Choice::query()->whereIn('slug', ['wood', 'steel'])->exists())->toBeFalse()
        ->and($notes('dining-tables')['lead_days'])->toBe(5)
        // How long a delivery takes, and nothing under the date, which would say the same.
        ->and($notes('dining-tables')['delivery_note'])->toBe('Ready-made pieces are delivered within 5 to 7 days. Custom designs take longer; we will confirm the timeline in your quotation.')
        ->and($notes('dining-tables')['schedule_note'])->toBeNull()
        ->and($notes('slab-supply-only')['delivery_note'])->toBeNull()
        // An installed service asks no delivery method, nor a date ahead.
        ->and($notes('flooring'))->toBe(['delivery_note' => null, 'schedule_note' => null, 'lead_days' => null]);
});

it('brings a database from before the notes and seaters up to them, without Table base, keeping what its admins changed', function (): void {
    $id = fn (string $slug): int => DetailField::query()->where('slug', $slug)->value('id');
    $service = fn (string $slug): Service => Service::query()->where('slug', $slug)->sole();
    // As it was: no own-answer names or notes; center and side tables without seaters; furniture asked its area; tables asked Table base (Wood, Steel).
    DetailField::query()->update(['custom_label' => null, 'custom_hint' => null]);
    DetailField::query()->where('slug', 'thickness')->update(['custom_label' => 'Your own thickness']);
    DetailFieldOption::query()->where('is_suggestion', true)->update(['description' => null]);
    Service::query()->update(['delivery_note' => null, 'schedule_note' => null, 'lead_days' => null]);
    $service('side-tables')->update(['lead_days' => 10]);
    foreach (['center-tables', 'side-tables'] as $slug) {
        $service($slug)->detailFields()->detach($id('seaters'));
    }
    foreach (['dining-tables', 'other-customized-furniture'] as $slug) {
        $service($slug)->detailFields()->attach($id('area_sqm'), ['sort_order' => 50, 'is_required' => true]);
    }
    $material = DetailField::query()->create(['slug' => 'table_base', 'name' => 'Table base', 'scope' => 'project', 'answer_type' => DetailAnswerType::Choice, 'allows_custom' => false, 'sort_order' => 60]);
    $materials = collect(['wood' => 'Wood', 'steel' => 'Steel'])->map(fn (string $name, string $slug): int => Choice::query()->create(['slug' => $slug, 'name' => $name, 'is_active' => true, 'sort_order' => 90])->id);
    $material->writeChoices($materials->values()->all());
    foreach (['center-tables', 'side-tables', 'dining-tables'] as $slug) {
        $service($slug)->detailFields()->attach($material->id, ['sort_order' => 1, 'is_required' => true]);
    }
    StoneType::query()->where('slug', 'monumental')->sole()->services()->attach($service('dining-tables')->id);
    // Seaters suggested 2 and 6 only, without their sizes.
    DetailField::query()->where('slug', 'seaters')->sole()->writeSuggestions([['name' => '2', 'note' => null], ['name' => '6', 'note' => null]]);
    // Rectangular still on its old seeded picture; Oval on one an admin uploaded.
    Choice::query()->where('slug', 'rectangular')->sole()->update(['thumbnail_path' => 'question-choices/1437d2234a87aa3364c4d7cb1755daea.jpg']);
    Choice::query()->where('slug', 'oval')->sole()->update(['thumbnail_path' => 'question-choices/0b3f5d0e-uploaded.jpg']);

    $this->seed(InquiryFormSeeder::class);
    $once = askedQuestions();
    $this->seed(InquiryFormSeeder::class);

    expect($once['center-tables'])->toBe(['table_shape', 'table_base_design_center', 'size', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and($once['side-tables'])->toBe(['table_shape', 'table_base_design_side', 'size', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and($once['dining-tables'])->toBe(['table_shape', 'table_base_design_dining', 'seaters', 'edge_profile (stone)', 'finish (stone)'])
        ->and($once['other-customized-furniture'])->toBe(['size', 'finish (stone)'])
        ->and(askedQuestions())->toBe($once)
        // Archived, not deleted, so a sent inquiry's Table base still reads.
        ->and($material->fresh()->archived_at)->not->toBeNull()
        ->and(Choice::query()->whereIn('slug', ['wood', 'steel'])->whereNull('archived_at')->exists())->toBeFalse()
        ->and(Choice::query()->whereIn('slug', ['wood', 'steel'])->count())->toBe(2)
        ->and(DetailField::query()->where('slug', 'size')->value('custom_label'))->toBe('Custom size')
        ->and(DetailField::query()->where('slug', 'thickness')->value('custom_label'))->toBe('Your own thickness')
        ->and(DetailField::query()->where('slug', 'seaters')->sole()->suggestions()->map(fn (DetailFieldOption $option): string => "{$option->name} – {$option->description}")->all())->toBe(['6 – approx. 1.6 sqm', '8 – approx. 2.2 sqm', '12 – approx. 3.3 sqm'])
        ->and(Choice::query()->where('slug', 'rectangular')->value('thumbnail_path'))->toBe('question-choices/11a89a838eee51dc81bce3d3a356fef4.jpg')
        ->and(DetailFieldOption::query()->whereHas('detailField', fn ($query) => $query->where('slug', 'table_shape'))->where('slug', 'rectangular')->value('thumbnail_path'))->toBe('question-choices/11a89a838eee51dc81bce3d3a356fef4.jpg')
        ->and(Choice::query()->where('slug', 'oval')->value('thumbnail_path'))->toBe('question-choices/0b3f5d0e-uploaded.jpg')
        ->and($service('dining-tables')->lead_days)->toBe(5)
        ->and($service('side-tables')->lead_days)->toBe(10)
        ->and($service('dining-tables')->stoneTypes()->where('slug', 'monumental')->exists())->toBeFalse()
        ->and($service('flooring')->stoneTypes()->where('slug', 'monumental')->exists())->toBeTrue();
});
