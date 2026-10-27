<?php

use App\Enums\MessageType;
use App\Enums\Role;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;

/*
 * Project cards: a snapshot of one project (its status, its quotation's and
 * its payment's) posted into the thread its inquiry's cards go to. Nothing
 * posts them yet but the seeder; the flow pass adds the listener.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
});

it('posts a project snapshot once, however many times it is asked to', function (): void {
    $client = inquiryClient();
    $project = makeInquiry($client)->projects->sole();
    $thread = supportThreadFor($client);
    $messages = app(MessageRepositoryInterface::class);

    $first = $messages->createProjectCardOnce($thread->id, $project->id, 'ongoing', 'closeable', 'partial');
    $again = $messages->createProjectCardOnce($thread->id, $project->id, 'ongoing', 'closeable', 'partial');
    $next = $messages->createProjectCardOnce($thread->id, $project->id, 'ongoing', 'closeable', 'fully_paid');

    expect($first)->toBeInstanceOf(Message::class)
        ->and($first->type)->toBe(MessageType::ProjectCard)
        ->and($again)->toBeNull()
        ->and($next)->toBeInstanceOf(Message::class)
        ->and(Message::query()->where('conversation_id', $thread->id)->count())->toBe(2);
});

it('shows the card to the client and to the support pool, and previews it as "Project {name} · {Status}"', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, [['name' => 'Kitchen island']], ['reference' => 'INQ-P1']);
    $thread = supportThreadFor($client);
    app(MessageRepositoryInterface::class)->createProjectCardOnce($thread->id, $inquiry->projects->sole()->id, 'ongoing', 'closeable', 'partial');

    foreach ([$client, messagingStaff(Role::Marketing)] as $viewer) {
        $this->actingAs($viewer)->getJson(route('message.thread', $thread))
            ->assertOk()
            ->assertJsonPath('messages.0.type', MessageType::ProjectCard->value)
            ->assertJsonPath('messages.0.projectCard', [
                'reference' => 'INQ-P1',
                'name' => 'Kitchen island',
                'location' => 'Makati',
                'projectStatus' => 'ongoing',
                'quotationStatus' => 'closeable',
                'paymentStatus' => 'partial',
                'occurredAt' => Message::query()->sole()->created_at->toIso8601String(),
            ])
            ->assertJsonPath('messages.0.inquiryCard', null);
    }

    $page = $this->actingAs($client)->get(route('message.index'))->viewData('page');
    $list = $this->actingAs($client)->get(route('message.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'message',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->json('props.conversations.data');

    expect(collect($list)->firstWhere('id', $thread->id)['preview'])->toBe('Project Kitchen island · Ongoing');
});
