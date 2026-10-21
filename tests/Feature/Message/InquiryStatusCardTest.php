<?php

use App\Enums\ConversationType;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Events\InquiryStatusChanged;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\User;

/*
 * Inquiry cards (R9): every status an inquiry reaches, from the moment it is
 * sent, posts a card. It goes to the inquiry's group chat when there is one,
 * otherwise to the client's Regray Team thread.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
});

/** The client's Regray Team thread, or null if they have none. */
function regrayTeamThreadOf(User $client): ?Conversation
{
    return Conversation::query()
        ->where('type', ConversationType::Support->value)
        ->where('support_client_id', $client->id)
        ->first();
}

/** @return list<string> */
function inquiryCardStatusesIn(Conversation $conversation): array
{
    return Message::query()
        ->where('conversation_id', $conversation->id)
        ->where('type', MessageType::InquiryCard->value)
        ->orderBy('id')
        ->pluck('announced_status')
        ->all();
}

it('posts a "New inquiry" card into the Regray Team thread when a client sends an inquiry', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect();

    $inquiry = Inquiry::query()->where('client_id', $client->id)->sole();
    $card = Message::query()->where('conversation_id', $thread->id)->sole();

    expect($card->type)->toBe(MessageType::InquiryCard)
        ->and($card->inquiry_id)->toBe($inquiry->id)
        ->and($card->announced_status)->toBe(InquiryStatus::Pending->value)
        ->and($card->sender_id)->toBeNull();
});

it('creates the Regray Team thread for a client who has none yet', function (): void {
    $client = inquiryClient();
    expect(regrayTeamThreadOf($client))->toBeNull();

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect();

    $thread = regrayTeamThreadOf($client);

    expect($thread)->not->toBeNull()
        ->and(inquiryCardStatusesIn($thread))->toBe([InquiryStatus::Pending->value]);
});

it('posts nothing for an inquiry the server refuses', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->postJson(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['location' => 'Cebu'])]])
        ->assertUnprocessable();

    expect(Message::query()->where('conversation_id', $thread->id)->count())->toBe(0);
});

it('shows the card to the client and to the support pool, and previews it as "New inquiry"', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['name' => 'Kitchen island'])]])
        ->assertRedirect();
    $reference = Inquiry::query()->where('client_id', $client->id)->value('reference');

    foreach ([$client, messagingStaff(Role::Marketing)] as $viewer) {
        $this->actingAs($viewer)->getJson(route('message.thread', $thread))
            ->assertOk()
            ->assertJsonPath('messages.0.type', MessageType::InquiryCard->value)
            ->assertJsonPath('messages.0.inquiryCard.reference', $reference)
            ->assertJsonPath('messages.0.inquiryCard.title', 'Kitchen island')
            ->assertJsonPath('messages.0.inquiryCard.announcedStatus', InquiryStatus::Pending->value);
    }

    $page = $this->actingAs($client)->get(route('message.index'))->viewData('page');
    $list = $this->actingAs($client)->get(route('message.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'message',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->json('props.conversations.data');

    expect(collect($list)->firstWhere('id', $thread->id)['preview'])->toBe("New Inquiry · {$reference}");
});

it('posts a status change into the Regray Team thread when the inquiry has no group', function (): void {
    $client = inquiryClient();
    makeInquiry($client, attributes: ['reference' => 'INQ-C3']);
    $thread = supportThreadFor($client);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C3'))->assertRedirect();

    expect(inquiryCardStatusesIn($thread))->toBe([InquiryStatus::Cancelled->value]);
});

it('posts into the inquiry\'s group, and not the Regray Team thread, once the inquiry has one', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-C1']);
    $thread = supportThreadFor($client);
    $group = groupConversation([messagingStaff(Role::Sales), $client], attributes: ['inquiry_id' => $inquiry->id]);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C1'))->assertRedirect();

    $card = Message::query()->where('conversation_id', $group->id)->sole();

    expect($card->type)->toBe(MessageType::InquiryCard)
        ->and($card->inquiry_id)->toBe($inquiry->id)
        ->and($card->announced_status)->toBe(InquiryStatus::Cancelled->value)
        ->and(inquiryCardStatusesIn($thread))->toBe([]);
});

it('does not post the same status change twice when the listener runs again', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-C2']);
    $thread = supportThreadFor($client);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C2'))->assertRedirect();
    event(new InquiryStatusChanged($inquiry->refresh(), InquiryStatus::Pending, InquiryStatus::Cancelled, $client->id));

    expect(inquiryCardStatusesIn($thread))->toBe([InquiryStatus::Cancelled->value]);
});

it('keeps the sent and the cancelled card in order in one thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput()]])
        ->assertRedirect();
    $reference = Inquiry::query()->where('client_id', $client->id)->value('reference');
    $this->actingAs($client)->patchJson(route('quote.cancel', $reference))->assertRedirect();

    expect(inquiryCardStatusesIn($thread))->toBe([
        InquiryStatus::Pending->value,
        InquiryStatus::Cancelled->value,
    ]);
});
