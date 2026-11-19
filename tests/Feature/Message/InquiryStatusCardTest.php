<?php

use App\Enums\ConversationType;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\MessageRepositoryInterface;

/*
 * Inquiry cards (R9): every change to what an inquiry shows, from the moment
 * it is sent, posts a card with the inquiry's snapshot: its display status
 * and its three role statuses. It goes to the inquiry's group chat when there
 * is one, otherwise to the client's Regray Team thread. Nothing about the
 * inquiry's lead is on a card.
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

it('posts a "New inquiry" card with no role status in its snapshot into the Regray Team thread when a client sends an inquiry', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))
        ->assertRedirect();

    $inquiry = Inquiry::query()->where('client_id', $client->id)->sole();
    $card = Message::query()->where('conversation_id', $thread->id)->sole();

    expect($card->type)->toBe(MessageType::InquiryCard)
        ->and($card->inquiry_id)->toBe($inquiry->id)
        ->and($card->announced_status)->toBe(InquiryStatus::Pending->value)
        // Sales, payment and logistics have not started.
        ->and($card->announced_sales_status)->toBeNull()
        ->and($card->announced_payment_status)->toBeNull()
        ->and($card->announced_process_status)->toBeNull()
        ->and($card->sender_id)->toBeNull();
});

it('posts one card per inquiry when a client sends several', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    foreach (['Kitchen', 'Bath'] as $name) {
        $this->actingAs($client)
            ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['name' => $name])]))
            ->assertRedirect();
    }

    expect(Message::query()->where('conversation_id', $thread->id)->pluck('inquiry_id')->all())
        ->toEqualCanonicalizing(Inquiry::query()->pluck('id')->all())
        ->toHaveCount(2);
});

it('creates the Regray Team thread for a client who has none yet', function (): void {
    $client = inquiryClient();
    expect(regrayTeamThreadOf($client))->toBeNull();

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))
        ->assertRedirect();

    $thread = regrayTeamThreadOf($client);

    expect($thread)->not->toBeNull()
        ->and(inquiryCardStatusesIn($thread))->toBe([InquiryStatus::Pending->value]);
});

it('posts nothing for an inquiry the server refuses', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->postJson(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['location' => 'Cebu'])]))
        ->assertUnprocessable();

    expect(Message::query()->where('conversation_id', $thread->id)->count())->toBe(0);
});

it('shows the card to the client and to the support pool, and previews it as "New inquiry"', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput(['name' => 'Kitchen island'])]))
        ->assertRedirect();
    $reference = Inquiry::query()->where('client_id', $client->id)->value('reference');

    foreach ([$client, messagingStaff(Role::Marketing)] as $viewer) {
        $this->actingAs($viewer)->getJson(route('inbox.thread', $thread))
            ->assertOk()
            ->assertJsonPath('messages.0.type', MessageType::InquiryCard->value)
            ->assertJsonPath('messages.0.inquiryCard.reference', $reference)
            ->assertJsonPath('messages.0.inquiryCard.name', 'Kitchen island')
            ->assertJsonPath('messages.0.inquiryCard.location', 'Makati')
            ->assertJsonMissingPath('messages.0.inquiryCard.projectCount')
            ->assertJsonMissingPath('messages.0.projectCard')
            ->assertJsonPath('messages.0.inquiryCard.announcedStatus', InquiryStatus::Pending->value)
            ->assertJsonMissingPath('messages.0.inquiryCard.announcedMarketingStatus')
            ->assertJsonPath('messages.0.inquiryCard.announcedPaymentStatus', null);
    }

    $page = $this->actingAs($client)->get(route('inbox.index'))->viewData('page');
    $list = $this->actingAs($client)->get(route('inbox.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'inbox',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->json('props.conversations.data');

    expect(collect($list)->firstWhere('id', $thread->id)['preview'])->toBe("New Inquiry · {$reference}");
});

it('keeps the inquiry\'s status now from a support-pool member who may not view the inquiry', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, [], ['reference' => 'INQ-P2', 'status' => InquiryStatus::Production]);
    $thread = supportThreadFor($client);
    app(MessageRepositoryInterface::class)->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Pending, null, null, null);
    $pool = inquiryClient(Role::Marketing);

    // The unclaimed thread is open to the pool, but this inquiry is not theirs to read.
    expect($inquiry->isVisibleTo($pool))->toBeFalse();

    $card = $this->actingAs($pool)->getJson(route('inbox.thread', $thread))
        ->assertOk()
        ->json('messages.0.inquiryCard');

    expect($card['reference'])->toBe('INQ-P2')
        ->and($card['announcedStatus'])->toBe('pending')
        ->and($card['status'])->toBeNull();

    $this->actingAs($client)->getJson(route('inbox.thread', $thread))
        ->assertJsonPath('messages.0.inquiryCard.status', 'production');
});

it('sends the snapshot the card was posted for, not the inquiry as it is now, and previews it by its display status', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, ['name' => 'Kitchen island'], ['reference' => 'INQ-P1', 'status' => InquiryStatus::Production, 'payment_status' => PaymentStatus::Partial]);
    $thread = supportThreadFor($client);
    app(MessageRepositoryInterface::class)->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Production, SalesStatus::Quoted, PaymentStatus::Partial, ProcessStatus::Production);
    showInquiryAs($inquiry, InquiryStatus::Closed);

    $card = $this->actingAs($client)->getJson(route('inbox.thread', $thread))
        ->assertOk()
        ->json('messages.0.inquiryCard');

    expect($card)->toMatchArray([
        'reference' => 'INQ-P1',
        'name' => 'Kitchen island',
        'location' => 'Makati',
        'stoneCount' => 1,
        'announcedStatus' => 'production',
        'announcedSalesStatus' => 'quoted',
        'announcedPaymentStatus' => 'partial',
        'announcedProcessStatus' => 'production',
        'status' => 'closed',
        'occurredAt' => Message::query()->sole()->created_at->toIso8601String(),
    ]);

    $page = $this->actingAs($client)->get(route('inbox.index'))->viewData('page');
    $list = $this->actingAs($client)->get(route('inbox.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'inbox',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->json('props.conversations.data');

    expect(collect($list)->firstWhere('id', $thread->id)['preview'])->toBe('Inquiry INQ-P1 · Production');
});

it('posts a snapshot once, however many times it is asked to, and a new one for any change', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client);
    $thread = supportThreadFor($client);
    $messages = app(MessageRepositoryInterface::class);

    $first = $messages->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Quoted, SalesStatus::Quoted, PaymentStatus::Awaiting, ProcessStatus::OrderReceived);
    $again = $messages->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Quoted, SalesStatus::Quoted, PaymentStatus::Awaiting, ProcessStatus::OrderReceived);
    // Only the payment moved: the display status is the same, the snapshot is not.
    $next = $messages->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Quoted, SalesStatus::Quoted, PaymentStatus::Partial, ProcessStatus::OrderReceived);
    // Stages not started are NULL, and a NULL snapshot is posted once too.
    $early = $messages->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Negotiation, SalesStatus::Negotiation, null, null);
    $earlyAgain = $messages->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Negotiation, SalesStatus::Negotiation, null, null);

    expect($first)->toBeInstanceOf(Message::class)
        ->and($first->type)->toBe(MessageType::InquiryCard)
        ->and($again)->toBeNull()
        ->and($next)->toBeInstanceOf(Message::class)
        ->and($early)->toBeInstanceOf(Message::class)
        ->and($early->announced_payment_status)->toBeNull()
        ->and($earlyAgain)->toBeNull()
        ->and(Message::query()->where('conversation_id', $thread->id)->count())->toBe(3);
});

it('posts a status change into the Regray Team thread when the inquiry has no group', function (): void {
    $client = inquiryClient();
    makeInquiry($client, attributes: ['reference' => 'INQ-C3', 'lead' => makeLead($client)]);
    $thread = supportThreadFor($client);

    $this->actingAs($client)->patchJson(route('quote.cancel', 'INQ-C3'))->assertRedirect();

    $card = Message::query()->where('conversation_id', $thread->id)->sole();

    // Sales had not started, so the cancel lost the lead; the card says only cancelled.
    expect($card->announced_status)->toBe(InquiryStatus::Cancelled->value)
        ->and($card->announced_sales_status)->toBeNull()
        ->and($card->getAttributes())->not->toHaveKey('announced_marketing_status');
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

it('keeps the sent and the cancelled card in order in one thread', function (): void {
    $client = inquiryClient();
    $thread = supportThreadFor($client);

    $this->actingAs($client)
        ->post(route('quote.inquire.store'), clientInquiryInput([inquiryProjectInput()]))
        ->assertRedirect();
    $reference = Inquiry::query()->where('client_id', $client->id)->value('reference');
    $this->actingAs($client)->patchJson(route('quote.cancel', $reference))->assertRedirect();

    expect(inquiryCardStatusesIn($thread))->toBe([
        InquiryStatus::Pending->value,
        InquiryStatus::Cancelled->value,
    ]);
});

it('sends the inquiry\'s one service, and up to three stone pictures with the rest counted', function (): void {
    $client = inquiryClient();
    $stones = array_map(fn (int $skip): string => offeredStoneSlug('slab-supply-only', $skip), range(0, 3));
    $inquiry = makeInquiry($client, ['name' => 'Kitchen island', 'stones' => $stones], ['reference' => 'INQ-SV1']);
    $thread = supportThreadFor($client);
    app(MessageRepositoryInterface::class)->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Pending, null, null, null);
    $service = inquiryService('slab-supply-only');

    $card = $this->actingAs($client)->getJson(route('inbox.thread', $thread))
        ->assertOk()
        ->json('messages.0.inquiryCard');

    expect($card['service'])->toBe(['id' => $service->slug, 'name' => $service->name])
        ->and($card['stones'])->toHaveCount(3)
        ->and(array_column($card['stones'], 'slug'))->toBe(array_slice($stones, 0, 3))
        ->and($card['stoneCount'])->toBe(4);
});

it('sends no service for an inquiry without one', function (): void {
    $client = inquiryClient();
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-SV2']);
    $inquiry->forceFill(['service_id' => null])->save();
    $thread = supportThreadFor($client);
    app(MessageRepositoryInterface::class)->createInquiryCardOnce($thread->id, $inquiry->id, InquiryStatus::Pending, null, null, null);

    $this->actingAs($client)->getJson(route('inbox.thread', $thread))
        ->assertOk()
        ->assertJsonPath('messages.0.inquiryCard.service', null);
});
