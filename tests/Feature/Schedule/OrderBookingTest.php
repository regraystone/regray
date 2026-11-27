<?php

use App\Enums\InquiryStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleStatus;
use App\Models\Inquiry;
use App\Models\Schedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A client books the installation or the delivery of one of their accepted
 * orders from the Schedule page: an installation for a service the team
 * installs, a delivery for one they chose to have delivered. It goes straight
 * to the sales member handling the order to confirm, or to marketing when the
 * order has none, and an order has one open of each at most. "Now" is
 * Wednesday 14 October 2026, 13:05 in Manila.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    seedInquiryCatalog();
    $this->travelTo(CarbonImmutable::parse('2026-10-14 13:05', 'Asia/Manila'));
    $this->client = inquiryClient();
});

/**
 * An order booking in the shape the page posts, with `$overrides`.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function orderBookingInput(string $kind, string $reference, array $overrides = []): array
{
    return [
        'kind' => $kind,
        'orderReference' => $reference,
        'date' => '2026-10-15',
        'arrivalTime' => '10:00',
        'notes' => 'Parking at the back',
        ...$overrides,
    ];
}

/** An accepted order of the client's for a service the team installs. */
function installedOrder(User $client, array $attributes = []): Inquiry
{
    return makeInquiry($client, ['name' => 'Kitchen countertop', 'service' => 'slab-supply-and-installation'], [
        'reference' => 'INQ-I1',
        'status' => InquiryStatus::OrderReceived,
        ...$attributes,
    ]);
}

/** An accepted order of the client's they chose to have delivered. */
function deliveredOrder(User $client, array $attributes = []): Inquiry
{
    return makeInquiry($client, ['name' => 'Dining slabs', 'service' => 'slab-supply-only'], [
        'reference' => 'INQ-D1',
        'status' => InquiryStatus::OrderReceived,
        ...$attributes,
    ]);
}

it('sends an installation straight to the sales member handling the order, awaiting their confirmation', function (): void {
    $order = installedOrder($this->client);
    $sales = User::factory()->role(Role::Sales)->create(['name' => 'Sam']);
    assignInquiry($order, $sales);

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1'))
        ->assertSessionHasNoErrors();

    $schedule = Schedule::query()->sole();

    expect($schedule->kind)->toBe(ScheduleKind::Installation)
        ->and($schedule->order_id)->toBe($order->id)
        ->and($schedule->inquiry_id)->toBeNull()
        ->and($schedule->client_id)->toBe($this->client->id)
        ->and($schedule->title)->toBe('Installation: Kitchen countertop')
        ->and($schedule->notes)->toBe('Parking at the back')
        ->and($schedule->status)->toBe(ScheduleStatus::AwaitingSales)
        ->and($schedule->assigned_to)->toBe($sales->id)
        ->and(notificationBodies($this->client))->toBe(['Your installation request for INQ-I1 on Oct 15, 2026 at 10:00 AM has been sent.'])
        ->and(notificationBodies($sales))->toBe([$this->client->name.' has asked for the installation of INQ-I1 on Oct 15, 2026 at 10:00 AM. Confirm or decline it.']);
});

it('opens booking only once an order won by sales has been paid', function (): void {
    $order = installedOrder($this->client, ['status' => InquiryStatus::Won]);

    // Won, but no payment yet: not offered, and refused.
    $this->actingAs($this->client)
        ->get(route('profile.schedule.book'))
        ->assertInertia(fn (Assert $page) => $page->where('orders', []));
    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1'))
        ->assertSessionHasErrors(['orderReference' => 'Choose one of your accepted orders']);

    // The first payment starts it at order received: now it may be booked.
    showInquiryAs($order, InquiryStatus::OrderReceived)->forceFill(['payment_status' => PaymentStatus::Partial])->save();

    $this->actingAs($this->client)
        ->get(route('profile.schedule.book'))
        ->assertInertia(fn (Assert $page) => $page->where('orders.0.reference', 'INQ-I1'));
    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1'))
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->sole()->kind)->toBe(ScheduleKind::Installation);
});

it('books a delivery with no purpose asked, and one for an order no sales member handles waits for marketing', function (): void {
    deliveredOrder($this->client);

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('delivery', 'INQ-D1', ['purpose' => '']))
        ->assertSessionHasNoErrors();

    $schedule = Schedule::query()->sole();

    expect($schedule->kind)->toBe(ScheduleKind::Delivery)
        ->and($schedule->title)->toBe('Delivery: Dining slabs')
        ->and($schedule->status)->toBe(ScheduleStatus::Pending)
        ->and($schedule->assigned_to)->toBeNull();
});

it('keeps a showroom visit as before, with its purpose', function (): void {
    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), ['date' => '2026-10-15', 'arrivalTime' => '10:00', 'purpose' => 'See the marble', 'notes' => ''])
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->sole())
        ->kind->toBe(ScheduleKind::ShowroomVisit)
        ->order_id->toBeNull()
        ->title->toBe('See the marble');
});

it('refuses an order that is not theirs, not accepted, closed, or does not take what was asked', function (callable $makeOrder, string $kind, string $message): void {
    $makeOrder($this->client);

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput($kind, 'INQ-X1'))
        ->assertSessionHasErrors(['orderReference' => $message]);

    expect(Schedule::query()->count())->toBe(0);
})->with([
    'someone else\'s' => [fn (User $client) => installedOrder(inquiryClient(), ['reference' => 'INQ-X1']), 'installation', 'Choose one of your accepted orders'],
    'only quoted' => [fn (User $client) => installedOrder($client, ['reference' => 'INQ-X1', 'status' => InquiryStatus::Quoted]), 'installation', 'Choose one of your accepted orders'],
    'closed' => [fn (User $client) => installedOrder($client, ['reference' => 'INQ-X1', 'status' => InquiryStatus::Closed]), 'installation', 'Choose one of your accepted orders'],
    'an installation for a delivery' => [fn (User $client) => deliveredOrder($client, ['reference' => 'INQ-X1']), 'installation', 'This order has no installation. Choose another order.'],
    'a delivery for an installation' => [fn (User $client) => installedOrder($client, ['reference' => 'INQ-X1']), 'delivery', 'This order is not for delivery. Choose another order.'],
]);

it('asks for the order, and refuses an unknown kind', function (): void {
    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', ''))
        ->assertSessionHasErrors(['orderReference' => 'Choose one of your accepted orders']);

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('pickup', 'INQ-I1'))
        ->assertSessionHasErrors(['kind' => 'Choose what you would like to schedule']);
});

it('takes one open installation per order, and another once that one is cancelled', function (): void {
    installedOrder($this->client);
    $this->actingAs($this->client)->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1'));

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1', ['date' => '2026-10-16']))
        ->assertSessionHasErrors(['orderReference' => 'This order already has an installation booked. You can change it from your schedule.']);

    Schedule::query()->sole()->forceFill(['status' => ScheduleStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'Moved'])->save();

    $this->actingAs($this->client)
        ->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1', ['date' => '2026-10-16']))
        ->assertSessionHasNoErrors();

    expect(Schedule::query()->count())->toBe(2);
});

it('offers the client their accepted orders with what each may still be booked for', function (): void {
    installedOrder($this->client);
    deliveredOrder($this->client);
    installedOrder($this->client, ['reference' => 'INQ-Q1', 'status' => InquiryStatus::Quoted]);
    installedOrder(inquiryClient(), ['reference' => 'INQ-O1']);
    $this->actingAs($this->client)->post(route('schedule.book.store'), orderBookingInput('delivery', 'INQ-D1'));

    $this->actingAs($this->client)
        ->get(route('profile.schedule.book'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders', [
                ['reference' => 'INQ-I1', 'name' => 'Kitchen countertop', 'service' => 'Slab Supply and Installation', 'kinds' => ['installation']],
            ]));
});

it('shows each booking\'s kind and order on the Schedule page', function (): void {
    installedOrder($this->client);
    $this->actingAs($this->client)->post(route('schedule.book.store'), orderBookingInput('installation', 'INQ-I1'));

    $this->actingAs($this->client)
        ->get(route('profile.schedule', ['from' => '2026-10-11', 'to' => '2026-10-18']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('schedules.0.kind', 'installation')
            ->where('schedules.0.orderReference', 'INQ-I1'));
});
