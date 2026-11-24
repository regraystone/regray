<?php

use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\DetailField;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\StoneType;
use App\Models\User;
use App\Repositories\Contracts\InquiryCatalogRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin's Services page: what the Inquire page offers. Adding, changing
 * and archiving a service and a service type, and what the Inquire page
 * offers after each.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->admin = User::factory()->role(Role::Admin)->create();
    $this->natural = ServiceType::query()->where('slug', 'natural-stone-applications')->sole();
    $this->granite = StoneType::query()->where('slug', 'granite')->sole();
    $this->marble = StoneType::query()->where('slug', 'marble')->sole();
});

/** @return list<string> */
function offeredServiceSlugs(): array
{
    return app(InquiryCatalogRepositoryInterface::class)->serviceTypes()
        ->flatMap(fn (ServiceType $type) => $type->services->pluck('slug'))
        ->values()
        ->all();
}

describe('the page', function (): void {
    it('lists every service but the archived, by type then display order, with what the forms offer', function (): void {
        Service::query()->where('slug', 'pedestal')->update(['is_active' => false, 'archived_at' => now()]);
        Service::query()->where('slug', 'flooring')->update(['is_active' => false]);
        $live = Service::query()->notArchived()->count();
        $first = Service::query()->where('service_type_id', $this->natural->id)->orderBy('sort_order')->orderBy('name')->first();

        $this->actingAs($this->admin)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('services')
                ->has('services.data', 10)
                ->where('services.meta.total', $live)
                ->where('services.data.0.slug', $first?->slug)
                ->where('services.data.0.actions', ['view', 'edit', 'archive'])
                ->where('services.data.0.canToggle', true)
                ->where('filters', ['search' => '', 'page' => 1, 'perPage' => 10, 'type' => 'all', 'status' => 'all'])
                ->has('serviceTypes', 2)
                ->where('serviceTypes.0', ['id' => $this->natural->id, 'slug' => 'natural-stone-applications', 'name' => $this->natural->name, 'isActive' => true])
                ->has('questions', DetailField::query()->count())
                ->has('stoneTypes', StoneType::query()->where('is_active', true)->count())
                ->where('canCreate', true));

        $this->actingAs($this->admin)
            ->get(route('services.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('services.data', fn ($services): bool => collect($services)->doesntContain('slug', 'pedestal')));
    });

    it('searches by name or description and filters by type and status', function (): void {
        Service::query()->where('slug', 'flooring')->update(['is_active' => false]);
        $furniture = ServiceType::query()->where('slug', 'custom-stone-furniture')->sole();
        $slugs = fn (array $query): array => collect($this->actingAs($this->admin)->get(route('services.index', $query))
            ->viewData('page')['props']['services']['data'])->pluck('slug')->all();

        expect($slugs(['search' => 'FLOOR']))->toBe(['flooring'])
            ->and($slugs(['search' => '%']))->toBe([])
            ->and($slugs(['status' => 'inactive']))->toBe(['flooring'])
            ->and($slugs(['type' => 'custom-stone-furniture']))
            ->toBe(Service::query()->where('service_type_id', $furniture->id)->orderBy('sort_order')->orderBy('name')->pluck('slug')->all())
            ->and($slugs(['type' => 'custom-stone-furniture', 'search' => 'flooring']))->toBe([]);

        $this->actingAs($this->admin)
            ->get(route('services.index', ['type' => 'nope', 'status' => 'maybe']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'nope')
                ->where('filters.status', 'all')
                ->has('services.data', 0));
    });

    it('marks a visible service the Inquire page doesn\'t offer', function (): void {
        $this->natural->forceFill(['is_active' => false])->save();

        $this->actingAs($this->admin)
            ->get(route('services.index', ['type' => 'natural-stone-applications', 'search' => 'flooring']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('services.data.0.isActive', true)
                ->where('services.data.0.isOffered', false));
    });

    it('is the admin\'s alone, and so are its writes', function (): void {
        $sales = User::factory()->role(Role::Sales)->create();
        $service = Service::query()->where('slug', 'flooring')->sole();

        $this->actingAs($sales)->get(route('services.index'))->assertForbidden();
        $this->actingAs($sales)->post(route('dashboard.services.store'), serviceForm())->assertForbidden();
        $this->actingAs($sales)->put(route('dashboard.services.update', $service->id), serviceForm())->assertForbidden();
        $this->actingAs($sales)->patch(route('dashboard.services.status', $service->id), ['active' => false])->assertForbidden();
        $this->actingAs($sales)->post(route('dashboard.services.archive', $service->id))->assertForbidden();
        $this->actingAs($sales)->post(route('dashboard.services.lists.store', 'service-types'), ['name' => 'Tiles', 'is_active' => true, 'sort_order' => 3])->assertForbidden();

        expect(Service::query()->where('slug', 'fireplace-surrounds')->exists())->toBeFalse()
            ->and(ServiceType::query()->where('name', 'Tiles')->exists())->toBeFalse()
            ->and($service->refresh()->is_active)->toBeTrue();
    });
});

describe('a row\'s switch', function (): void {
    it('hides a service from the Inquire page and shows it again, audited', function (): void {
        $service = Service::query()->where('slug', 'flooring')->sole();

        $this->actingAs($this->admin)
            ->patch(route('dashboard.services.status', $service->id), ['active' => false])
            ->assertSessionHasNoErrors();

        expect($service->refresh()->is_active)->toBeFalse()
            ->and(offeredServiceSlugs())->not->toContain('flooring');

        $audit = AuditLog::query()->where('event', AuditEvent::StatusChanged)->sole();
        expect($audit->auditable_id)->toBe($service->id);

        $this->actingAs($this->admin)
            ->patch(route('dashboard.services.status', $service->id), ['active' => true])
            ->assertSessionHasNoErrors();

        expect($service->refresh()->is_active)->toBeTrue()
            ->and(offeredServiceSlugs())->toContain('flooring')
            ->and(AuditLog::query()->where('event', AuditEvent::StatusChanged)->count())->toBe(2);
    });

    it('records nothing when it is already so, and wants a yes or no', function (): void {
        $service = Service::query()->where('slug', 'flooring')->sole();

        $this->actingAs($this->admin)->patch(route('dashboard.services.status', $service->id), ['active' => true])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->patch(route('dashboard.services.status', $service->id), [])->assertSessionHasErrors('active');

        expect(AuditLog::query()->where('event', AuditEvent::StatusChanged)->exists())->toBeFalse();
    });

    it('is a 404 for an archived service', function (): void {
        $service = Service::query()->where('slug', 'flooring')->sole();
        $service->forceFill(['is_active' => false, 'archived_at' => now()])->save();

        $this->actingAs($this->admin)->patch(route('dashboard.services.status', $service->id), ['active' => true])->assertNotFound();

        expect($service->refresh()->is_active)->toBeFalse();
    });
});

describe('adding and changing a service', function (): void {
    it('adds it with its lines, stone types and questions in order, and the Inquire page offers it', function (): void {
        $this->actingAs($this->admin)
            ->from(route('services.index'))
            ->post(route('dashboard.services.store'), serviceForm())
            ->assertRedirect(route('services.index'))
            ->assertSessionHasNoErrors();

        $service = Service::query()->where('slug', 'fireplace-surrounds')->sole();

        expect($service->name)->toBe('Fireplace Surrounds')
            ->and($service->service_type_id)->toBe($this->natural->id)
            ->and($service->features->pluck('text')->all())->toBe(['Cut to size', 'Installed on site'])
            ->and($service->stoneTypes->pluck('id')->sort()->values()->all())->toBe(collect([$this->granite->id, $this->marble->id])->sort()->values()->all())
            ->and($service->detailFields->map(fn (DetailField $field): array => [$field->slug, $field->isRequiredByService()])->all())
            ->toBe([['area_sqm', false], ['edge_profile', true]])
            ->and(offeredServiceSlugs())->toContain('fireplace-surrounds');

        $audit = AuditLog::query()->where('auditable_type', $service->getMorphClass())->where('auditable_id', $service->id)->sole();
        expect($audit->event)->toBe(AuditEvent::Created);
    });

    it('numbers the slug when the name\'s is taken, by an archived service too', function (): void {
        Service::query()->where('slug', 'flooring')->update(['is_active' => false, 'archived_at' => now()]);

        $this->actingAs($this->admin)->post(route('dashboard.services.store'), serviceForm(['name' => 'Flooring']))->assertSessionHasNoErrors();

        expect(Service::query()->where('slug', 'flooring-2')->exists())->toBeTrue();
    });

    it('replaces what it includes and asks, and keeps its slug when renamed', function (): void {
        $service = Service::query()->where('slug', 'dining-tables')->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.update', $service->id), serviceForm([
                'name' => 'Dining and Kitchen Tables',
                'service_type_id' => $service->service_type_id,
                'features' => ['Dining tables'],
                'fulfillment' => 'delivery_or_pickup',
                'questions' => [['id' => DetailField::query()->where('slug', 'seaters')->value('id'), 'required' => true]],
            ]))
            ->assertSessionHasNoErrors();

        $service->refresh();

        expect($service->slug)->toBe('dining-tables')
            ->and($service->name)->toBe('Dining and Kitchen Tables')
            ->and($service->features->pluck('text')->all())->toBe(['Dining tables'])
            ->and($service->detailFields->pluck('slug')->all())->toBe(['seaters'])
            ->and(AuditLog::query()->where('event', AuditEvent::Updated)->sole()->old_values['name'])->toBe('Dining Tables');
    });

    it('keeps no stone types for a service that needs no stones', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['requires_stones' => false, 'questions' => []]))
            ->assertSessionHasNoErrors();

        expect(Service::query()->where('slug', 'fireplace-surrounds')->sole()->stoneTypes)->toBeEmpty();
    });

    it('refuses what the Inquire page could not offer or ask', function (array $overrides, string $field, string $message): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Service::query()->where('slug', 'fireplace-surrounds')->exists())->toBeFalse();
    })->with([
        'stones with no stone type' => [['stone_type_ids' => []], 'stone_type_ids', 'Choose the stone types it is made from, or turn off "Needs stones".'],
        'a stone question with no stones' => [['requires_stones' => false], 'questions.1.id', 'This question is answered for each stone. Turn on "Needs stones", or remove it.'],
        'a name its type already has' => [['name' => 'Flooring'], 'name', 'This service type already has a service by that name.'],
        'no description' => [['description' => ' '], 'description', 'Describe the service in a line, as the Inquire page shows it.'],
        'a line twice' => [['features' => ['Cut to size', 'cut to size']], 'features.1', 'That line is already listed.'],
        'too many lines' => [['features' => array_map(fn (int $line): string => "Line {$line}", range(1, 9))], 'features', 'A service can list 8 things it includes at most.'],
        'no way to reach the client' => [['fulfillment' => 'courier'], 'fulfillment', 'Choose how the work reaches the client.'],
    ]);

    it('refuses an archived service type', function (): void {
        $this->natural->forceFill(['archived_at' => now(), 'is_active' => false])->save();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm())
            ->assertSessionHasErrors(['service_type_id' => 'Choose one of the service types.']);
    });
});

describe('choosing questions', function (): void {
    it('asks a question added on the Questions page, in its place, and a client can answer it', function (): void {
        Storage::fake(config('inquiry.disk'));
        $area = DetailField::query()->where('slug', 'area_sqm')->value('id');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Sink cut-out', 'answer_type' => 'choice', 'choices' => ['Single bowl', 'Double bowl']])
            ->assertSessionHasNoErrors();

        $field = DetailField::query()->where('name', 'Sink cut-out')->sole();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['questions' => [
                ['id' => $area, 'required' => false],
                ['id' => $field->id, 'required' => true],
            ]]))
            ->assertSessionHasNoErrors();

        $service = Service::query()->where('slug', 'fireplace-surrounds')->sole();

        expect($service->detailFields->map(fn (DetailField $asked): array => [$asked->slug, $asked->isRequiredByService()])->all())
            ->toBe([['area_sqm', false], ['sink_cut_out', true]]);

        // The Inquire page asks it, and takes a client's answer to it.
        $this->actingAs(inquiryClient())
            ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['serviceId' => 'fireplace-surrounds'])]))
            ->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->detailAnswers()->where('detail_field_id', $field->id)->sole()->option?->name)->toBe('Single bowl');
    });

    it('writes no new question: one is chosen from the list', function (array $question, string $field, string $message): void {
        $before = DetailField::query()->count();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['questions' => [[...$question, 'required' => true]]]))
            ->assertSessionHasErrors([$field => $message]);

        expect(DetailField::query()->count())->toBe($before)
            ->and(Service::query()->where('slug', 'fireplace-surrounds')->exists())->toBeFalse();
    })->with([
        'a written one' => [['name' => 'Sink cut-out', 'answer_type' => 'text'], 'questions.0', 'Choose a question from the list.'],
        'none chosen' => [[], 'questions.0.id', 'Choose a question from the list.'],
        'one not on the list' => [['id' => 999999], 'questions.0.id', 'Choose from the questions listed.'],
    ]);
});

describe('archiving', function (): void {
    it('takes a service off the page and the Inquire page, and its inquiries keep it', function (): void {
        $service = Service::query()->where('slug', 'flooring')->sole();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.archive', $service->id))
            ->assertSessionHasNoErrors();

        $service->refresh();

        expect($service->archived_at)->not->toBeNull()
            ->and($service->is_active)->toBeFalse()
            ->and(offeredServiceSlugs())->not->toContain('flooring')
            ->and(AuditLog::query()->where('event', AuditEvent::Archived)->sole()->auditable_id)->toBe($service->id);

        // Gone from the page: it can no longer be changed or archived again.
        $this->actingAs($this->admin)->put(route('dashboard.services.update', $service->id), serviceForm())->assertNotFound();
        $this->actingAs($this->admin)->post(route('dashboard.services.archive', $service->id))->assertNotFound();
    });

    it('archives a service type only once it has no services left', function (): void {
        $furniture = ServiceType::query()->where('slug', 'custom-stone-furniture')->sole();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['service-types', $furniture->id]))
            ->assertSessionHasErrors(['archive' => 'Custom Stone Furniture can\'t be archived. Move or archive its 5 services first.']);

        expect($furniture->refresh()->archived_at)->toBeNull();

        $furniture->services()->update(['is_active' => false, 'archived_at' => now()]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['service-types', $furniture->id]))
            ->assertSessionHasNoErrors();

        expect($furniture->refresh()->archived_at)->not->toBeNull()
            ->and($furniture->is_active)->toBeFalse();
    });
});

describe('service types', function (): void {
    it('adds one with a slug from its name, and renames one keeping its slug', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'service-types'), ['name' => ' Tiles & Mosaics ', 'is_active' => true, 'sort_order' => 3])
            ->assertSessionHasNoErrors();

        $type = ServiceType::query()->where('name', 'Tiles & Mosaics')->sole();
        expect($type->slug)->toBe('tiles-mosaics');

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['service-types', $type->id]), ['name' => 'Tiles', 'is_active' => false, 'sort_order' => 4])
            ->assertSessionHasNoErrors();

        expect($type->refresh()->only(['name', 'slug', 'is_active', 'sort_order']))
            ->toBe(['name' => 'Tiles', 'slug' => 'tiles-mosaics', 'is_active' => false, 'sort_order' => 4]);
    });

    it('takes a hidden type\'s services off the Inquire page', function (): void {
        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['service-types', $this->natural->id]), ['name' => $this->natural->name, 'is_active' => false, 'sort_order' => 0])
            ->assertSessionHasNoErrors();

        expect(offeredServiceSlugs())->not->toContain('flooring')
            ->and(offeredServiceSlugs())->toContain('dining-tables');
    });

    it('refuses a name another type has', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'service-types'), ['name' => 'Custom Stone Furniture', 'is_active' => true, 'sort_order' => 3])
            ->assertSessionHasErrors(['name' => 'There is already a service type by that name, or an archived one had it.']);
    });
});
