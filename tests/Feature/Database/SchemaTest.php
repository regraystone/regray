<?php

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Furniture;
use App\Models\Stone;
use App\Models\StoneImage;
use App\Models\StoneType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * What PostgreSQL itself refuses: each CHECK, unique index, NOT NULL and
 * restricting foreign key the ERD names, one row each. The rules that tie one
 * status to another are the app's, so the tests after the dataset pin what the
 * database deliberately lets through.
 */

/** A new inquiry: no lead, and every stage left to NULL. */
function schemaInquiry(): int
{
    return DB::table('inquiries')->insertGetId([
        'reference' => 'INQ-S'.random_int(100000, 999999),
        'client_id' => User::factory()->create()->id,
        'name' => 'Kitchen',
        'location' => 'Makati',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** A lead for a client of its own unless one is given. A lead has no status. */
function schemaLead(?int $clientId = null, ?string $reference = null): int
{
    return DB::table('leads')->insertGetId([
        'reference' => $reference ?? 'LEAD-S'.random_int(100000, 999999),
        'client_id' => $clientId ?? User::factory()->create()->id,
        'marketing_user_id' => User::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function schemaHistory(int $inquiryId, ?string $track, ?string $from, string $to): void
{
    DB::table('inquiry_status_history')->insert(['inquiry_id' => $inquiryId, 'track' => $track, 'from_status' => $from, 'to_status' => $to, 'changed_at' => now()]);
}

function schemaFile(int $inquiryId, ?string $documentType, ?int $revision = null): void
{
    DB::table('inquiry_files')->insert([
        'inquiry_id' => $inquiryId,
        'document_type' => $documentType,
        'revision' => $revision,
        'disk' => 'local',
        'path' => 'inquiries/x.pdf',
        'original_name' => 'x.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 10,
        'created_at' => now(),
    ]);
}

function schemaTransaction(int $inquiryId, string $kind, string $amount): void
{
    DB::table('payment_transactions')->insert(['inquiry_id' => $inquiryId, 'kind' => $kind, 'amount' => $amount, 'received_at' => now(), 'created_at' => now()]);
}

function schemaSchedule(?int $clientId, ?int $inquiryId): int
{
    return DB::table('schedules')->insertGetId([
        'client_id' => $clientId,
        'booked_by_type' => $clientId === null ? 'staff' : 'client',
        'inquiry_id' => $inquiryId,
        'title' => 'Visit',
        'arrival_at' => now(),
        'status' => 'pending',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function schemaConversation(?int $inquiryId = null): int
{
    return DB::table('conversations')->insertGetId(['type' => 'group', 'name' => 'Schema test', 'inquiry_id' => $inquiryId, 'created_at' => now(), 'updated_at' => now()]);
}

/** @return array<string, mixed> */
function schemaCard(): array
{
    return [
        'conversation_id' => schemaConversation(),
        'type' => 'inquiry_card',
        'inquiry_id' => schemaInquiry(),
        'announced_status' => 'production',
        'announced_sales_status' => 'quoted',
        'announced_payment_status' => 'partial',
        'announced_process_status' => 'production',
        'created_at' => now(),
    ];
}

function schemaClient(string $kind = 'direct', ?int $userId = null): int
{
    return DB::table('clients')->insertGetId(['full_name' => 'Grace Lim', 'kind' => $kind, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
}

/** @return array<string, mixed> */
function schemaInquiryStone(int $inquiryId, int $quantity = 1, ?int $finishId = null): array
{
    return [
        'inquiry_id' => $inquiryId,
        'stone_id' => Stone::factory()->create(['slug' => 'schema-stone', 'name' => 'Schema Stone'])->id,
        'edge_profile_id' => EdgeProfile::create(['name' => 'Beveled', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1])->id,
        'finish_id' => $finishId ?? Finish::create(['name' => 'Polished', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1])->id,
        'quantity' => $quantity,
        'slab_size' => '120 x 60 cm',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

/**
 * Runs `$write` in a savepoint, so the test's transaction survives it, and
 * checks `$constraint` refused it. `not-null` stands for a NOT NULL column,
 * which PostgreSQL reports by SQLSTATE.
 */
function expectRefusedBy(string $constraint, Closure $write): void
{
    try {
        DB::transaction(fn () => $write());
    } catch (QueryException $exception) {
        $constraint === 'not-null'
            ? expect($exception->getCode())->toBe('23502')
            : expect($exception->getMessage())->toContain($constraint);

        return;
    }

    test()->fail("Expected {$constraint} to refuse the write.");
}

test('refuses a row that breaks a constraint', function (string $constraint, Closure $write) {
    expectRefusedBy($constraint, $write);
})->with([
    // Inquiries: each role status takes only its own values.
    'sales: a display value' => ['chk_inquiries_sales_status', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['sales_status' => 'cancelled'])],
    'payment: an unknown value' => ['chk_inquiries_payment_status', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['payment_status' => 'overpaid'])],
    'process: the old received' => ['chk_inquiries_process_status', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['process_status' => 'received'])],
    'quoted total of 0' => ['chk_inquiries_quoted_total', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['quoted_total' => 0])],
    'quote revision of 0' => ['chk_inquiries_quote_revision', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['quote_revision' => 0])],
    // Waiting for marketing is NULL, not idle.
    'marketing: idle stored' => ['chk_inquiries_marketing_status', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['marketing_status' => 'idle'])],
    'marketing: an inquiry value' => ['chk_inquiries_marketing_status', fn () => DB::table('inquiries')->where('id', schemaInquiry())->update(['marketing_status' => 'negotiation'])],

    // Leads and their history.
    'lead: a reference twice' => ['leads_reference_unique', fn () => schemaLead(reference: DB::table('leads')->where('id', schemaLead())->value('reference'))],
    'lead: no marketing user' => ['not-null', fn () => DB::table('leads')->where('id', schemaLead())->update(['marketing_user_id' => null])],
    'lead: a second lead for the client' => ['leads_client_id_unique', function () {
        $clientId = User::factory()->create()->id;
        schemaLead($clientId);
        schemaLead($clientId);
    }],
    'lead history: to an inquiry value' => ['chk_lead_status_history_to_status', fn () => DB::table('lead_status_history')->insert(['lead_id' => schemaLead(), 'to_status' => 'pending', 'changed_at' => now()])],
    'lead history: from an inquiry value' => ['chk_lead_status_history_from_status', fn () => DB::table('lead_status_history')->insert(['lead_id' => schemaLead(), 'from_status' => 'negotiation', 'to_status' => 'won', 'changed_at' => now()])],
    'deleting a lead with inquiries' => ['inquiries_lead_id_foreign', function () {
        $leadId = schemaLead();
        DB::table('inquiries')->where('id', schemaInquiry())->update(['lead_id' => $leadId]);
        DB::table('leads')->where('id', $leadId)->delete();
    }],

    // Status history.
    'history: an unknown track' => ['chk_inquiry_status_history_track', fn () => schemaHistory(schemaInquiry(), 'accounting', null, 'awaiting')],
    'history: the old marketing track' => ['chk_inquiry_status_history_track', fn () => schemaHistory(schemaInquiry(), 'marketing', null, 'negotiation')],
    'history: to a lead value' => ['chk_inquiry_status_history_to_status', fn () => schemaHistory(schemaInquiry(), 'sales', null, 'idle')],
    'history: from an unknown value' => ['chk_inquiry_status_history_from_status', fn () => schemaHistory(schemaInquiry(), 'sales', 'draft', 'negotiation')],
    'history: no track' => ['not-null', fn () => schemaHistory(schemaInquiry(), null, null, 'negotiation')],

    // Files, payments, visits and stones.
    'file: an unknown document type' => ['chk_inquiry_files_document_type', fn () => schemaFile(schemaInquiry(), 'contract')],
    'file: no document type' => ['not-null', fn () => schemaFile(schemaInquiry(), null)],
    'file: revision 0' => ['chk_inquiry_files_revision', fn () => schemaFile(schemaInquiry(), 'quotation', 0)],
    'payment: an amount of 0' => ['chk_payment_transactions_amount', fn () => schemaTransaction(schemaInquiry(), 'payment', '0')],
    'payment: a negative refund' => ['chk_payment_transactions_amount', fn () => schemaTransaction(schemaInquiry(), 'refund', '-10.00')],
    'payment: an unknown kind' => ['chk_payment_transactions_kind', fn () => schemaTransaction(schemaInquiry(), 'deposit', '10.00')],
    'visit: a second deadline visit' => ['idx_schedules_inquiry_once', function () {
        $inquiryId = schemaInquiry();
        $clientId = DB::table('inquiries')->where('id', $inquiryId)->value('client_id');
        schemaSchedule($clientId, $inquiryId);
        schemaSchedule($clientId, $inquiryId);
    }],
    'stone: the same stone, edge, finish and slab size twice' => ['idx_inquiry_stones_unique', function () {
        $row = schemaInquiryStone(schemaInquiry());
        DB::table('inquiry_stones')->insert($row);
        DB::table('inquiry_stones')->insert([...$row, 'quantity' => 3]);
    }],
    'stone: a quantity of 0' => ['chk_inquiry_stones_quantity', fn () => DB::table('inquiry_stones')->insert(schemaInquiryStone(schemaInquiry(), 0))],
    'stone: no slab size' => ['not-null', fn () => DB::table('inquiry_stones')->insert([...schemaInquiryStone(schemaInquiry()), 'slab_size' => null])],

    // Group chats: one per inquiry, and only a group has one.
    'a second group chat for an inquiry' => ['idx_conversations_inquiry_group', function () {
        $inquiryId = schemaInquiry();
        schemaConversation($inquiryId);
        schemaConversation($inquiryId);
    }],
    'a direct chat bound to an inquiry' => ['chk_conversations_inquiry', fn () => DB::table('conversations')->insert(['type' => 'direct', 'direct_key' => str_repeat('a', 64), 'inquiry_id' => schemaInquiry(), 'created_at' => now(), 'updated_at' => now()])],
    'deleting an inquiry with a group chat' => ['conversations_inquiry_id_foreign', function () {
        $inquiryId = schemaInquiry();
        schemaConversation($inquiryId);
        DB::table('inquiries')->where('id', $inquiryId)->delete();
    }],

    // Cards.
    'card: no display status' => ['chk_messages_type', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_status' => null])],
    'card: no inquiry' => ['chk_messages_type', fn () => DB::table('messages')->insert([...schemaCard(), 'inquiry_id' => null])],
    'card: with words' => ['chk_messages_type', fn () => DB::table('messages')->insert([...schemaCard(), 'body' => 'Hello'])],
    'card: a lead value as display status' => ['chk_messages_type', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_status' => 'idle'])],
    'card: completed as display status' => ['chk_messages_type', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_status' => 'completed'])],
    'card: an unknown sales value' => ['chk_messages_announced_sales_status', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_sales_status' => 'draft'])],
    'card: an unknown payment value' => ['chk_messages_announced_payment_status', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_payment_status' => 'overpaid'])],
    'card: an unknown process value' => ['chk_messages_announced_process_status', fn () => DB::table('messages')->insert([...schemaCard(), 'announced_process_status' => 'fabrication'])],
    'card: the same snapshot twice' => ['idx_messages_inquiry_card_once', function () {
        $card = schemaCard();
        DB::table('messages')->insert($card);
        DB::table('messages')->insert($card);
    }],
    'card: the same snapshot with stages not started twice' => ['idx_messages_inquiry_card_once', function () {
        $card = [...schemaCard(), 'announced_status' => 'negotiation', 'announced_sales_status' => 'negotiation', 'announced_payment_status' => null, 'announced_process_status' => null];
        DB::table('messages')->insert($card);
        DB::table('messages')->insert($card);
    }],
    'schedule card: with a snapshot' => ['chk_messages_type', fn () => DB::table('messages')->insert(['conversation_id' => schemaConversation(), 'type' => 'schedule_card', 'schedule_id' => schemaSchedule(User::factory()->create()->id, null), 'announced_status' => 'confirmed', 'announced_payment_status' => 'awaiting', 'created_at' => now()])],
    'text message: with a snapshot' => ['chk_messages_type', fn () => DB::table('messages')->insert(['conversation_id' => schemaConversation(), 'type' => 'text', 'body' => 'Hello', 'announced_sales_status' => 'quoted', 'created_at' => now()])],
    'the old project card' => ['chk_messages_type', fn () => DB::table('messages')->insert(['conversation_id' => schemaConversation(), 'type' => 'project_card', 'inquiry_id' => schemaInquiry(), 'announced_status' => 'pending', 'created_at' => now()])],

    // Clients and their contacts.
    'client: the walk_in kind' => ['chk_clients_kind', fn () => schemaClient('walk_in')],
    'client: an account twice' => ['clients_user_id_unique', function () {
        $userId = User::factory()->create()->id;
        schemaClient('platform', $userId);
        schemaClient('platform', $userId);
    }],
    'client: no name' => ['not-null', fn () => DB::table('clients')->insert(['kind' => 'direct', 'full_name' => null, 'created_at' => now(), 'updated_at' => now()])],
    'contact: the same contact for two clients' => ['idx_client_contacts_once', function () {
        $contact = ['kind' => 'phone', 'value' => '+639175550142', 'created_at' => now()];
        DB::table('client_contacts')->insert([...$contact, 'client_id' => schemaClient()]);
        DB::table('client_contacts')->insert([...$contact, 'client_id' => schemaClient()]);
    }],
    'contact: an unknown kind' => ['chk_client_contacts_kind', fn () => DB::table('client_contacts')->insert(['client_id' => schemaClient(), 'kind' => 'fax', 'value' => '123', 'created_at' => now()])],
    'deleting a direct client with an inquiry' => ['inquiries_client_record_id_foreign', function () {
        $clientId = schemaClient();
        DB::table('inquiries')->insert(['reference' => 'INQ-SD1', 'client_id' => null, 'client_record_id' => $clientId, 'name' => 'Counter', 'location' => 'Pasig', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('clients')->where('id', $clientId)->delete();
    }],

    // The stone catalogue.
    'stone: a size not written WxH' => ['chk_stones_size', fn () => Stone::factory()->create(['size' => '20X120'])],
    'stone: a fractional size' => ['chk_stones_size', fn () => Stone::factory()->create(['size' => '20.5x120'])],
    'stone: a thickness of 0' => ['chk_stones_thickness_positive', fn () => Stone::factory()->create(['thickness_mm' => 0])],
    'stone: a name in another case' => ['idx_stones_name_unique_not_deleted', function () {
        Stone::factory()->create(['name' => 'Cosmic Black']);
        Stone::factory()->create(['name' => 'CoSmIc BlAcK']);
    }],
    'stone: a slug in another case' => ['idx_stones_slug_not_deleted', function () {
        Stone::factory()->create(['slug' => 'cosmic-black']);
        Stone::factory()->create(['slug' => 'CoSmIc-BlAcK']);
    }],
    'stone: two gallery images in one position' => ['idx_stone_images_unique', function () {
        $stone = Stone::factory()->create();
        $stone->images()->create(['path' => 'stones/a.jpg', 'sort_order' => 0]);
        $stone->images()->create(['path' => 'stones/b.jpg', 'sort_order' => 0]);
    }],
    'type: a slug in another case' => ['idx_stone_types_slug_not_deleted', function () {
        StoneType::factory()->create(['slug' => 'granite']);
        StoneType::factory()->create(['slug' => 'GRANITE']);
    }],
    'type: a name in another case' => ['idx_stone_types_name_unique_not_deleted', function () {
        StoneType::factory()->create(['name' => 'Granite']);
        StoneType::factory()->create(['name' => 'GRANITE']);
    }],
    'removing a brand its stones use' => ['stones_brand_id_foreign', fn () => Stone::factory()->create()->brand->delete()],
    'removing a brand its furniture uses' => ['furniture_brand_id_foreign', fn () => Furniture::factory()->create()->brand->delete()],
    'removing a type its stones use' => ['stones_stone_type_id_foreign', fn () => Stone::factory()->create()->type->forceDelete()],
]);

test('starts an inquiry with no lead and no stage, and takes every role value or NULL', function () {
    $id = schemaInquiry();

    $this->assertDatabaseHas('inquiries', [
        'id' => $id, 'lead_id' => null, 'quoted_total' => null, 'quote_revision' => null,
        'sales_status' => null, 'payment_status' => null, 'process_status' => null,
    ]);

    foreach (['negotiation', 'site_visited', 'on_hold', 'quoted', 'rejected'] as $sales) {
        DB::table('inquiries')->where('id', $id)->update(['sales_status' => $sales]);
    }
    foreach (['awaiting', 'partial', 'fully_paid'] as $payment) {
        DB::table('inquiries')->where('id', $id)->update(['payment_status' => $payment]);
    }
    foreach (['order_received', 'measure', 'sourcing', 'production', 'quality_check', 'ready', 'dispatched', 'installation', 'completed', 'on_hold', 'delayed'] as $process) {
        DB::table('inquiries')->where('id', $id)->update(['process_status' => $process]);
    }

    foreach (['won', 'lost'] as $marketing) {
        DB::table('inquiries')->where('id', $id)->update(['marketing_status' => $marketing]);
    }

    DB::table('inquiries')->where('id', $id)->update(['marketing_status' => null, 'sales_status' => null, 'payment_status' => null, 'process_status' => null]);
    $this->assertDatabaseHas('inquiries', ['id' => $id, 'marketing_status' => null, 'sales_status' => null]);
});

test('leaves the rules between rows and statuses to the app', function () {
    $id = schemaInquiry();

    // Statuses are not tied to each other: quoted with no quotation, paid with
    // no process, logistics with no job order.
    DB::table('inquiries')->where('id', $id)->update(['sales_status' => 'quoted', 'payment_status' => 'fully_paid']);
    DB::table('inquiries')->where('id', $id)->update(['payment_status' => null, 'process_status' => 'dispatched']);

    // A history value is not tied to its track.
    schemaHistory($id, 'sales', null, 'fully_paid');

    // Platform if and only if there is an account is ClientService's rule.
    schemaClient('direct', User::factory()->create()->id);

    // A NULL card column is a stage not started.
    DB::table('messages')->insert([...schemaCard(), 'announced_status' => 'pending', 'announced_sales_status' => null, 'announced_payment_status' => null, 'announced_process_status' => null]);

    $this->assertDatabaseHas('inquiries', ['id' => $id, 'sales_status' => 'quoted', 'payment_status' => null, 'process_status' => 'dispatched']);
});

test('allows as many inquiries on a lead, bookings, unbound groups and new snapshots as needed', function () {
    $clientId = User::factory()->create()->id;
    $leadId = schemaLead($clientId);

    foreach (['won', 'lost', null] as $marketing) {
        DB::table('inquiries')->where('id', schemaInquiry())->update(['lead_id' => $leadId, 'marketing_status' => $marketing]);
    }

    schemaSchedule($clientId, null);
    schemaSchedule($clientId, null);

    schemaConversation();
    schemaConversation();

    // Any column of the snapshot moving is a new card.
    $card = schemaCard();
    DB::table('messages')->insert($card);
    DB::table('messages')->insert([...$card, 'announced_payment_status' => 'fully_paid']);

    // The same number as another kind is another contact.
    $contact = ['client_id' => schemaClient(), 'value' => '+639175550142', 'created_at' => now()];
    DB::table('client_contacts')->insert([...$contact, 'kind' => 'phone']);
    DB::table('client_contacts')->insert([...$contact, 'kind' => 'viber']);

    expect(DB::table('inquiries')->where('lead_id', $leadId)->count())->toBe(3)
        ->and(DB::table('schedules')->whereNull('inquiry_id')->count())->toBe(2)
        ->and(DB::table('messages')->where('type', 'inquiry_card')->count())->toBe(2)
        ->and(DB::table('client_contacts')->count())->toBe(2);
});

test('frees a stone or type identifier when it is soft-deleted, and drops a stone\'s gallery with it', function () {
    $type = StoneType::factory()->create(['slug' => 'granite', 'name' => 'Granite']);
    $stone = Stone::factory()->for($type, 'type')->create(['slug' => 'test-stone', 'name' => 'Test Stone']);
    $image = $stone->images()->create(['path' => 'stones/gallery.jpg', 'sort_order' => 0]);

    $stone->delete();
    $type->delete();

    $newType = StoneType::factory()->create(['slug' => 'granite', 'name' => 'Granite']);
    Stone::factory()->for($newType, 'type')->create(['slug' => 'test-stone', 'name' => 'Test Stone']);

    $stone->forceDelete();
    expect(StoneImage::query()->find($image->id))->toBeNull();
});
