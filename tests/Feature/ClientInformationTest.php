<?php

use App\Data\ClientInformationValues;
use App\Enums\AuditEvent;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\ClientInformation;
use App\Models\ClientInformationSnapshot;
use App\Models\Inquiry;
use App\Repositories\Contracts\ClientInformationRepositoryInterface;
use App\Repositories\Eloquent\EloquentClientInformationRepository;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * "Client Information" is private prefill data, not a CRM contact: its own
 * table, its own permission, its own snapshot on a client's inquiry, and no
 * new requirement on either staff flow.
 */
beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
});

/** A valid Profile save, with a site that has space to trim. */
function informationSave(array $overrides = []): array
{
    return [
        'deliveryMethod' => 'delivery',
        'location' => ' Makati ',
        ...$overrides,
    ];
}

test('opens Client Information only to its owner, with no id or shared prop', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->get(route('profile.client-information.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profile/client-information')
            ->where('information', [
                'deliveryMethod' => 'delivery',
                'location' => null,
            ])
            ->where('locations', (array) config('inquiry.locations'))
            ->missing('clientInformation'));

    // Forget the guard so this really is a visitor, not the client above.
    $this->app['auth']->forgetGuards();

    $this->get(route('profile.client-information.edit'))->assertRedirect(route('login'));

    foreach ([Role::Sales, Role::Marketing, Role::Owner] as $role) {
        $this->actingAs(inquiryClient($role))
            ->get(route('profile.client-information.edit'))
            ->assertForbidden();
    }
});

test('saves one row per account, normalized and audited without values', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->from(route('profile.client-information.edit'))
        ->put(route('profile.client-information.update'), informationSave())
        ->assertRedirect(route('profile.client-information.edit'))
        ->assertSessionHasNoErrors();

    $row = ClientInformation::sole();

    expect($row->user_id)->toBe($client->id)
        ->and($row->delivery_method)->toBe('delivery')
        ->and($row->location)->toBe('Makati');

    $audit = AuditLog::sole();

    expect($audit->event)->toBe(AuditEvent::Created)
        ->and($audit->user_id)->toBe($client->id)
        ->and($audit->auditable_type)->toBe(ClientInformation::class)
        ->and($audit->auditable_id)->toBe($client->id)
        ->and($audit->old_values)->toBeNull()
        ->and($audit->new_values)->toBeNull();

    $this->actingAs($client)
        ->put(route('profile.client-information.update'), informationSave(['location' => 'Taguig']))
        ->assertRedirect(route('profile.client-information.edit'));

    expect(ClientInformation::query()->count())->toBe(1)
        ->and(ClientInformation::sole()->location)->toBe('Taguig')
        ->and(AuditLog::query()->orderBy('id')->pluck('event')->all())
        ->toBe([AuditEvent::Created, AuditEvent::Updated])
        ->and(AuditLog::query()->whereNotNull('old_values')->orWhereNotNull('new_values')->count())
        ->toBe(0);
});

test('a pickup saves no site even when one was sent with it', function (): void {
    $this->actingAs(inquiryClient())
        ->put(route('profile.client-information.update'), informationSave([
            'deliveryMethod' => 'pickup',
            'location' => 'Makati',
        ]))
        ->assertRedirect(route('profile.client-information.edit'))
        ->assertSessionHasNoErrors();

    expect(ClientInformation::sole()->delivery_method)->toBe('pickup')
        ->and(ClientInformation::sole()->location)->toBeNull();
});

test('refuses a bad Profile save without writing a row or an audit', function (): void {
    $this->actingAs(inquiryClient())
        ->put(route('profile.client-information.update'), informationSave([
            'deliveryMethod' => 'courier',
            'location' => 'Nowhere',
        ]))
        ->assertSessionHasErrors(['deliveryMethod', 'location']);

    expect(ClientInformation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('a delivery has to say where', function (): void {
    $this->actingAs(inquiryClient())
        ->put(route('profile.client-information.update'), informationSave(['location' => '']))
        ->assertSessionHasErrors(['location']);

    expect(ClientInformation::query()->count())->toBe(0);
});

test('rate-limits the Profile save', function (): void {
    $client = inquiryClient();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->actingAs($client)
            ->put(route('profile.client-information.update'), informationSave())
            ->assertRedirect(route('profile.client-information.edit'));
    }

    $this->actingAs($client)
        ->putJson(route('profile.client-information.update'), informationSave())
        ->assertTooManyRequests();
});

test('ignores a saved site no longer on the list instead of offering it again', function (): void {
    $client = inquiryClient();

    ClientInformation::query()->forceCreate([
        'user_id' => $client->id,
        'delivery_method' => 'delivery',
        'location' => 'A town the studio no longer serves',
    ]);

    $this->actingAs($client)
        ->get(route('profile.client-information.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('information.location', null));
});

test('a stale saved row without a usable method falls back silently', function (): void {
    $stale = new ClientInformation(['delivery_method' => null, 'location' => 'Makati']);

    expect(ClientInformationValues::fromSaved($stale)->withValidLocation()->toArray())->toBe([
        'deliveryMethod' => 'delivery',
        'location' => 'Makati',
    ]);
});

test('a client inquiry carries a snapshot, and saves the Profile in the same request only when asked', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput(
            [inquiryProjectInput(['location' => 'Taguig'])],
            ['deliveryMethod' => 'delivery', 'location' => 'Taguig', 'saveToProfile' => true],
        ))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $inquiry = Inquiry::sole();
    $snapshot = $inquiry->clientInformationSnapshot;

    expect($inquiry->location)->toBe('Taguig')
        ->and($snapshot)->not->toBeNull()
        ->and($snapshot->delivery_method)->toBe('delivery')
        ->and($snapshot->location)->toBe('Taguig')
        ->and(ClientInformation::sole()->location)->toBe('Taguig');

    $this->actingAs($client)
        ->get(route('quote.edit', $inquiry->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inquire')
            ->where('mode', 'edit')
            ->where('clientInformation', [
                'deliveryMethod' => 'delivery',
                'location' => 'Taguig',
            ]));

    $this->actingAs($client)
        ->get(route('quote.show', $inquiry->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inquiry-details')
            ->where('inquiry.clientInformation.location', 'Taguig'));
});

test('a pickup inquiry snapshots no site and saves none either', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), clientInquiryInput(
            [inquiryProjectInput(['deliveryMethod' => 'pickup', 'location' => ''])],
            ['deliveryMethod' => 'pickup', 'location' => 'Makati', 'saveToProfile' => true],
        ))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Inquiry::sole()->clientInformationSnapshot->delivery_method)->toBe('pickup')
        ->and(Inquiry::sole()->clientInformationSnapshot->location)->toBeNull()
        ->and(ClientInformation::sole()->location)->toBeNull();
});

test('an unchecked save choice still snapshots the inquiry but leaves the Profile alone', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ClientInformation::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0)
        ->and(Inquiry::sole()->clientInformationSnapshot)->not->toBeNull();
});

test('a client inquiry cannot be sent without how it will be got or where', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertSessionHasErrors([
            'clientInformation',
            'clientInformation.deliveryMethod',
        ]);

    expect(Inquiry::query()->count())->toBe(0);
});

test('a client inquiry refuses a delivery with no site', function (): void {
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), clientInquiryInput(
            [inquiryProjectInput()],
            ['location' => ''],
        ))
        ->assertSessionHasErrors(['clientInformation.location']);

    expect(Inquiry::query()->count())->toBe(0);
});

test('staff flows ask for no new field and write no snapshot', function (): void {
    $marketing = inquiryClient(Role::Marketing);
    $client = inquiryClient();
    $lead = makeLead($client, marketing: $marketing);

    // `inquiry.inquire` is the staff stepper; `quote.inquire` is the client's.
    $this->actingAs($marketing)
        ->get(route('inquiry.inquire'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inquire')
            ->missing('clientInformation'));

    $this->actingAs($marketing)
        ->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput()]])
        ->assertSessionHasNoErrors();

    expect(Inquiry::query()->count())->toBe(1)
        ->and(ClientInformationSnapshot::query()->count())->toBe(0)
        ->and(ClientInformation::query()->count())->toBe(0);
});

test('a failed inquiry rolls back the Profile save, its audit and the snapshot', function (): void {
    // Fail the snapshot at the repository, after the Profile row and its
    // audit are written, so the transaction has something to roll back.
    // Mock the concrete class so its other methods still do the real write.
    $repository = $this->partialMock(EloquentClientInformationRepository::class, function ($mock): void {
        $mock->shouldReceive('snapshotForInquiry')
            ->once()
            ->andThrow(new RuntimeException('forced failure'));
    });

    $this->app->instance(ClientInformationRepositoryInterface::class, $repository);

    // The controller catches the failure, flashes a notice and keeps the form.
    $this->actingAs(inquiryClient())
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()], ['saveToProfile' => true]))
        ->assertRedirect();

    expect(Inquiry::query()->count())->toBe(0)
        ->and(ClientInformation::query()->count())->toBe(0)
        ->and(ClientInformationSnapshot::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

test('deleting the account removes only its saved row', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->put(route('profile.client-information.update'), informationSave())
        ->assertRedirect(route('profile.client-information.edit'));

    $client->delete();

    expect(ClientInformation::query()->count())->toBe(0);
});

test('removing saved information leaves an inquiry snapshot with the history', function (): void {
    $client = inquiryClient();

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput(
            [inquiryProjectInput()],
            ['saveToProfile' => true],
        ))
        ->assertSessionHasNoErrors();

    $inquiryId = Inquiry::query()->value('id');
    ClientInformation::query()->where('user_id', $client->id)->delete();

    expect(ClientInformation::query()->count())->toBe(0)
        ->and(ClientInformationSnapshot::query()->where('inquiry_id', $inquiryId)->count())->toBe(1);
});
