<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\MessageType;
use App\Enums\Role;
use App\Events\QuotationSent;
use App\Models\Conversation;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

/*
 * Quotation cards: every quotation Sales sends, a revision included, posts a
 * card of its own into the thread inquiry cards go to, right after the
 * inquiry card that announces the move. A card is a snapshot of the document;
 * opening it is the inquiry's file route, with its visibility rule.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Storage::fake(config('inquiry.disk'));
    $this->client = inquiryClient();
    $this->sales = User::factory()->role(Role::Sales)->create();
});

/** Sales sends a quotation (`quoted`) or a revision of it, with the PDF named `$fileName`. */
function sendQuotation(User $sales, string $reference, string $to, string $fileName, int $kilobytes = 12): void
{
    test()->actingAs($sales)
        ->post(route('inquiries.status', $reference), [
            'track' => 'sales',
            'to' => $to,
            'note' => 'Sent to the client.',
            'quotedTotal' => '1500.00',
            'files' => ['quotation' => [UploadedFile::fake()->create($fileName, $kilobytes, 'application/pdf')]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
}

/** @return list<string> Each message's type in the thread, oldest first. */
function messageTypesIn(Conversation $conversation): array
{
    return Message::query()
        ->where('conversation_id', $conversation->id)
        ->orderBy('id')
        ->pluck('type')
        ->map(fn (MessageType $type): string => $type->value)
        ->all();
}

/** An inquiry in negotiation, with three stones, that the sales member reads. */
function quotableInquiry(User $client, User $sales, string $reference = 'INQ-Q1'): Inquiry
{
    $inquiry = makeInquiry($client, [
        'name' => 'Kitchen renovation',
        'stones' => [offeredStoneSlug('slab-supply-only'), offeredStoneSlug('slab-supply-only', 1), offeredStoneSlug('slab-supply-only', 2)],
    ], ['reference' => $reference, 'status' => InquiryStatus::Negotiation]);
    letStaffRead($inquiry, $sales);

    return $inquiry;
}

it('posts a quotation card after the "Quotation sent" inquiry card when Sales sends a quotation', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);

    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');

    $file = $inquiry->files()->where('document_type', DocumentType::Quotation)->sole();
    $card = Message::query()->where('conversation_id', $thread->id)->where('type', MessageType::QuotationCard->value)->sole();

    // The inquiry card is kept, and the quotation card is right after it.
    expect(messageTypesIn($thread))->toBe([MessageType::InquiryCard->value, MessageType::QuotationCard->value])
        ->and(Message::query()->where('type', MessageType::InquiryCard->value)->value('announced_status'))->toBe(InquiryStatus::Quoted->value)
        ->and($card->inquiry_id)->toBe($inquiry->id)
        ->and($card->inquiry_file_id)->toBe($file->id)
        ->and($card->sender_id)->toBeNull()
        // jsonb keeps its own key order, so the keys are compared as a set.
        ->and($card->card_snapshot)->toEqual([
            'fileName' => 'QUO-0002_Regray Stone.pdf',
            'storedName' => $file->stored_name,
            'sizeBytes' => $file->size_bytes,
            'revision' => 1,
            'materialCount' => 3,
            'inquiryName' => 'Kitchen renovation',
            'reference' => 'INQ-Q1',
        ]);
});

it('posts a second card for a revision and leaves the first as it was', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);

    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');
    $first = Message::query()->where('type', MessageType::QuotationCard->value)->sole();
    $firstSnapshot = $first->card_snapshot;

    sendQuotation($this->sales, 'INQ-Q1', 'revision', 'QUO-0002_Regray Stone r2.pdf', 24);

    $cards = Message::query()->where('type', MessageType::QuotationCard->value)->orderBy('id')->get();
    $files = $inquiry->files()->where('document_type', DocumentType::Quotation)->orderBy('revision')->get();

    expect($cards)->toHaveCount(2)
        ->and($cards[0]->is($first))->toBeTrue()
        ->and($cards[0]->refresh()->card_snapshot)->toBe($firstSnapshot)
        ->and($cards[0]->inquiry_file_id)->toBe($files[0]->id)
        ->and($cards[1]->inquiry_file_id)->toBe($files[1]->id)
        ->and($cards[1]->card_snapshot['fileName'])->toBe('QUO-0002_Regray Stone r2.pdf')
        ->and($cards[1]->card_snapshot['revision'])->toBe(2)
        ->and(messageTypesIn($thread))->toBe([
            MessageType::InquiryCard->value,
            MessageType::QuotationCard->value,
            MessageType::InquiryCard->value,
            MessageType::QuotationCard->value,
        ]);
});

it('posts one card per document however often the listener runs', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);
    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');
    $file = $inquiry->files()->where('document_type', DocumentType::Quotation)->sole();

    // A retried job dispatches the same event again.
    QuotationSent::dispatch($file);
    QuotationSent::dispatch($file);

    expect(Message::query()->where('conversation_id', $thread->id)->where('type', MessageType::QuotationCard->value)->count())->toBe(1)
        ->and(app(MessageRepositoryInterface::class)->createQuotationCardOnce($thread->id, $inquiry->id, $file->id, ['fileName' => 'x']))->toBeNull();
});

it('posts into the inquiry\'s group chat once it has one', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);
    $group = groupConversation([$this->sales, $this->client], attributes: ['inquiry_id' => $inquiry->id]);

    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');

    expect(messageTypesIn($group))->toBe([MessageType::InquiryCard->value, MessageType::QuotationCard->value])
        ->and(messageTypesIn($thread))->toBe([]);
});

it('posts nothing for a walk-in client\'s inquiry with no group chat, and still sends the quotation', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $inquiry->forceFill(['client_id' => null, 'client_record_id' => walkInClient()->id, 'created_by' => $this->sales->id])->save();

    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');

    expect($inquiry->files()->where('document_type', DocumentType::Quotation)->count())->toBe(1)
        ->and(Message::query()->count())->toBe(0);
});

it('posts nothing when the move is refused', function (): void {
    quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);

    $this->actingAs($this->sales)
        ->postJson(route('inquiries.status', 'INQ-Q1'), ['track' => 'sales', 'to' => 'quoted', 'note' => 'Sent.', 'quotedTotal' => '0', 'files' => ['quotation' => [UploadedFile::fake()->create('q.pdf', 12, 'application/pdf')]]])
        ->assertUnprocessable();

    expect(messageTypesIn($thread))->toBe([]);
});

it('does not post for the other documents a status move stores', function (): void {
    Event::fake([QuotationSent::class]);
    $inquiry = quotableInquiry($this->client, $this->sales);
    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');

    $this->actingAs($this->sales)
        ->post(route('inquiries.status', 'INQ-Q1'), [
            'track' => 'sales',
            'to' => 'won',
            'note' => 'Approved.',
            'files' => ['job_order' => [UploadedFile::fake()->create('jo.pdf', 12, 'application/pdf')]],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Event::assertDispatchedTimes(QuotationSent::class, 1);
    Event::assertDispatched(QuotationSent::class, fn (QuotationSent $event): bool => $event->file->document_type === DocumentType::Quotation
        && $event->file->inquiry_id === $inquiry->id);
});

it('sends the card its snapshot, a View link and a Download link, and previews it by the file name', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);
    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf', 2867);
    $file = $inquiry->files()->where('document_type', DocumentType::Quotation)->sole();

    // Renaming the inquiry later does not rewrite the card.
    $inquiry->forceFill(['name' => 'Renamed'])->save();

    $messages = $this->actingAs($this->client)->getJson(route('inbox.thread', $thread))->assertOk()->json('messages');
    $card = collect($messages)->firstWhere('type', MessageType::QuotationCard->value);

    expect($card['inquiryCard'])->toBeNull()
        ->and($card['scheduleCard'])->toBeNull()
        ->and($card['body'])->toBeNull()
        ->and($card['quotationCard'])->toBe([
            'fileName' => 'QUO-0002_Regray Stone.pdf',
            'sizeBytes' => $file->size_bytes,
            'revision' => 1,
            'materialCount' => 3,
            'inquiryName' => 'Kitchen renovation',
            'reference' => 'INQ-Q1',
            'viewUrl' => route('inquiry.files.show', ['reference' => 'INQ-Q1', 'storedName' => $file->stored_name], absolute: false),
            'downloadUrl' => route('inquiry.files.show', ['reference' => 'INQ-Q1', 'storedName' => $file->stored_name, 'download' => 1], absolute: false),
            'occurredAt' => Message::query()->where('type', MessageType::QuotationCard->value)->sole()->created_at->toIso8601String(),
        ]);

    // The links open the file inline and as an attachment.
    $this->actingAs($this->client)->get($card['quotationCard']['viewUrl'])
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($this->actingAs($this->client)->get($card['quotationCard']['viewUrl'])->headers->get('Content-Disposition'))
        ->toStartWith('inline;');
    expect($this->actingAs($this->client)->get($card['quotationCard']['downloadUrl'])->assertOk()->headers->get('Content-Disposition'))
        ->toStartWith('attachment;');

    $page = $this->actingAs($this->client)->get(route('inbox.index'))->viewData('page');
    $list = $this->actingAs($this->client)->get(route('inbox.index'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $page['version'],
        'X-Inertia-Partial-Component' => 'inbox',
        'X-Inertia-Partial-Data' => 'conversations',
    ])->json('props.conversations.data');

    expect(collect($list)->firstWhere('id', $thread->id)['preview'])->toBe('Quotation · QUO-0002_Regray Stone.pdf');
});

it('counts no materials on a service-only inquiry', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $inquiry->stones()->delete();
    $thread = supportThreadFor($this->client);

    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');

    $card = collect($this->actingAs($this->client)->getJson(route('inbox.thread', $thread))->json('messages'))
        ->firstWhere('type', MessageType::QuotationCard->value);

    expect($card['quotationCard']['materialCount'])->toBe(0);
});

it('leaves who may open an older revision to the file route', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);
    sendQuotation($this->sales, 'INQ-Q1', 'quoted', 'QUO-0002_Regray Stone.pdf');
    sendQuotation($this->sales, 'INQ-Q1', 'revision', 'QUO-0002_Regray Stone r2.pdf');

    $cards = collect($this->actingAs($this->client)->getJson(route('inbox.thread', $thread))->json('messages'))
        ->where('type', MessageType::QuotationCard->value)
        ->pluck('quotationCard')
        ->values();

    // The client sees only the latest revision (InquiryFileVisibility); sales sees every one.
    $this->actingAs($this->client)->get($cards[0]['viewUrl'])->assertNotFound();
    $this->actingAs($this->client)->get($cards[1]['viewUrl'])->assertOk();
    $this->actingAs($this->sales)->get($cards[0]['viewUrl'])->assertOk();

    // Nor does a thread reader who may not open the inquiry.
    $this->actingAs(inquiryClient())->get($cards[1]['viewUrl'])->assertNotFound();
});

it('stores no card for a quotation the database would not take as one', function (): void {
    $inquiry = quotableInquiry($this->client, $this->sales);
    $thread = supportThreadFor($this->client);
    $file = addInquiryFile($inquiry, DocumentType::Quotation, $this->sales, 1);

    // A quotation card without its snapshot is refused by chk_messages_type.
    expect(fn () => Message::query()->insert([
        'conversation_id' => $thread->id,
        'type' => MessageType::QuotationCard->value,
        'inquiry_id' => $inquiry->id,
        'inquiry_file_id' => $file->id,
        'created_at' => now(),
    ]))->toThrow(QueryException::class, 'chk_messages_type')
        ->and($file)->toBeInstanceOf(InquiryFile::class);
});
