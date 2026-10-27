<?php

namespace Tests\Feature\Database;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Stone;
use App\Models\User;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_unique_indexes_allow_reuse_after_soft_delete(): void
    {
        $stone = Stone::factory()->create([
            'slug' => 'test-stone',
            'name' => 'Test Stone',
            'thumbnail_path' => '/path/to/thumb.jpg',
            'thickness_mm' => 50,
            'recommended' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $stone->delete();

        $newStone = Stone::factory()->for($stone->category, 'category')->create([
            'slug' => 'test-stone',
            'name' => 'Test Stone',
            'thumbnail_path' => '/path/to/thumb2.jpg',
            'thickness_mm' => 50,
            'recommended' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->assertNotEquals($stone->id, $newStone->id);
    }

    public function test_a_new_inquiry_starts_with_marketing_pending_and_the_later_stages_null(): void
    {
        $id = $this->insertInquiry();

        $this->assertDatabaseHas('inquiries', [
            'id' => $id,
            'marketing_status' => 'pending',
            'sales_status' => null,
            'sales_status_changed_at' => null,
            'payment_status' => null,
            'payment_status_changed_at' => null,
            'process_status' => null,
            'process_status_changed_at' => null,
            'quoted_total' => null,
            'quote_revision' => null,
        ]);
    }

    public function test_sales_payment_and_process_accept_null_and_their_own_values(): void
    {
        $id = $this->insertInquiry();

        DB::table('inquiries')->where('id', $id)->update(['sales_status' => 'negotiation', 'payment_status' => 'awaiting', 'process_status' => 'received']);
        DB::table('inquiries')->where('id', $id)->update(['sales_status' => null, 'payment_status' => null, 'process_status' => null]);

        // Only one of payment and process set: their starting together is the app's rule.
        DB::table('inquiries')->where('id', $id)->update(['payment_status' => 'awaiting']);

        $this->assertDatabaseHas('inquiries', ['id' => $id, 'sales_status' => null, 'payment_status' => 'awaiting', 'process_status' => null]);
    }

    public function test_each_role_status_rejects_a_value_that_is_not_its_own(): void
    {
        $id = $this->insertInquiry();
        $update = fn (array $values) => fn () => DB::table('inquiries')->where('id', $id)->update($values);

        $this->assertRejectedBy('chk_inquiries_marketing_status', $update(['marketing_status' => 'negotiation']));
        $this->assertRejectedBy('chk_inquiries_sales_status', $update(['sales_status' => 'won']));
        $this->assertRejectedBy('chk_inquiries_sales_status', $update(['sales_status' => 'cancelled']));
        $this->assertRejectedBy('chk_inquiries_payment_status', $update(['payment_status' => 'refunded']));
        $this->assertRejectedBy('chk_inquiries_process_status', $update(['process_status' => 'fabrication']));

        $this->assertRejectedBy('not-null', $update(['marketing_status' => null]));
        $this->assertRejectedBy('not-null', $update(['marketing_status_changed_at' => null]));
    }

    public function test_the_quote_columns_reject_out_of_range_values(): void
    {
        $id = $this->insertInquiry();

        $this->assertRejectedBy('chk_inquiries_quoted_total', fn () => DB::table('inquiries')->where('id', $id)->update(['quoted_total' => 0]));
        $this->assertRejectedBy('chk_inquiries_quoted_total', fn () => DB::table('inquiries')->where('id', $id)->update(['quoted_total' => -5]));
        $this->assertRejectedBy('chk_inquiries_quote_revision', fn () => DB::table('inquiries')->where('id', $id)->update(['quote_revision' => 0]));
    }

    public function test_no_check_ties_one_status_to_another(): void
    {
        $id = $this->insertInquiry();

        // Sales quoted with no quotation and marketing still pending, a payment
        // with no process, logistics past received with no job order: the app's
        // rules, not the database's.
        DB::table('inquiries')->where('id', $id)->update([
            'marketing_status' => 'pending',
            'sales_status' => 'quoted',
            'payment_status' => 'fully_paid',
        ]);
        DB::table('inquiries')->where('id', $id)->update(['payment_status' => null, 'process_status' => 'dispatched']);

        $this->assertDatabaseHas('inquiries', ['id' => $id, 'sales_status' => 'quoted', 'payment_status' => null, 'process_status' => 'dispatched', 'quote_revision' => null]);
    }

    public function test_the_status_history_holds_a_track_and_any_role_value_on_it(): void
    {
        $id = $this->insertInquiry();

        $this->assertRejectedBy('chk_inquiry_status_history_track', fn () => $this->insertHistory($id, 'accounting', null, 'awaiting'));
        $this->assertRejectedBy('chk_inquiry_status_history_to_status', fn () => $this->insertHistory($id, 'sales', null, 'draft'));
        $this->assertRejectedBy('chk_inquiry_status_history_to_status', fn () => $this->insertHistory($id, 'sales', null, 'cancelled'));
        $this->assertRejectedBy('chk_inquiry_status_history_from_status', fn () => $this->insertHistory($id, 'sales', 'draft', 'negotiation'));
        $this->assertRejectedBy('not-null', fn () => $this->insertHistory($id, null, null, 'pending'));

        // The values are not tied to the track.
        $this->insertHistory($id, 'marketing', null, 'fully_paid');
        $this->assertDatabaseHas('inquiry_status_history', ['inquiry_id' => $id, 'track' => 'marketing', 'to_status' => 'fully_paid']);
    }

    public function test_inquiry_files_have_a_known_document_type_and_a_revision_from_one(): void
    {
        $id = $this->insertInquiry();

        $this->assertRejectedBy('chk_inquiry_files_document_type', fn () => $this->insertFile($id, 'invoice'));
        $this->assertRejectedBy('not-null', fn () => $this->insertFile($id, null));
        $this->assertRejectedBy('chk_inquiry_files_revision', fn () => $this->insertFile($id, 'quotation', 0));

        foreach (['client_attachment', 'quotation', 'receipt', 'job_order'] as $type) {
            $this->insertFile($id, $type, $type === 'quotation' ? 1 : null);
        }

        $this->assertDatabaseCount('inquiry_files', 4);
    }

    public function test_payment_transactions_have_a_known_kind_and_a_positive_amount(): void
    {
        $id = $this->insertInquiry();

        $this->assertRejectedBy('chk_payment_transactions_amount', fn () => $this->insertTransaction($id, 'payment', '0'));
        $this->assertRejectedBy('chk_payment_transactions_amount', fn () => $this->insertTransaction($id, 'refund', '-10.00'));
        $this->assertRejectedBy('chk_payment_transactions_kind', fn () => $this->insertTransaction($id, 'deposit', '10.00'));

        $this->insertTransaction($id, 'payment', '10.00');
        $this->insertTransaction($id, 'refund', '10.00');
        $this->assertDatabaseCount('payment_transactions', 2);
    }

    public function test_an_inquiry_has_one_deadline_visit_and_a_booking_has_none(): void
    {
        $id = $this->insertInquiry();
        $clientId = DB::table('inquiries')->where('id', $id)->value('client_id');

        $this->insertSchedule($clientId, $id);

        $this->assertRejectedBy('idx_schedules_inquiry_once', fn () => $this->insertSchedule($clientId, $id));

        $this->insertSchedule($clientId, null);
        $this->insertSchedule($clientId, null);
        $this->assertDatabaseCount('schedules', 3);
    }

    public function test_an_inquiry_stone_is_unique_per_stone_edge_and_finish(): void
    {
        $stone = Stone::factory()->create([
            'slug' => 'test-stone-2',
            'name' => 'Test Stone 2',
            'thumbnail_path' => '/path/to/thumb.jpg',
            'thickness_mm' => 50,
            'recommended' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $edgeProfile = EdgeProfile::create(['name' => 'Beveled', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1]);
        $finish = Finish::create(['name' => 'Polished', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 1]);
        $id = $this->insertInquiry();

        $row = fn (int $quantity) => [
            'inquiry_id' => $id,
            'stone_id' => $stone->id,
            'edge_profile_id' => $edgeProfile->id,
            'finish_id' => $finish->id,
            'quantity' => $quantity,
            'sort_order' => $quantity,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('inquiry_stones')->insert($row(5));

        $this->assertRejectedBy('idx_inquiry_stones_unique', fn () => DB::table('inquiry_stones')->insert($row(3)));
        $this->assertRejectedBy('chk_inquiry_stones_quantity', fn () => DB::table('inquiry_stones')->insert([...$row(0), 'finish_id' => Finish::create(['name' => 'Honed', 'is_advisory' => false, 'is_active' => true, 'sort_order' => 2])->id]));
    }

    public function test_message_type_check_constraint(): void
    {
        $this->expectException(\Exception::class);

        DB::insert('INSERT INTO messages (conversation_id, sender_id, type, body, inquiry_id, schedule_id, announced_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            1,
            1,
            'text',
            null,
            1,
            null,
            null,
            now(),
        ]);
    }

    public function test_an_inquiry_card_needs_its_display_and_marketing_status_and_takes_null_for_stages_not_started(): void
    {
        $card = $this->inquiryCard();

        foreach (['announced_status', 'announced_marketing_status', 'inquiry_id'] as $column) {
            $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, $column => null]));
        }

        DB::table('messages')->insert([
            ...$card,
            'announced_status' => 'pending',
            'announced_marketing_status' => 'pending',
            'announced_sales_status' => null,
            'announced_payment_status' => null,
            'announced_process_status' => null,
        ]);

        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'body' => 'Hello']));

        DB::table('messages')->insert($card);
        $this->assertDatabaseHas('messages', ['type' => 'inquiry_card', 'announced_status' => 'production']);
        $this->assertDatabaseHas('messages', ['type' => 'inquiry_card', 'announced_status' => 'pending', 'announced_sales_status' => null]);
    }

    public function test_an_inquiry_card_announces_a_display_status_and_role_values(): void
    {
        $card = $this->inquiryCard();

        // `won` is a marketing value, `completed` a logistics one: neither is a display status.
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_status' => 'won']));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_status' => 'completed']));
        $this->assertRejectedBy('chk_messages_announced_marketing_status', fn () => DB::table('messages')->insert([...$card, 'announced_marketing_status' => 'quoted']));
        $this->assertRejectedBy('chk_messages_announced_sales_status', fn () => DB::table('messages')->insert([...$card, 'announced_sales_status' => 'won']));
        $this->assertRejectedBy('chk_messages_announced_payment_status', fn () => DB::table('messages')->insert([...$card, 'announced_payment_status' => 'refunded']));
        $this->assertRejectedBy('chk_messages_announced_process_status', fn () => DB::table('messages')->insert([...$card, 'announced_process_status' => 'fabrication']));

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_an_inquiry_card_snapshot_is_posted_once(): void
    {
        $card = $this->inquiryCard();
        DB::table('messages')->insert($card);

        $this->assertRejectedBy('idx_messages_inquiry_card_once', fn () => DB::table('messages')->insert($card));

        // Any column of the snapshot moving is a new card.
        DB::table('messages')->insert([...$card, 'announced_payment_status' => 'fully_paid']);
        DB::table('messages')->insert([...$card, 'announced_process_status' => 'quality_check', 'announced_status' => 'quality_check']);
        $this->assertDatabaseCount('messages', 3);
    }

    public function test_a_snapshot_with_stages_not_started_is_posted_once_too(): void
    {
        $card = [
            ...$this->inquiryCard(),
            'announced_status' => 'negotiation',
            'announced_sales_status' => 'negotiation',
            'announced_payment_status' => null,
            'announced_process_status' => null,
        ];
        DB::table('messages')->insert($card);

        // A NULL matches a NULL here: the index reads them through COALESCE.
        $this->assertRejectedBy('idx_messages_inquiry_card_once', fn () => DB::table('messages')->insert($card));

        DB::table('messages')->insert([...$card, 'announced_status' => 'pending', 'announced_marketing_status' => 'pending', 'announced_sales_status' => null]);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_a_schedule_card_keeps_its_own_statuses_and_no_snapshot(): void
    {
        $conversationId = $this->insertConversation();
        $inquiryId = $this->insertInquiry();
        $scheduleId = $this->insertSchedule(DB::table('inquiries')->where('id', $inquiryId)->value('client_id'), null);
        $card = ['conversation_id' => $conversationId, 'type' => 'schedule_card', 'schedule_id' => $scheduleId, 'announced_status' => 'confirmed', 'created_at' => now()];

        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_payment_status' => 'awaiting']));

        DB::table('messages')->insert($card);
        $this->assertDatabaseHas('messages', ['type' => 'schedule_card', 'announced_status' => 'confirmed']);
    }

    public function test_a_message_that_is_not_an_inquiry_card_cannot_carry_the_snapshot_columns(): void
    {
        $conversationId = $this->insertConversation();

        foreach (['announced_marketing_status' => 'won', 'announced_sales_status' => 'quoted', 'announced_payment_status' => 'partial', 'announced_process_status' => 'ready'] as $column => $value) {
            $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
                'conversation_id' => $conversationId,
                'type' => 'text',
                'body' => 'Hello',
                $column => $value,
                'created_at' => now(),
            ]));
            $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
                'conversation_id' => $conversationId,
                'type' => 'system',
                'system_event' => 'assigned',
                $column => $value,
                'created_at' => now(),
            ]));
        }

        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'type' => 'project_card',
            'inquiry_id' => $this->insertInquiry(),
            'announced_status' => 'pending',
            'created_at' => now(),
        ]));

        $this->assertDatabaseCount('messages', 0);
    }

    /**
     * Runs a write that a constraint must refuse, in a savepoint so the test's
     * transaction survives it, and checks it was that constraint which refused.
     * `not-null` stands for a NOT NULL column, which PostgreSQL reports by SQLSTATE.
     */
    private function assertRejectedBy(string $constraint, Closure $write): void
    {
        try {
            DB::transaction(fn () => $write());
        } catch (QueryException $exception) {
            if ($constraint === 'not-null') {
                $this->assertSame('23502', $exception->getCode());
            } else {
                $this->assertStringContainsString($constraint, $exception->getMessage());
            }

            return;
        }

        $this->fail("Expected {$constraint} to refuse the write.");
    }

    /** A new inquiry: marketing is left to its default, the later stages to NULL. */
    private function insertInquiry(): int
    {
        return DB::table('inquiries')->insertGetId([
            'reference' => 'INQ-S'.random_int(100000, 999999),
            'client_id' => User::factory()->create()->id,
            'name' => 'Kitchen',
            'location' => 'Makati',
            'marketing_status_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertHistory(int $inquiryId, ?string $track, ?string $from, string $to): void
    {
        DB::table('inquiry_status_history')->insert([
            'inquiry_id' => $inquiryId,
            'track' => $track,
            'from_status' => $from,
            'to_status' => $to,
            'changed_at' => now(),
        ]);
    }

    private function insertFile(int $inquiryId, ?string $documentType, ?int $revision = null): void
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

    private function insertTransaction(int $inquiryId, string $kind, string $amount): void
    {
        DB::table('payment_transactions')->insert([
            'inquiry_id' => $inquiryId,
            'kind' => $kind,
            'amount' => $amount,
            'received_at' => now(),
            'created_at' => now(),
        ]);
    }

    private function insertSchedule(int $clientId, ?int $inquiryId): int
    {
        return DB::table('schedules')->insertGetId([
            'client_id' => $clientId,
            'booked_by_type' => 'client',
            'inquiry_id' => $inquiryId,
            'title' => 'Visit',
            'arrival_at' => now(),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function inquiryCard(): array
    {
        return [
            'conversation_id' => $this->insertConversation(),
            'type' => 'inquiry_card',
            'inquiry_id' => $this->insertInquiry(),
            'announced_status' => 'production',
            'announced_marketing_status' => 'won',
            'announced_sales_status' => 'quoted',
            'announced_payment_status' => 'partial',
            'announced_process_status' => 'production',
            'created_at' => now(),
        ];
    }

    private function insertConversation(): int
    {
        return DB::table('conversations')->insertGetId([
            'type' => 'group',
            'name' => 'Schema test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
