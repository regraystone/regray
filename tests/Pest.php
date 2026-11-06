<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Conversation;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Services\Message\SupportConversationService;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\FurnitureTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Inquiry', 'Unit/Quote');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The rows an inquiry is made from: roles, services, edge profiles, and the
 * stones and finishes the stone seeder brings with it, and the furniture types.
 */
function seedInquiryCatalog(): void
{
    test()->seed([
        RolePermissionSeeder::class,
        ServiceSeeder::class,
        FurnitureTypeSeeder::class,
        EdgeProfileSeeder::class,
        StoneSeeder::class,
    ]);
}

function inquiryClient(Role $role = Role::Client): User
{
    return User::factory()->role($role)->create();
}

/**
 * One valid project in the shape the Inquire page posts, with `$overrides`
 * laid over it: one stone, with one service for it. Stones given without work get
 * "Stone supply only" each; none at all is one row with no stone, for a
 * recommendation.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryProjectInput(array $overrides = []): array
{
    $stones = $overrides['stones'] ?? [[
        'stoneId' => Stone::query()->active()->orderBy('id')->value('slug'),
        'quantity' => 4,
        'edge' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
        'finish' => Finish::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
    ]];

    return [
        'name' => 'Kitchen counters',
        'work' => array_map(
            fn (string $stoneId): array => ['stoneId' => $stoneId, 'serviceId' => 'supply-only', 'furnitureType' => ''],
            array_values(array_unique(array_column($stones, 'stoneId'))) ?: [''],
        ),
        'stones' => $stones,
        'deliveryMethod' => 'delivery',
        'location' => 'Makati',
        'landmark' => 'Near the church',
        'areaSqm' => '12.5',
        'neededBy' => now()->addDays(10)->toDateString(),
        'notes' => 'Ground floor.',
        ...$overrides,
    ];
}

/**
 * Role statuses that show `$status`, for an inquiry or a card snapshot, NULL
 * for a stage that has not started: sales starts when the lead is won, payment
 * when the client approves, and logistics with the first payment. Won comes
 * with the job order (`job_order` true), billed and waiting for that payment,
 * and shows as quoted, so `$status` Won is the shorthand for that state only;
 * a logistics stage and closed have the job order too. On hold is sales' on
 * hold, before the client approved; cancelled is sales rejected.
 *
 * @return array{sales_status: ?SalesStatus, payment_status: ?PaymentStatus, process_status: ?ProcessStatus, job_order: bool}
 */
function roleStatusesShowing(InquiryStatus $status): array
{
    [$sales, $payment, $process, $jobOrder] = match ($status) {
        InquiryStatus::Pending => [null, null, null, false],
        InquiryStatus::Cancelled => [SalesStatus::Rejected, null, null, false],
        InquiryStatus::Closed => [SalesStatus::Won, PaymentStatus::Awaiting, ProcessStatus::Completed, true],
        InquiryStatus::Negotiation, InquiryStatus::SiteVisited, InquiryStatus::OnHold => [SalesStatus::from($status->value), null, null, false],
        InquiryStatus::Quoted, InquiryStatus::Revision => [SalesStatus::from($status->value), null, null, false],
        InquiryStatus::Won => [SalesStatus::Won, PaymentStatus::Awaiting, null, true],
        // The client asked for their money back after a deposit.
        InquiryStatus::ForRefund => [SalesStatus::ForRefund, PaymentStatus::Partial, ProcessStatus::OrderReceived, true],
        default => [SalesStatus::Won, PaymentStatus::Awaiting, ProcessStatus::from($status->value), true],
    };

    return ['sales_status' => $sales, 'payment_status' => $payment, 'process_status' => $process, 'job_order' => $jobOrder];
}

/**
 * A lead for `$client`, held by `$marketing` (a new marketing user when not
 * given), with the first row of its log. A lead has no status of its own:
 * marketing's decisions are its inquiries' (`marketing_status`).
 */
function makeLead(User $client, ?User $marketing = null): Lead
{
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'marketing_user_id' => ($marketing ?? User::factory()->role(Role::Marketing)->create())->id,
    ]);

    $lead->history()->create(['to_status' => LeadStatus::Idle, 'changed_at' => now()]);

    return $lead;
}

/**
 * An inquiry in the database for `$client`, without going through the form.
 * One inquiry is one project: `$project` is `['name' => ..., 'stones' =>
 * [stone slugs], 'services' => [service slugs]]`, its stones stored in the
 * order given.
 *
 * `$attributes` may set `reference` and `created_at`, `lead` (the lead it is
 * linked to), `marketing_status` (marketing's decision; on a lead it defaults
 * to won once sales has started, as only a win starts it), `status` (a
 * display status, reached through roleStatusesShowing()), any of the three
 * role statuses, and `job_order` (whether sales has uploaded the job order
 * file). Without them it has no lead and no role status has started.
 *
 * @param  array{name?: string, stones?: list<string>, services?: list<string>}  $project
 * @param  array{reference?: string, created_at?: mixed, lead?: Lead, marketing_status?: LeadStatus|null, status?: InquiryStatus, sales_status?: SalesStatus, payment_status?: PaymentStatus, process_status?: ProcessStatus, completed_at?: mixed, job_order?: bool}  $attributes
 */
function makeInquiry(User $client, array $project = [], array $attributes = []): Inquiry
{
    static $sequence = 0;
    $sequence++;

    $statuses = [
        ...isset($attributes['status']) ? roleStatusesShowing($attributes['status']) : [],
        ...array_intersect_key($attributes, array_flip(['sales_status', 'payment_status', 'process_status', 'job_order'])),
    ];

    $inquiry = new Inquiry;
    $inquiry->forceFill([
        'reference' => $attributes['reference'] ?? sprintf('INQ-T%04d', $sequence),
        'client_id' => $client->id,
        'lead_id' => $attributes['lead']->id ?? null,
        'marketing_status' => $marketing = array_key_exists('marketing_status', $attributes)
            ? $attributes['marketing_status']
            : (isset($attributes['lead'], $statuses['sales_status']) ? LeadStatus::Won : null),
        'marketing_status_changed_at' => $marketing === null ? null : now(),
        'name' => $project['name'] ?? 'Project '.$sequence,
        'location' => 'Makati',
        'sales_status' => $statuses['sales_status'] ?? null,
        'sales_status_changed_at' => isset($statuses['sales_status']) ? now() : null,
        'payment_status' => $statuses['payment_status'] ?? null,
        'payment_status_changed_at' => isset($statuses['payment_status']) ? now() : null,
        'process_status' => $statuses['process_status'] ?? null,
        'process_status_changed_at' => isset($statuses['process_status']) ? now() : null,
        'completed_at' => $attributes['completed_at'] ?? (($statuses['process_status'] ?? null) === ProcessStatus::Completed ? now() : null),
        ...(isset($attributes['created_at']) ? ['created_at' => $attributes['created_at']] : []),
    ])->save();

    if ($statuses['job_order'] ?? false) {
        addInquiryFile($inquiry, DocumentType::JobOrder);
    }

    $edge = EdgeProfile::query()->value('id');
    $finish = Finish::query()->value('id');
    $stones = $project['stones'] ?? [Stone::query()->orderBy('id')->value('slug')];

    foreach ($project['services'] ?? ['supply-only'] as $serviceSlug) {
        foreach ($stones === [] ? [null] : $stones as $slug) {
            $inquiry->workItems()->create([
                'stone_id' => $slug === null ? null : Stone::query()->where('slug', $slug)->value('id'),
                'service_id' => Service::query()->where('slug', $serviceSlug)->value('id'),
            ]);
        }
    }

    foreach (array_values($stones) as $order => $slug) {
        $inquiry->stones()->create([
            'stone_id' => Stone::query()->where('slug', $slug)->value('id'),
            'edge_profile_id' => $edge,
            'finish_id' => $finish,
            'quantity' => 1,
            'sort_order' => $order,
        ]);
    }

    return $inquiry;
}

/** Move an existing inquiry's role statuses so it shows `$status`, adding the job order file where one is needed. */
function showInquiryAs(Inquiry $inquiry, InquiryStatus $status): Inquiry
{
    $statuses = roleStatusesShowing($status);

    $inquiry->forceFill([
        ...array_diff_key($statuses, ['job_order' => true]),
        'sales_status_changed_at' => $statuses['sales_status'] === null ? null : now(),
        'payment_status_changed_at' => $statuses['payment_status'] === null ? null : now(),
        'process_status_changed_at' => $statuses['process_status'] === null ? null : now(),
    ])->save();

    if ($statuses['job_order'] && ! $inquiry->hasJobOrder()) {
        addInquiryFile($inquiry, DocumentType::JobOrder);
    }

    return $inquiry;
}

/** A file row on the inquiry, written straight to the database. Nothing is put on a disk. */
function addInquiryFile(Inquiry $inquiry, DocumentType $type, ?User $uploader = null, ?int $revision = null): void
{
    $inquiry->files()->create([
        'document_type' => $type,
        'revision' => $revision,
        'disk' => 'local',
        'path' => "inquiries/{$inquiry->reference}/{$type->value}.pdf",
        'original_name' => "{$type->value}.pdf",
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'uploaded_by' => $uploader?->id,
    ]);
}

/*
 * The Quote card's button table, exactly as the product states it: for an
 * inquiry shown at each status (on hold being sales' on hold, before the client
 * approved), what each of the four buttons is. `null` is a button that is not
 * rendered; `[true]` is clickable; `[false, reason]` is shown and locked.
 */
dataset('inquiry button table', [
    'pending' => ['pending', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'negotiation' => ['negotiation', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'site_visited' => ['site_visited', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'on_hold' => ['on_hold', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'quoted' => ['quoted', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'revision' => ['revision', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'won' => ['won', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
    'production' => ['production', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
    'closed' => ['closed', ['view' => [true], 'update' => null, 'cancel' => null, 'invoice' => [true]]],
    'cancelled' => ['cancelled', ['view' => [true], 'update' => [false, 'This inquiry was cancelled.'], 'cancel' => null, 'invoice' => null]],
    // Approved before the refund was asked for: locked like any approved inquiry.
    'for_refund' => ['for_refund', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
]);

/**
 * What one action should look like on the wire, from its row in the table.
 *
 * @param  array{0?: bool, 1?: string}|null  $expectation
 * @return array{visible: bool, enabled: bool, reason?: string}
 */
function expectedAction(?array $expectation): array
{
    if ($expectation === null) {
        return ['visible' => false, 'enabled' => false];
    }

    return [
        'visible' => true,
        'enabled' => $expectation[0],
        ...($expectation[0] ? [] : ['reason' => $expectation[1]]),
    ];
}

/**
 * A member of staff for the Message tests. Marketing holds the support pool's
 * `support.claim`; sales holds neither it nor `conversations.create`.
 */
function messagingStaff(Role $role = Role::Marketing, string $name = 'Staff'): User
{
    return User::factory()->role($role)->create(['name' => $name]);
}

/** The client's Regray Team thread, as registration creates it. */
function supportThreadFor(User $client): Conversation
{
    return app(SupportConversationService::class)->ensureFor($client);
}

/**
 * A group conversation with `$members` in it, each a member unless `$roles`
 * names another role for their id. `$attributes` may bind it to an inquiry.
 *
 * @param  list<User>  $members
 * @param  array<int, ConversationMemberRole>  $roles
 * @param  array<string, mixed>  $attributes
 */
function groupConversation(array $members, array $roles = [], array $attributes = []): Conversation
{
    $conversation = Conversation::query()->create([
        'type' => ConversationType::Group,
        'name' => 'Kitchen project',
        'created_by' => $members[0]->id,
        ...$attributes,
    ]);

    foreach ($members as $member) {
        app(ConversationMemberRepositoryInterface::class)
            ->upsert($conversation->id, $member->id, $roles[$member->id] ?? ConversationMemberRole::Member);
    }

    return $conversation;
}

/** A text message written straight to the database, without the send rules. */
function textMessage(Conversation $conversation, User $sender, string $body = 'Hello'): Message
{
    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'type' => MessageType::Text,
        'body' => $body,
    ]);
}
