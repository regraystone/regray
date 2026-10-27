<?php

use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\InquiryProjectStatus;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Services\Message\SupportConversationService;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\ProjectCategorySeeder;
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
 * stones and finishes the stone seeder, and the project categories the Application step offers brings with them.
 */
function seedInquiryCatalog(): void
{
    test()->seed([
        RolePermissionSeeder::class,
        ServiceSeeder::class,
        ProjectCategorySeeder::class,
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
 * laid over it. A regular service, so it carries a stone and an application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryProjectInput(array $overrides = []): array
{
    return [
        'name' => 'Kitchen counters',
        'serviceIds' => ['natural-stone-supply'],
        'applicationIds' => [ProjectCategory::query()->orderBy('id')->value('slug')],
        'stones' => [[
            'stoneId' => Stone::query()->active()->orderBy('id')->value('slug'),
            'quantity' => 4,
            'edge' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
            'finish' => Finish::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
        ]],
        'location' => 'Makati',
        'landmark' => 'Near the church',
        'areaSqm' => '12.5',
        'neededBy' => now()->addDays(10)->toDateString(),
        'neededByTime' => '',
        'notes' => 'Ground floor.',
        ...$overrides,
    ];
}

/**
 * A visit: a service that stands on its own, with no stone and no application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryVisitInput(array $overrides = []): array
{
    return [
        'name' => 'Stone Relocation, Dismantling & Reinstallation',
        'serviceIds' => ['stone-relocation-dismantling-reinstallation'],
        'location' => 'Taguig',
        'landmark' => '',
        'areaSqm' => '',
        'neededBy' => now()->addDays(5)->toDateString(),
        'neededByTime' => '12:00',
        'notes' => '',
        ...$overrides,
    ];
}

/**
 * An inquiry in the database for `$client`, without going through the form.
 *
 * Each project is `['name' => ..., 'stones' => [stone slugs], 'services' => [service slugs]]`,
 * its stones stored in the order given. `$attributes` may set `reference`,
 * `status` and `created_at`.
 *
 * @param  list<array{name?: string, stones?: list<string>, services?: list<string>}>  $projects
 * @param  array{reference?: string, status?: InquiryStatus, created_at?: mixed}  $attributes
 */
function makeInquiry(User $client, array $projects = [[]], array $attributes = []): Inquiry
{
    static $sequence = 0;
    $sequence++;

    $inquiry = new Inquiry;
    $inquiry->forceFill([
        'reference' => $attributes['reference'] ?? sprintf('INQ-T%04d', $sequence),
        'client_id' => $client->id,
        'status' => $attributes['status'] ?? InquiryStatus::Pending,
        'status_changed_at' => now(),
        ...(isset($attributes['created_at']) ? ['created_at' => $attributes['created_at']] : []),
    ])->save();

    $edge = EdgeProfile::query()->value('id');
    $finish = Finish::query()->value('id');

    foreach (array_values($projects) as $position => $project) {
        $row = $inquiry->projects()->create([
            'name' => $project['name'] ?? 'Project '.($position + 1),
            'sort_order' => $position,
            'location' => 'Makati',
            'status' => InquiryProjectStatus::Pending,
            'status_changed_at' => now(),
        ]);

        $row->services()->attach(
            Service::query()->whereIn('slug', $project['services'] ?? ['natural-stone-supply'])->pluck('id')->all(),
        );

        $stones = $project['stones'] ?? [Stone::query()->orderBy('id')->value('slug')];

        foreach (array_values($stones) as $order => $slug) {
            $row->stones()->create([
                'stone_id' => Stone::query()->where('slug', $slug)->value('id'),
                'edge_profile_id' => $edge,
                'finish_id' => $finish,
                'quantity' => 1,
                'sort_order' => $order,
            ]);
        }
    }

    return $inquiry;
}

/*
 * The Quote card's button table, exactly as the product states it: for every
 * status, what each of the four buttons is. `null` is a button that is not
 * rendered; `[true]` is clickable; `[false, reason]` is shown and locked.
 */
dataset('inquiry button table', [
    'pending' => ['pending', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'ongoing' => ['ongoing', ['view' => [true], 'update' => [false, 'Locked once quoted. Message the team to request changes.'], 'cancel' => [true], 'invoice' => [true]]],
    'closed' => ['closed', ['view' => [true], 'update' => null, 'cancel' => null, 'invoice' => [true]]],
    'lost' => ['lost', ['view' => [true], 'update' => [false, 'This inquiry was cancelled.'], 'cancel' => null, 'invoice' => null]],
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
