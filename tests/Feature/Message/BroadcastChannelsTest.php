<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Testing\TestResponse;

/*
 * The test environment broadcasts to the null driver, which authorizes nothing.
 * These tests swap in the Reverb driver, with throwaway keys, and register the
 * channels on it again, so /broadcasting/auth runs the real channel rules.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
});

function authorizeChannel(User $user, string $channel): TestResponse
{
    return test()->actingAs($user)->post('/broadcasting/auth', [
        'channel_name' => "private-{$channel}",
        'socket_id' => '1234.5678',
    ]);
}

it('lets a user listen to their own inbox only', function (): void {
    $user = inquiryClient();

    authorizeChannel($user, "user.{$user->id}")->assertOk()->assertJsonStructure(['auth']);
    authorizeChannel($user, 'user.'.inquiryClient()->id)->assertForbidden();
});

it('lets only the support pool listen to it', function (): void {
    authorizeChannel(messagingStaff(Role::Marketing), 'support.pool')->assertOk();
    authorizeChannel(messagingStaff(Role::Sales), 'support.pool')->assertForbidden();
    authorizeChannel(inquiryClient(), 'support.pool')->assertForbidden();
});

it('lets a user listen to a thread only when they may open it', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $marketing = messagingStaff(Role::Marketing);

    authorizeChannel($client, "conversation.{$thread->id}")->assertOk();
    authorizeChannel(inquiryClient(), "conversation.{$thread->id}")->assertForbidden();
    authorizeChannel($marketing, "conversation.{$thread->id}")->assertOk();

    $thread->forceFill(['assigned_to' => messagingStaff(Role::Marketing)->id, 'assigned_at' => now()])->save();

    authorizeChannel($marketing, "conversation.{$thread->id}")->assertForbidden();
});
