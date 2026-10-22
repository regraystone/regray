<?php

use App\Enums\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;

/*
 * The Message page's rate limits, each per user: sending 60, receipts 120,
 * claim/release/reassign 10 and the people search 60 a minute.
 */

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('puts every Message endpoint that can be repeated behind its own limit', function (string $route, string $limiter): void {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain("throttle:{$limiter}");
})->with([
    ['message.send', 'messages'],
    ['message.read', 'message-receipts'],
    ['message.delivered', 'message-receipts'],
    ['message.claim', 'message-assignment'],
    ['message.release', 'message-assignment'],
    ['message.reassign', 'message-assignment'],
    ['message.people', 'message-people'],
    ['message.attachments.show', 'message-attachments'],
]);

it('answers the 11th claim in a minute with 429, and lets the user back in after it', function (): void {
    $marketing = messagingStaff(Role::Marketing);
    $threads = collect(range(1, 11))->map(fn () => supportThreadFor(inquiryClient()));

    foreach ($threads->take(10) as $thread) {
        $this->actingAs($marketing)->postJson(route('message.claim', $thread))->assertOk();
    }

    $this->actingAs($marketing)->postJson(route('message.claim', $threads->last()))->assertTooManyRequests();

    $this->travel(61)->seconds();
    $this->actingAs($marketing)->postJson(route('message.claim', $threads->last()))->assertOk();
});

it('counts each user separately', function (): void {
    $mara = messagingStaff(Role::Marketing, 'Mara');
    $mike = messagingStaff(Role::Marketing, 'Mike');

    foreach (range(1, 10) as $attempt) {
        $this->actingAs($mara)->postJson(route('message.claim', supportThreadFor(inquiryClient())))->assertOk();
    }

    $this->actingAs($mara)->postJson(route('message.claim', supportThreadFor(inquiryClient())))->assertTooManyRequests();
    $this->actingAs($mike)->postJson(route('message.claim', supportThreadFor(inquiryClient())))->assertOk();
});

it('answers the 121st read receipt in a minute with 429', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $message = textMessage($thread, $client);

    foreach (range(1, 120) as $attempt) {
        $this->actingAs($client)->postJson(route('message.read', $thread), ['lastReadMessageId' => $message->id])->assertSuccessful();
    }

    $this->actingAs($client)->postJson(route('message.read', $thread), ['lastReadMessageId' => $message->id])->assertTooManyRequests();
});
