<?php

use App\Enums\ConversationMemberRole;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Everyone the Regray Team rules tell apart, around one client's thread.
 *
 * @return array{thread: Conversation, owner: User, otherClient: User, marketing: User, otherMarketing: User, sales: User, admin: User}
 */
function supportCast(): array
{
    $owner = inquiryClient();

    return [
        'thread' => supportThreadFor($owner),
        'owner' => $owner,
        'otherClient' => inquiryClient(),
        'marketing' => messagingStaff(Role::Marketing, 'Mara'),
        'otherMarketing' => messagingStaff(Role::Marketing, 'Mike'),
        'sales' => messagingStaff(Role::Sales, 'Sam'),
        'admin' => messagingStaff(Role::Admin, 'Ada'),
    ];
}

/** @param  array<string, bool>  $expected */
function expectAbility(string $ability, Conversation $thread, array $cast, array $expected): void
{
    foreach ($expected as $who => $allowed) {
        expect($cast[$who]->can($ability, $thread))->toBe($allowed, "{$ability} by {$who}");
    }
}

it('lets the client, the support pool and the admin open an unassigned Regray Team thread', function (): void {
    $cast = supportCast();

    expectAbility('view', $cast['thread'], $cast, [
        'owner' => true,
        'otherClient' => false,
        'marketing' => true,
        'otherMarketing' => true,
        'sales' => false,
        'admin' => true,
    ]);
});

it('closes a claimed Regray Team thread to the rest of the pool', function (): void {
    $cast = supportCast();
    $cast['thread']->forceFill(['assigned_to' => $cast['marketing']->id, 'assigned_at' => now()])->save();

    expectAbility('view', $cast['thread'], $cast, [
        'owner' => true,
        'otherClient' => false,
        'marketing' => true,
        'otherMarketing' => false,
        'sales' => false,
        'admin' => true,
    ]);
});

it('lets only holders of support.claim and the admin claim a Regray Team thread', function (): void {
    $cast = supportCast();

    expectAbility('claim', $cast['thread'], $cast, [
        'owner' => false,
        'otherClient' => false,
        'marketing' => true,
        'sales' => false,
        'admin' => true,
    ]);
});

it('keeps a claim for good: a claimed thread can be neither released nor reassigned', function (): void {
    $cast = supportCast();
    $cast['thread']->forceFill(['assigned_to' => $cast['marketing']->id, 'assigned_at' => now()])->save();

    expect(Route::has('inbox.release'))->toBeFalse()
        ->and(Route::has('inbox.reassign'))->toBeFalse();

    foreach (['marketing', 'admin'] as $who) {
        $this->actingAs($cast[$who])->postJson("/dashboard/inbox/{$cast['thread']->id}/release")->assertNotFound();
        $this->actingAs($cast[$who])->postJson("/dashboard/inbox/{$cast['thread']->id}/reassign", ['userId' => $cast['otherMarketing']->id])->assertNotFound();
    }

    expect($cast['thread']->fresh()->assigned_to)->toBe($cast['marketing']->id);
});

it('lets group members read, and only non-viewers write', function (): void {
    $client = inquiryClient();
    $member = messagingStaff(Role::Sales);
    $viewer = messagingStaff(Role::Sales);
    $outsider = messagingStaff(Role::Marketing);
    $group = groupConversation([$client, $member, $viewer], [$viewer->id => ConversationMemberRole::Viewer]);

    expect($client->can('view', $group))->toBeTrue()
        ->and($member->can('send', $group))->toBeTrue()
        ->and($viewer->can('send', $group))->toBeFalse()
        ->and($outsider->can('view', $group))->toBeFalse()
        ->and($outsider->can('claim', $group))->toBeFalse();
});
