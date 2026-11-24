<?php

use App\Enums\AuditEvent;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The lists under Services, each its own page as Products' lists are:
 * Service types and Questions, on the one list page (CatalogListController)
 * with services' permissions.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->admin = User::factory()->role(Role::Admin)->create();
});

/** A project question no service asks, with these choices. */
function unaskedQuestion(string $name = 'Room size', DetailAnswerType $type = DetailAnswerType::Text): DetailField
{
    return DetailField::query()->create([
        'slug' => str($name)->slug('_')->value(),
        'name' => $name,
        'scope' => DetailFieldScope::Project,
        'answer_type' => $type,
        'allows_custom' => $type !== DetailAnswerType::Choice,
        'sort_order' => 99,
    ]);
}

describe('the pages', function (): void {
    it('shows the service types, but the archived, with services\' words for the switch', function (): void {
        ServiceType::query()->create(['slug' => 'old', 'name' => 'Old', 'sort_order' => 9, 'is_active' => false])
            ->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', 'service-types'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog-list')
                ->where('list.section', 'services')
                ->where('list.statusWords.on', 'Visible')
                ->where('list.statusWords.off', 'Hidden')
                ->has('items.data', 2)
                ->where('items.data.0.name', 'Natural Stone & Applications')
                ->where('items.data.0.cells.services', '9 services')
                ->where('items.data.0.actions', ['edit', 'archive'])
                ->where('canCreate', true));
    });

    it('shows the questions, with what kind of answer each takes and where it is asked', function (): void {
        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', ['list' => 'questions', 'search' => 'edge']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog-list')
                ->where('list.hasStatus', false)
                ->has('items.data', 1)
                ->where('items.data.0.cells.answer', 'Choose from a list')
                ->where('items.data.0.cells.asked', 'On each stone')
                // Its choices are the edge profiles, so the page doesn't edit them.
                ->where('items.data.0.fixed', ['answer_type', 'choices'])
                ->where('items.data.0.values.choices', []));
    });

    it('is under Services alone, with services\' permissions', function (): void {
        $sales = User::factory()->role(Role::Sales)->create();

        $this->actingAs($this->admin)->get('/dashboard/products/questions')->assertNotFound();
        // A product list isn't one of Services' (405: a service's own PUT route has that shape).
        expect($this->actingAs($this->admin)->get('/dashboard/services/finishes')->status())->toBeIn([404, 405]);
        $this->actingAs($sales)->get(route('dashboard.services.lists.index', 'questions'))->assertForbidden();
        $this->actingAs($sales)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Room size', 'answer_type' => 'text'])
            ->assertForbidden();

        expect(DetailField::query()->where('name', 'Room size')->exists())->toBeFalse();
    });
});

describe('service types', function (): void {
    it('hides a type and its services from the Inquire page with its switch', function (): void {
        $natural = ServiceType::query()->where('slug', 'natural-stone-applications')->sole();

        $this->actingAs($this->admin)
            ->patch(route('dashboard.services.lists.status', ['service-types', $natural->id]), ['active' => false])
            ->assertSessionHasNoErrors();

        expect($natural->refresh()->is_active)->toBeFalse()
            ->and(AuditLog::query()->where('event', AuditEvent::StatusChanged)->sole()->auditable_id)->toBe($natural->id);
    });

    it('leaves the page once archived, and can no longer be changed', function (): void {
        $type = ServiceType::query()->create(['slug' => 'tiles', 'name' => 'Tiles', 'sort_order' => 5, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['service-types', $type->id]))
            ->assertSessionHasNoErrors();

        expect($type->refresh()->archived_at)->not->toBeNull()
            ->and($type->is_active)->toBeFalse()
            ->and(AuditLog::query()->where('event', AuditEvent::Archived)->sole()->auditable_id)->toBe($type->id);

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['service-types', $type->id]), ['name' => 'Tiles', 'is_active' => true])
            ->assertNotFound();
    });

    it('refuses a service saved under a type archived by now', function (): void {
        $type = ServiceType::query()->create(['slug' => 'tiles', 'name' => 'Tiles', 'sort_order' => 5, 'is_active' => true]);
        $type->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['service_type_id' => $type->id]))
            ->assertSessionHasErrors('service_type_id');
    });
});

describe('questions', function (): void {
    it('adds a list question, asked once for the project, with its choices', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => ' Which room? ', 'answer_type' => 'choice', 'choices' => [' Kitchen', 'Bathroom ']])
            ->assertSessionHasNoErrors();

        $question = DetailField::query()->where('name', 'Which room?')->sole();

        expect($question->scope)->toBe(DetailFieldScope::Project)
            ->and($question->answer_type)->toBe(DetailAnswerType::Choice)
            ->and($question->allows_custom)->toBeFalse()
            ->and($question->slug)->toBe('which_room')
            ->and($question->closedOptions()->pluck('name')->all())->toBe(['Kitchen', 'Bathroom'])
            ->and(AuditLog::query()->where('event', AuditEvent::Created)->sole()->new_values['choices'])->toBe(['Kitchen', 'Bathroom']);
    });

    it('wants two choices for a list, and a name no other question has', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Which room?', 'answer_type' => 'choice', 'choices' => ['Kitchen']])
            ->assertSessionHasErrors(['choices' => 'List at least two choices.']);

        $taken = DetailField::query()->where('slug', 'seaters')->value('name');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => mb_strtoupper($taken), 'answer_type' => 'number'])
            ->assertSessionHasErrors(['name' => 'Another question, maybe an archived one, already reads that way.']);
    });

    it('changes a list\'s choices, keeping the one past answers chose', function (): void {
        $question = unaskedQuestion('Which room?', DetailAnswerType::Choice);
        $question->writeChoices(['Kitchen', 'Bathroom']);
        $kitchen = DetailFieldOption::query()->where('detail_field_id', $question->id)->where('name', 'Kitchen')->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $question->id]), ['name' => 'Which room is it?', 'answer_type' => 'text', 'choices' => ['Lanai', 'kitchen']])
            ->assertSessionHasNoErrors();

        $question->refresh();
        $options = DetailFieldOption::query()->where('detail_field_id', $question->id)->get();

        expect($question->name)->toBe('Which room is it?')
            // What kind of answer it takes stays.
            ->and($question->answer_type)->toBe(DetailAnswerType::Choice)
            ->and($question->closedOptions()->pluck('name')->all())->toBe(['Lanai', 'kitchen'])
            ->and($question->closedOptions()->firstWhere('name', 'kitchen')?->id)->toBe($kitchen->id)
            ->and($options->firstWhere('name', 'Bathroom')?->is_active)->toBeFalse();
    });

    it('leaves the edge profiles\' question\'s choices alone', function (): void {
        $edge = DetailField::query()->where('slug', DetailField::EDGE_PROFILE)->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $edge->id]), ['name' => 'Edge', 'choices' => ['Bullnose', 'Bevel']])
            ->assertSessionHasNoErrors();

        expect($edge->refresh()->name)->toBe('Edge')
            ->and(DetailFieldOption::query()->where('detail_field_id', $edge->id)->count())->toBe(0);
    });

    it('archives a question no service asks, and services can no longer ask it', function (): void {
        $question = unaskedQuestion();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['questions', $question->id]))
            ->assertSessionHasNoErrors();

        expect($question->refresh()->archived_at)->not->toBeNull();

        $this->actingAs($this->admin)
            ->get(route('services.index'))
            ->assertInertia(fn (Assert $page) => $page->where('questions', fn ($questions): bool => collect($questions)->doesntContain('id', $question->id)));

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['questions' => [['id' => $question->id, 'required' => true]]]))
            ->assertSessionHasErrors(['questions.0.id' => 'Choose from the questions listed.']);
    });

    it('refuses to archive a question a service asks, or a stone question', function (): void {
        $seaters = DetailField::query()->where('slug', 'seaters')->sole();
        $asking = Service::query()->notArchived()->whereHas('detailFields', fn ($fields) => $fields->whereKey($seaters->id))->count();
        $quantity = DetailField::query()->where('slug', DetailField::QUANTITY)->sole();
        $quantity->services()->detach();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['questions', $seaters->id]))
            ->assertSessionHasErrors(['archive' => "{$seaters->name} can't be archived. Asked by {$asking} ".($asking === 1 ? 'service' : 'services').'. Take it off their questions first.']);

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['questions', $quantity->id]))
            ->assertSessionHasErrors(['archive' => "{$quantity->name} can't be archived. Every stone row has a place for its answer, so it stays on the list."]);

        expect($seaters->refresh()->archived_at)->toBeNull()
            ->and($quantity->refresh()->archived_at)->toBeNull();
    });
});
