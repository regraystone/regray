<?php

use App\Enums\DetailFieldScope;
use App\Enums\Role;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The admin keeps what the Inquire page says around a service's questions:
 * a service's delivery and date lines and its fewest days ahead (the
 * service form), and a free entry's suggested answers and what its own
 * answer reads (the Questions page).
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->admin = User::factory()->role(Role::Admin)->create();
});

describe('a service', function (): void {
    it('keeps its delivery and date lines and its fewest days ahead', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm([
                'fulfillment' => 'delivery_or_pickup',
                'delivery_note' => ' Within Metro Manila: ₱1,500. Outside it: quoted by location. ',
                'schedule_note' => 'Ready in 10 days.',
                'lead_days' => 10,
            ]))
            ->assertSessionHasNoErrors();

        expect(Service::query()->where('slug', 'fireplace-surrounds')->sole()->only(['delivery_note', 'schedule_note', 'lead_days']))
            ->toBe(['delivery_note' => 'Within Metro Manila: ₱1,500. Outside it: quoted by location.', 'schedule_note' => 'Ready in 10 days.', 'lead_days' => 10]);
    });

    it('keeps no delivery line for an installed service, and none for no fewest days', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['delivery_note' => 'Free delivery', 'lead_days' => 0]))
            ->assertSessionHasNoErrors();

        expect(Service::query()->where('slug', 'fireplace-surrounds')->sole()->only(['delivery_note', 'lead_days']))
            ->toBe(['delivery_note' => null, 'lead_days' => null]);
    });

    it('refuses fewest days that are not a whole number up to a year', function (mixed $days, string $message): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.store'), serviceForm(['lead_days' => $days]))
            ->assertSessionHasErrors(['lead_days' => $message]);
    })->with([
        'a fraction' => ['2.5', 'Enter a whole number of days.'],
        'below zero' => [-1, 'Enter 0 or more days.'],
        'over a year' => [366, 'Enter 365 days or fewer.'],
    ]);

    it('lists them on the services page for the form', function (): void {
        $this->actingAs($this->admin)
            ->get(route('services.index', ['search' => 'Dining']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('services.data.0.slug', 'dining-tables')
                ->where('services.data.0.leadDays', 5)
                ->where('limits.noteMax', 500));
    });
});

describe('a free entry question', function (): void {
    it('keeps its suggested answers, each with its note, and what its own answer reads', function (): void {
        $seaters = DetailField::query()->where('slug', 'seaters')->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $seaters->id]), [
                'name' => $seaters->name,
                'suggestions' => ['4 | approx. 1.2 sqm', '6', '20 | a banquet'],
                'custom_label' => 'Another number',
                'custom_hint' => 'How many will sit at it?',
            ])
            ->assertSessionHasNoErrors();

        $seaters->refresh();

        expect($seaters->only(['custom_label', 'custom_hint']))->toBe(['custom_label' => 'Another number', 'custom_hint' => 'How many will sit at it?'])
            ->and($seaters->suggestions()->map(fn (DetailFieldOption $option): array => [$option->name, $option->description])->all())
            ->toBe([['4', 'approx. 1.2 sqm'], ['6', null], ['20', 'a banquet']])
            // One left out is switched off, as a sent answer may read it.
            ->and(DetailFieldOption::query()->where('detail_field_id', $seaters->id)->where('slug', '2')->value('is_active'))->toBeFalse();
    });

    it('refuses a number question\'s answer that is no number', function (): void {
        $seaters = DetailField::query()->where('slug', 'seaters')->sole();

        $this->actingAs($this->admin)
            ->put(route('dashboard.services.lists.update', ['questions', $seaters->id]), ['name' => $seaters->name, 'suggestions' => ['many | a crowd']])
            ->assertSessionHasErrors(['suggestions.0' => 'A number question\'s answers are numbers above 0, such as 4.']);
    });

    it('adds a written question with its suggestions', function (): void {
        $this->actingAs($this->admin)
            ->post(route('dashboard.services.lists.store', 'questions'), ['name' => 'Room size', 'answer_type' => 'text', 'suggestions' => ['Small | under 10 sqm', 'Large'], 'custom_label' => 'Custom size'])
            ->assertSessionHasNoErrors();

        $question = DetailField::query()->where('name', 'Room size')->sole();

        expect($question->custom_label)->toBe('Custom size')
            ->and($question->suggestions()->pluck('name')->all())->toBe(['Small', 'Large']);
    });

    it('keeps a stone question\'s suggestions to its stone\'s own sizes', function (): void {
        $this->actingAs($this->admin)
            ->get(route('dashboard.services.lists.index', ['list' => 'questions', 'search' => 'slab']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('items.data.0.values.custom_label', 'Custom size')
                ->where('items.data.0.fixed', ['answer_type', 'choices', 'suggestions']));

        expect(DetailField::query()->where('slug', 'slab_size')->sole()->scope)->toBe(DetailFieldScope::Stone);
    });
});
