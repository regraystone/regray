<?php

use App\Enums\ClientKind;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\InquiryStatusService;
use Illuminate\Support\Facades\Storage;

/*
 * Inquiry notifications. The owner and the admin hear about every one, in the
 * staff's words. The assigned staff are the marketer holding the lead and the
 * inquiry's handlers; while there is neither, marketing and sales hear
 * instead. The client hears the status they see; staff the raw one.
 */

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = User::factory()->role(Role::Client)->create(['name' => 'Carla']);
    $this->mara = messagingStaff(Role::Marketing, 'Mara');
    $this->mike = messagingStaff(Role::Marketing, 'Mike');
    $this->sales = messagingStaff(Role::Sales, 'Sam');
    $this->technical = messagingStaff(Role::Technical, 'Tim');
    $this->owner = messagingStaff(Role::Owner, 'Olive');
    $this->admin = messagingStaff(Role::Admin, 'Ada');
});

function submitInquiry(mixed $test, User $client): Inquiry
{
    $test->actingAs($client)->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])->assertSessionHasNoErrors();

    return Inquiry::query()->latest('id')->firstOrFail();
}

/** An inquiry on Mara's lead, with sales and technical assigned. */
function claimedInquiry(mixed $test, array $attributes = []): Inquiry
{
    $inquiry = makeInquiry($test->client, attributes: ['reference' => 'INQ-N1', 'lead' => makeLead($test->client, $test->mara), ...$attributes]);
    assignInquiry($inquiry, $test->sales, $test->technical);

    return $inquiry;
}

function moveStatus(Inquiry $inquiry, InquiryStatusTrack $track, SalesStatus|PaymentStatus|ProcessStatus $to, User $by): void
{
    app(InquiryStatusService::class)->transition($inquiry->fresh(), $track, $to, $by);
}

it('confirms an unclaimed inquiry to its client and tells marketing, sales, the owner and the admin', function (): void {
    $inquiry = submitInquiry($this, $this->client);
    $ref = $inquiry->reference;

    expect(notificationBodies($this->client))->toBe(["You have successfully submitted inquiry {$ref}."]);

    foreach (['mara', 'mike', 'sales', 'owner', 'admin'] as $who) {
        expect(notificationBodies($this->{$who}))->toBe(["Carla has submitted inquiry {$ref}."], $who);
    }

    expect(notificationBodies($this->technical))->toBe([]);
});

it('tells only the marketer who claimed the client, with the owner and the admin, about a new inquiry', function (): void {
    makeLead($this->client, $this->mara);

    $ref = submitInquiry($this, $this->client)->reference;

    expect(notificationBodies($this->mara))->toBe(["Carla has submitted inquiry {$ref}."])
        ->and(notificationBodies($this->owner))->toBe(["Carla has submitted inquiry {$ref}."])
        ->and(notificationBodies($this->mike))->toBe([])
        ->and(notificationBodies($this->sales))->toBe([]);
});

it('confirms the client\'s update and cancel to them and tells the assigned staff', function (): void {
    $inquiry = claimedInquiry($this);
    $edit = ['id' => $inquiry->id, 'name' => $inquiry->name, 'keepFileIds' => []];

    $this->actingAs($this->client)->patch(route('quote.update', 'INQ-N1'), ['projects' => [inquiryProjectInput([...$edit, 'notes' => 'Changed.'])]])->assertSessionHasNoErrors();
    $this->actingAs($this->client)->patch(route('quote.cancel', 'INQ-N1'))->assertSessionHasNoErrors();

    foreach (['client', 'mara', 'sales', 'technical', 'owner', 'admin'] as $who) {
        expect(notificationBodies($this->{$who}))->toBe($who === 'client'
            ? ['You have successfully updated inquiry INQ-N1.', 'You have successfully cancelled inquiry INQ-N1.']
            : ['Carla has updated inquiry INQ-N1.', 'Carla has cancelled inquiry INQ-N1.'], $who);
    }

    expect(notificationBodies($this->mike))->toBe([]);
});

it('tells the client the status they see and the assigned staff the raw one, never whoever moved it', function (): void {
    $inquiry = claimedInquiry($this, ['status' => InquiryStatus::Negotiation]);

    moveStatus($inquiry, InquiryStatusTrack::Sales, SalesStatus::OnHold, $this->sales);

    expect(notificationBodies($this->client))->toBe(['Your inquiry INQ-N1 is now on hold.'])
        ->and(notificationBodies($this->technical))->toBe(['Sam has moved inquiry INQ-N1 to on hold (sales).'])
        ->and(notificationBodies($this->mara))->toBe(['Sam has moved inquiry INQ-N1 to on hold (sales).'])
        ->and(notificationBodies($this->owner))->toBe(['Sam has moved inquiry INQ-N1 to on hold (sales).'])
        ->and(notificationBodies($this->sales))->toBe([])
        ->and(notificationBodies($this->mike))->toBe([]);
});

it('shows the client sales won as quoted, so they hear nothing until their payment moves', function (): void {
    $inquiry = claimedInquiry($this, ['status' => InquiryStatus::Quoted]);

    moveStatus($inquiry, InquiryStatusTrack::Sales, SalesStatus::Won, $this->sales);
    expect(notificationBodies($this->client))->toBe([])
        ->and(notificationBodies($this->technical))->toBe(['Sam has moved inquiry INQ-N1 to won (sales).']);

    moveStatus($inquiry, InquiryStatusTrack::Payment, PaymentStatus::Partial, $this->sales);

    expect(notificationBodies($this->client))->toBe(['Your inquiry INQ-N1 is now partially paid.'])
        ->and(notificationBodies($this->technical))->toContain('Sam has moved inquiry INQ-N1 to partially paid (accounting).');
});

it('tells the client marketing\'s win as negotiation, and staff as marketing\'s won', function (): void {
    $inquiry = claimedInquiry($this);

    app(InquiryStatusService::class)->decide($inquiry->fresh(), LeadStatus::Won, $this->mara);

    expect(notificationBodies($this->client))->toBe(['Your inquiry INQ-N1 is now negotiation.'])
        ->and(notificationBodies($this->sales))->toBe(['Mara has moved inquiry INQ-N1 to won (marketing).'])
        ->and(notificationBodies($this->mara))->toBe([]);
});

it('tells the client a lost inquiry is cancelled', function (): void {
    $inquiry = claimedInquiry($this);

    app(InquiryStatusService::class)->decide($inquiry->fresh(), LeadStatus::Lost, $this->mara);

    expect(notificationBodies($this->client))->toBe(['Your inquiry INQ-N1 is now cancelled.'])
        ->and(notificationBodies($this->sales))->toBe(['Mara has moved inquiry INQ-N1 to lost (marketing).']);
});

it('stops telling staff who lost their assignment, and keeps what they had', function (): void {
    $inquiry = claimedInquiry($this, ['status' => InquiryStatus::Negotiation]);
    moveStatus($inquiry, InquiryStatusTrack::Sales, SalesStatus::OnHold, $this->sales);

    $inquiry->handlers()->where('user_id', $this->technical->id)->delete();
    moveStatus($inquiry, InquiryStatusTrack::Sales, SalesStatus::Negotiation, $this->sales);

    expect(notificationBodies($this->technical))->toBe(['Sam has moved inquiry INQ-N1 to on hold (sales).']);
});

it('tells staff who open an inquiry for a client, and the client', function (): void {
    $lead = makeLead($this->client, $this->mara);

    $this->actingAs($this->mara)->post(route('leads.inquiries.store', $lead->reference), ['projects' => [inquiryProjectInput()]])->assertSessionHasNoErrors();
    $ref = Inquiry::query()->sole()->reference;

    expect(notificationBodies($this->mara))->toBe(["You have successfully created inquiry {$ref} for Carla."])
        ->and(notificationBodies($this->client))->toBe(["Inquiry {$ref} has been created for you."])
        ->and(notificationBodies($this->owner))->toBe(["Mara has created inquiry {$ref} for Carla."])
        ->and(notificationBodies($this->mike))->toBe([]);
});

it('tells only the staff member, and nobody as a client, about a walk-in\'s inquiry', function (): void {
    $record = Client::query()->create(['kind' => ClientKind::Direct, 'full_name' => 'Wanda Walk-in', 'created_by' => $this->sales->id]);

    $this->actingAs($this->sales)->post(route('inquiry.direct-clients.inquiries.store', $record->id), ['projects' => [inquiryProjectInput()]])->assertSessionHasNoErrors();
    $ref = Inquiry::query()->sole()->reference;

    expect(notificationBodies($this->sales))->toBe(["You have successfully created inquiry {$ref} for Wanda Walk-in."])
        ->and(notificationBodies($this->client))->toBe([])
        ->and(notificationBodies($this->mara))->toBe([]);
});
