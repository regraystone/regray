<?php

use App\Enums\AuditEvent;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

/** A library choice (the Choices page), added as the Choices page adds one. */
function libraryChoice(string $name, array $attributes = []): Choice
{
    return Choice::query()->create([
        'slug' => Choice::uniqueSlug($name),
        'name' => $name,
        'is_active' => true,
        'sort_order' => 90,
        ...$attributes,
    ]);
}

/**
 * A question's library choices, by id, in its order.
 *
 * @return list<int>
 */
function questionChoiceIds(DetailField $question): array
{
    return DetailFieldOption::query()->where('detail_field_id', $question->id)->whereNull('archived_at')->whereNotNull('choice_id')->orderBy('sort_order')->pluck('choice_id')->all();
}

/** A project question no service asks. */
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
    it('adds a list question, asked once for the project, listing choices from the library', function (): void {
        $kitchen = libraryChoice('Kitchen', ['description' => 'Where you cook.']);
        $bathroom = libraryChoice('Bathroom');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => ' Which room? ', 'answer_type' => 'choice', 'choices' => [(string) $kitchen->id, (string) $bathroom->id]])
            ->assertSessionHasNoErrors();

        $question = DetailField::query()->where('name', 'Which room?')->sole();

        expect($question->scope)->toBe(DetailFieldScope::Project)
            ->and($question->answer_type)->toBe(DetailAnswerType::Choice)
            ->and($question->allows_custom)->toBeFalse()
            ->and($question->slug)->toBe('which_room')
            // Its options are the library's choices, with what the client sees of them.
            ->and($question->closedOptions()->map->only(['slug', 'name', 'description', 'choice_id'])->all())->toBe([
                ['slug' => 'kitchen', 'name' => 'Kitchen', 'description' => 'Where you cook.', 'choice_id' => $kitchen->id],
                ['slug' => 'bathroom', 'name' => 'Bathroom', 'description' => null, 'choice_id' => $bathroom->id],
            ])
            ->and(AuditLog::query()->where('event', AuditEvent::Created)->sole()->new_values['choices'])->toBe(['Kitchen', 'Bathroom']);
    });

    it('offers the library\'s choices to pick, and links to the Choices page for a new one', function (): void {
        $question = unaskedQuestion('Which room?', DetailAnswerType::Choice);
        $question->writeChoices([libraryChoice('Kitchen')->id, libraryChoice('Bathroom')->id]);
        libraryChoice('Gone')->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', ['list' => 'questions', 'search' => 'Which room']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('list.fields.2.name', 'choices')
                ->where('list.fields.2.type', 'multiselect')
                ->where('list.fields.2.addLink', ['list' => 'choices', 'label' => 'Add a new choice'])
                ->where('list.fields.2.options', fn ($options): bool => collect($options)->pluck('label')->contains('Kitchen')
                    && collect($options)->pluck('label')->contains('Not sure yet, advise me')
                    && ! collect($options)->pluck('label')->contains('Gone'))
                ->where('items.data.0.values.choices', Choice::query()->whereIn('name', ['Kitchen', 'Bathroom'])->orderBy('id')->pluck('id')->all()));
    });

    it('wants two choices for a list, one custom and one "advise me" at most, and a name no other question has', function (): void {
        $kitchen = libraryChoice('Kitchen');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Which room?', 'answer_type' => 'choice', 'choices' => [$kitchen->id]])
            ->assertSessionHasErrors(['choices' => 'List at least two choices.']);

        $customs = Choice::query()->whereIn('slug', ['custom-shape', 'custom-design'])->pluck('id')->all();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Which room?', 'answer_type' => 'choice', 'choices' => [$kitchen->id, ...$customs]])
            ->assertSessionHasErrors(['choices' => 'List one custom choice at most.']);

        $gone = libraryChoice('Gone');
        $gone->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Which room?', 'answer_type' => 'choice', 'choices' => [$kitchen->id, $gone->id]])
            ->assertSessionHasErrors(['choices.1' => 'One of those choices is no longer in the library. Remove it.']);

        $taken = DetailField::query()->where('slug', 'seaters')->value('name');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => mb_strtoupper($taken), 'answer_type' => 'number'])
            ->assertSessionHasErrors(['name' => 'Another question, maybe an archived one, already reads that way.']);
    });

    it('changes a list\'s choices, keeping the option past answers chose', function (): void {
        [$kitchen, $bathroom, $lanai] = [libraryChoice('Kitchen'), libraryChoice('Bathroom'), libraryChoice('Lanai')];
        $question = unaskedQuestion('Which room?', DetailAnswerType::Choice);
        $question->writeChoices([$kitchen->id, $bathroom->id]);
        $kitchenOption = DetailFieldOption::query()->where('detail_field_id', $question->id)->where('choice_id', $kitchen->id)->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $question->id]), ['name' => 'Which room is it?', 'answer_type' => 'text', 'choices' => [$lanai->id, $kitchen->id]])
            ->assertSessionHasNoErrors();

        $question->refresh();
        $bathroomOption = DetailFieldOption::query()->where('detail_field_id', $question->id)->where('choice_id', $bathroom->id)->sole();

        expect($question->name)->toBe('Which room is it?')
            // What kind of answer it takes stays.
            ->and($question->answer_type)->toBe(DetailAnswerType::Choice)
            ->and($question->closedOptions()->pluck('name')->all())->toBe(['Lanai', 'Kitchen'])
            ->and($question->closedOptions()->firstWhere('name', 'Kitchen')?->id)->toBe($kitchenOption->id)
            // Taken off the question, kept for the answers that chose it; still in the library.
            ->and($bathroomOption->archived_at)->not->toBeNull()
            ->and($bathroom->refresh()->archived_at)->toBeNull();

        // Put back, it is the same option again.
        $question->writeChoices([$kitchen->id, $bathroom->id]);

        expect($question->refresh()->closedOptions()->firstWhere('name', 'Bathroom')?->id)->toBe($bathroomOption->id);
    });

    it('leaves the edge profiles\' question\'s choices alone', function (): void {
        $edge = DetailField::query()->where('slug', DetailField::EDGE_PROFILE)->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $edge->id]), ['name' => 'Edge', 'choices' => [1, 2]])
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

describe('choices', function (): void {
    /** A list question's option, by its question's slug and its own. */
    $option = fn (string $question, string $slug): DetailFieldOption => DetailFieldOption::query()
        ->whereHas('detailField', fn ($fields) => $fields->where('slug', $question))
        ->where('slug', $slug)
        ->sole();
    $choice = fn (string $slug): Choice => Choice::query()->where('slug', $slug)->sole();

    it('lists the library, with each choice\'s picture, kind and the services it is offered for', function (): void {
        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', ['list' => 'choices', 'search' => 'advise me']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('catalog-list')
                ->where('list.section', 'services')
                ->where('list.fields.0.name', 'name')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Not sure yet, advise me')
                ->where('list.columns', [['key' => 'kind', 'header' => 'Kind'], ['key' => 'services', 'header' => 'Offered for'], ['key' => 'order', 'header' => 'Order']])
                ->where('items.data.0.cells.kind', 'Advise me')
                ->where('items.data.0.cells.services', 'Every service asking it'));

        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', ['list' => 'choices', 'search' => 'C-Legs V1']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.thumbnail', '/storage/question-choices/9ab33da52cced2c01686b459a63b3dea.jpg'));
    });

    it('adds a pictured choice to the library, offered for one service, which a question then lists for that service alone', function (): void {
        Storage::fake(config('filesystems.public_disk'));
        $center = Service::query()->where('slug', 'center-tables')->sole();
        $dining = Service::query()->where('slug', 'dining-tables')->sole();

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'choices'), [
                'name' => ' Kidney ',
                'description' => 'A curved, bean-shaped top.',
                'thumbnail' => UploadedFile::fake()->image('kidney.jpg', 400, 300),
                'kind' => 'choice',
                'size_kind' => 'free',
                'services' => [$center->id],
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $kidney = Choice::query()->where('name', 'Kidney')->sole();

        expect($kidney->slug)->toBe('kidney')
            ->and($kidney->thumbnail_path)->toStartWith('question-choices/')
            ->and($kidney->questions)->toBeEmpty();

        $shape = DetailField::query()->where('slug', 'table_shape')->sole();
        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $shape->id]), ['name' => $shape->name, 'choices' => [...questionChoiceIds($shape), $kidney->id]])
            ->assertSessionHasNoErrors();

        $shape = $shape->fresh()->load('options.services');
        $listed = $shape->closedOption('kidney');

        expect($listed?->only(['name', 'description', 'thumbnail_path']))->toBe(['name' => 'Kidney', 'description' => 'A curved, bean-shaped top.', 'thumbnail_path' => $kidney->thumbnail_path])
            ->and($shape->closedOptions($center->id)->pluck('slug'))->toContain('kidney')
            ->and($shape->closedOptions($dining->id)->pluck('slug'))->not->toContain('kidney');
    });

    it('shows a change on every question listing the choice', function () use ($choice, $option): void {
        $advise = $choice('not-sure');

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $advise->id]), ['name' => 'Help me decide', 'description' => 'We will suggest one.', 'kind' => 'advisory', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        foreach (['table_shape', 'table_base_design_center', 'table_base_design_side', 'table_base_design_dining'] as $question) {
            expect($option($question, 'not-sure')->only(['name', 'description', 'is_advisory']))->toBe(['name' => 'Help me decide', 'description' => 'We will suggest one.', 'is_advisory' => true]);
        }
    });

    it('keeps names apart, a question to one custom and one "advise me" choice, and "Same as stone" a choice', function () use ($choice): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'choices'), ['name' => 'round', 'kind' => 'choice', 'is_active' => '1'])
            ->assertSessionHasErrors(['name' => 'Another choice, maybe an archived one, already reads that way.']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $choice('round')->id]), ['name' => 'Round', 'kind' => 'custom', 'is_active' => '1'])
            ->assertSessionHasErrors(['kind' => 'Table shape already lists a custom choice.']);

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $choice('wood')->id]), ['name' => 'Wood', 'kind' => 'advisory', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $choice('same-as-stone')->id]), ['name' => 'Same as stone', 'kind' => 'custom', 'is_active' => '1'])
            ->assertSessionHasErrors(['kind' => '"Same as stone" stays an ordinary choice.']);

        expect($choice('round')->is_custom)->toBeFalse()
            ->and($choice('wood')->is_advisory)->toBeTrue();
    });

    it('saves how a table shape asks the size, on Table shape\'s option alone', function () use ($choice, $option): void {
        $round = $choice('round');
        $uBase = $choice('u-base');

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $round->id]), ['name' => 'Round', 'kind' => 'choice', 'size_kind' => 'free', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $uBase->id]), ['name' => 'U-Base', 'kind' => 'choice', 'size_kind' => 'diameter', 'is_active' => '1'])
            ->assertSessionHasNoErrors();

        expect($round->refresh()->size_kind)->toBeNull()
            ->and($option('table_shape', 'round')->size_kind)->toBeNull()
            ->and($uBase->refresh()->size_kind)->toBe('diameter')
            // A base design asks no size.
            ->and($option('table_base_design_center', 'u-base')->size_kind)->toBeNull();
    });

    it('archives a choice: it leaves the library and every question, and past answers keep it', function () use ($choice, $option): void {
        $uBase = $choice('u-base');

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.archive', ['choices', $uBase->id]))
            ->assertSessionHasNoErrors();

        $listed = $option('table_base_design_center', 'u-base');
        $design = DetailField::query()->where('slug', 'table_base_design_center')->with('options.services')->sole();

        expect($uBase->refresh()->archived_at)->not->toBeNull()
            ->and($uBase->is_active)->toBeFalse()
            ->and($listed->archived_at)->not->toBeNull()
            ->and($design->closedOptions()->pluck('slug'))->not->toContain('u-base')
            // Still there for a sent answer to read.
            ->and($design->closedOption('u-base')?->id)->toBe($listed->id);

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['choices', $uBase->id]), ['name' => 'U-Base', 'kind' => 'choice', 'is_active' => '1'])
            ->assertNotFound();
    });

    it('switched off, it is no longer offered on any question listing it', function () use ($choice, $option): void {
        $this->actingAs($this->admin)
            ->patch(route('dashboard.services.lists.status', ['choices', $choice('round')->id]), ['active' => false])
            ->assertSessionHasNoErrors();

        expect($option('table_shape', 'round')->is_active)->toBeFalse();
    });
});

describe('adding what another page\'s form needs', function (): void {
    it('goes back to the form that asked, with the new item picked', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'choices'), ['name' => 'Kidney', 'kind' => 'choice', 'is_active' => '1', 'back' => '/dashboard/services/questions?reopen=1'])
            ->assertRedirect('/dashboard/services/questions?reopen=1&picked='.Choice::query()->where('name', 'Kidney')->value('id'));

        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Room size', 'answer_type' => 'text', 'back' => '/dashboard/services?reopen=1'])
            ->assertRedirect('/dashboard/services?reopen=1&picked='.DetailField::query()->where('name', 'Room size')->value('id'));
    });

    it('goes back only to the dashboard', function (string $back): void {
        $this->actingAs($this->admin)
            ->from(route('dashboard.services.lists.index', 'choices'))
            ->post(route('dashboard.services.lists.store', 'choices'), ['name' => 'Kidney', 'kind' => 'choice', 'is_active' => '1', 'back' => $back])
            ->assertRedirect(route('dashboard.services.lists.index', 'choices'));
    })->with(['https://example.com/dashboard/x', '//example.com/dashboard/', '/login', '/dashboard\\..\\x']);
});
