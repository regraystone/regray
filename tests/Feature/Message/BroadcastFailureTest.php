<?php

use App\Models\Message;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Broadcast;

/*
 * Reverb being down must not turn a saved message into a 500. These tests
 * point the Reverb driver at a port nothing listens on, so every broadcast
 * fails, and check that the request still succeeds.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 9,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.client_options' => ['timeout' => 1, 'connect_timeout' => 1],
    ]);
    Broadcast::purge();
});

it('saves a message and answers 201 when the broadcast fails', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->postJson(route('message.send', $thread), ['body' => 'Still here', 'clientKey' => fake()->uuid()])
        ->assertCreated();

    expect(Message::query()->where('conversation_id', $thread->id)->where('body', 'Still here')->exists())->toBeTrue();
});

it('moves the read pointer and answers 200 when the broadcast fails', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);
    $message = textMessage($thread, $client);

    $this->actingAs($client)
        ->postJson(route('message.read', $thread), ['lastReadMessageId' => $message->id])
        ->assertSuccessful();
});
