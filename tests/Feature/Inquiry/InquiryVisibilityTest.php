<?php

use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/*
 * Which inquiries each staff member reads: admin and owner every one,
 * marketing its leads' and those it opened, sales those it opened and is
 * assigned to, technical, accounting and logistics those they are assigned
 * to. Every staff read and write follows it, and one they do not read is a
 * 404, the same as one that does not exist.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Storage::fake(config('inquiry.disk'));
});

it('lists exactly the inquiries each role reads on the Inquiry list', function (Role $role, array $visible): void {
    $world = visibilityWorld();

    $rows = $this->actingAs($world['users'][$role->value])->get(route('inquiry.index'))
        ->assertOk()
        ->viewData('page')['props']['inquiries']['data'];

    expect(array_column($rows, 'reference'))->toEqualCanonicalizing(referencesOf($world, $visible));
})->with([
    'admin, every one' => [Role::Admin, ['lead', 'created', 'foreign', 'salesCreated', 'salesAssigned', 'assigned']],
    'owner, every one' => [Role::Owner, ['lead', 'created', 'foreign', 'salesCreated', 'salesAssigned', 'assigned']],
    'marketing, its lead\'s and those it opened' => [Role::Marketing, ['lead', 'created']],
    'sales, those it opened and is assigned to' => [Role::Sales, ['salesCreated', 'salesAssigned']],
    'technical, those it is assigned to' => [Role::Technical, ['assigned']],
    'accounting, those it is assigned to' => [Role::Accounting, ['assigned']],
    'logistics, those it is assigned to' => [Role::Logistics, ['assigned']],
]);

it('opens the details of an inquiry the role reads, and 404s one it does not', function (Role $role, string $visible, string $hidden): void {
    $world = visibilityWorld();
    $user = $world['users'][$role->value];

    $this->actingAs($user)->get(route('inquiry.show', $world['inquiries'][$visible]->reference))->assertOk();
    $this->actingAs($user)->get(route('inquiry.show', $world['inquiries'][$hidden]->reference))->assertNotFound();
})->with([
    'marketing' => [Role::Marketing, 'lead', 'foreign'],
    'sales' => [Role::Sales, 'salesAssigned', 'foreign'],
    'technical' => [Role::Technical, 'assigned', 'salesAssigned'],
    'accounting' => [Role::Accounting, 'assigned', 'salesAssigned'],
    'logistics' => [Role::Logistics, 'assigned', 'salesAssigned'],
]);

it('lets the admin and the owner open the details of every inquiry', function (Role $role): void {
    $world = visibilityWorld();

    foreach ($world['inquiries'] as $inquiry) {
        $this->actingAs($world['users'][$role->value])->get(route('inquiry.show', $inquiry->reference))->assertOk();
    }
})->with([
    'admin' => [Role::Admin],
    'owner' => [Role::Owner],
]);

it('404s a status move on an inquiry the user does not read, and leaves it as it was', function (Role $role, array $payload): void {
    $world = visibilityWorld();
    $hidden = $world['inquiries']['foreign'];

    $this->actingAs($world['users'][$role->value])
        ->postJson(route('inquiries.status', $hidden->reference), $payload)
        ->assertNotFound();

    expect($hidden->fresh()->status)->toBe(InquiryStatus::Production)
        ->and($hidden->statusHistory()->exists())->toBeFalse();
})->with([
    'sales' => [Role::Sales, ['track' => 'sales', 'to' => SalesStatus::ForRefund->value, 'note' => 'Asked for a refund.']],
    'accounting' => [Role::Accounting, ['track' => 'payment', 'to' => 'partial', 'note' => 'Paid.', 'amountReceived' => '10.00']],
    'logistics' => [Role::Logistics, ['track' => 'process', 'to' => 'quality_check', 'note' => 'Checked.']],
]);

it('moves the status of an inquiry the user is assigned to', function (): void {
    $world = visibilityWorld();
    $inquiry = $world['inquiries']['assigned'];

    $this->actingAs($world['users']['logistics'])
        ->post(route('inquiries.status', $inquiry->reference), ['track' => InquiryStatusTrack::Process->value, 'to' => 'quality_check', 'note' => 'Checked.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($inquiry->fresh()->status)->toBe(InquiryStatus::QualityCheck);
});

it('404s the job order upload for an inquiry sales does not read', function (): void {
    $world = visibilityWorld();
    $hidden = makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-V7', 'sales_status' => SalesStatus::Won]);

    $this->actingAs($world['users']['sales'])
        ->post(route('inquiries.job-order', $hidden->reference), ['note' => 'Job order in.', 'files' => ['job_order' => [UploadedFile::fake()->create('jo.pdf', 12, 'application/pdf')]]])
        ->assertNotFound();

    expect($hidden->hasJobOrder())->toBeFalse();
});

it('opens the staff edit page and saves an edit only for an inquiry the user reads', function (Role $role): void {
    $world = visibilityWorld();
    $user = $world['users'][$role->value];
    $visible = makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-V8', 'created_by' => $user, 'status' => InquiryStatus::Negotiation]);
    $hidden = makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-V9', 'status' => InquiryStatus::Negotiation]);

    $this->actingAs($user)->get(route('inquiry.edit', $visible->reference))->assertOk();
    $this->actingAs($user)->get(route('inquiry.edit', $hidden->reference))->assertNotFound();

    $this->actingAs($user)
        ->patch(route('inquiry.update', $hidden->reference), ['projects' => [inquiryProjectInput(['id' => $hidden->id, 'name' => 'Renamed', 'keepFileIds' => []])]])
        ->assertNotFound();

    expect($hidden->fresh()->name)->not->toBe('Renamed');
})->with([
    'marketing' => [Role::Marketing],
    'sales' => [Role::Sales],
]);

it('gives a user with two roles the inquiries of either', function (): void {
    $world = visibilityWorld();
    $user = $world['users']['marketing'];
    $user->assignRole(Role::Logistics->value);
    assignInquiry($world['inquiries']['assigned'], $user);

    expect(Inquiry::query()->visibleTo($user)->pluck('reference')->all())
        ->toEqualCanonicalizing(referencesOf($world, ['lead', 'created', 'assigned']));
});

it('gives a staff user with no role that reads inquiries none of them', function (): void {
    visibilityWorld();

    expect(Inquiry::query()->visibleTo(User::factory()->create())->exists())->toBeFalse();
});

it('answers alike for a list and for one loaded inquiry', function (Role $role): void {
    $world = visibilityWorld();
    $user = $world['users'][$role->value];

    foreach ($world['inquiries'] as $inquiry) {
        expect(Inquiry::query()->find($inquiry->id)->isVisibleTo($user))
            ->toBe(Inquiry::query()->visibleTo($user)->whereKey($inquiry->id)->exists(), "{$role->value} on {$inquiry->reference}");
    }
})->with(fn (): array => array_map(fn (Role $role): array => [$role], array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Client)));

it('refuses every inquiry ability on an inquiry the user does not read', function (string $ability, array $arguments): void {
    $world = visibilityWorld();
    $sales = $world['users']['sales'];

    expect(Gate::forUser($sales)->allows($ability, [$world['inquiries']['salesAssigned'], ...$arguments]))->toBeTrue()
        ->and(Gate::forUser($sales)->allows($ability, [$world['inquiries']['foreign'], ...$arguments]))->toBeFalse();
})->with([
    'view' => ['view', []],
    'update' => ['update', []],
    'uploadJobOrder' => ['uploadJobOrder', []],
    'updateStatus' => ['updateStatus', [InquiryStatusTrack::Sales]],
]);

it('keeps a client to their own inquiries, whoever handles them', function (): void {
    $world = visibilityWorld();
    $own = $world['inquiries']['assigned'];
    $client = $own->client;

    $this->actingAs($client)->get(route('quote.show', $own->reference))->assertOk();
    $this->actingAs($client)->get(route('quote.show', $world['inquiries']['lead']->reference))->assertNotFound();
    expect(Gate::forUser($client)->allows('view', $own))->toBeTrue();
});

it('records the staff member who opens an inquiry, and nobody for a client\'s own', function (): void {
    $marketing = User::factory()->role(Role::Marketing)->create();
    $lead = makeLead(inquiryClient(), $marketing);
    $client = inquiryClient();

    $this->actingAs($marketing)->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput(['name' => 'By staff'])]])->assertRedirect();
    $this->actingAs($client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['name' => 'By client'])]])->assertRedirect();

    expect(Inquiry::query()->where('name', 'By staff')->value('created_by'))->toBe($marketing->id)
        ->and(Inquiry::query()->where('name', 'By client')->value('created_by'))->toBeNull();
});
