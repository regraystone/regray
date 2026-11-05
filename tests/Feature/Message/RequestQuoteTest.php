<?php

use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    seedInquiryCatalog();
});

function canRequestQuote(User $viewer, Conversation $conversation): mixed
{
    return test()->actingAs($viewer)
        ->getJson(route('inbox.thread', $conversation))
        ->assertOk()
        ->json('canRequestQuote');
}

it('offers a client with no inquiries the button', function (): void {
    $client = inquiryClient();

    expect(canRequestQuote($client, supportThreadFor($client)))->toBeTrue();
});

it('decides from the inquiry the client holds', function (array $statuses, bool $expected): void {
    $client = inquiryClient();
    makeInquiry($client, [], $statuses);

    expect(canRequestQuote($client, supportThreadFor($client)))->toBe($expected);
})->with([
    'no status has started yet' => [[], false],
    'in negotiation' => [['sales_status' => SalesStatus::Negotiation], false],
    'rejected only' => [['sales_status' => SalesStatus::Rejected], true],
    'completed' => [['sales_status' => SalesStatus::Won, 'process_status' => ProcessStatus::Completed], true],
    'fully paid, still in production' => [[
        'sales_status' => SalesStatus::Won,
        'payment_status' => PaymentStatus::FullyPaid,
        'process_status' => ProcessStatus::Production,
    ], false],
    'fully paid and completed' => [[
        'sales_status' => SalesStatus::Won,
        'payment_status' => PaymentStatus::FullyPaid,
        'process_status' => ProcessStatus::Completed,
    ], true],
    'lost, which the agreed rule leaves active' => [['marketing_status' => LeadStatus::Lost], false],
]);

it('counts any active inquiry among finished ones', function (): void {
    $client = inquiryClient();
    makeInquiry($client, [], ['sales_status' => SalesStatus::Rejected]);
    makeInquiry($client, [], ['sales_status' => SalesStatus::Negotiation]);

    expect(canRequestQuote($client, supportThreadFor($client)))->toBeFalse();
});

it('does not count another client\'s active inquiry', function (): void {
    $client = inquiryClient();
    makeInquiry(inquiryClient(), [], ['sales_status' => SalesStatus::Negotiation]);

    expect(canRequestQuote($client, supportThreadFor($client)))->toBeTrue();
});

it('never offers it to staff, even staff who may send inquiries', function (): void {
    $marketing = messagingStaff(Role::Marketing);

    expect($marketing->can(Permission::InquiriesCreate->value))->toBeTrue()
        ->and(canRequestQuote($marketing, supportThreadFor(inquiryClient())))->toBeFalse();
});

it('never offers it in a group chat', function (): void {
    $client = inquiryClient();

    expect(canRequestQuote($client, groupConversation([$client, messagingStaff()])))->toBeFalse();
});

it('does not offer it to a client without inquiries.create', function (): void {
    $client = inquiryClient();
    SpatieRole::findByName(Role::Client->value)->revokePermissionTo(Permission::InquiriesCreate->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(canRequestQuote($client->fresh(), supportThreadFor($client)))->toBeFalse();
});
