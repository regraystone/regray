<?php

namespace Tests\Feature\Database;

use App\Enums\InquiryProjectStatus;
use App\Enums\InquiryStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryProject;
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

    public function test_check_constraints_reject_invalid_status(): void
    {
        $this->expectException(\Exception::class);

        DB::insert('INSERT INTO inquiries (reference, client_id, status, status_changed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [
            'RGS-000001',
            1,
            'invalid_status',
            now(),
            now(),
            now(),
        ]);
    }

    public function test_unique_constraint_on_inquiry_project_stones(): void
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

        $edgeProfile = EdgeProfile::create([
            'name' => 'Beveled',
            'is_advisory' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $finish = Finish::create([
            'name' => 'Polished',
            'is_advisory' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $client = User::factory()->create();

        $inquiry = Inquiry::create([
            'reference' => 'RGS-000001',
            'client_id' => $client->id,
            'status' => InquiryStatus::Pending,
            'status_changed_at' => now(),
        ]);

        $project = $inquiry->projects()->create([
            'name' => 'Kitchen',
            'sort_order' => 1,
            'location' => 'Cebu',
            'status' => InquiryProjectStatus::Pending,
            'status_changed_at' => now(),
        ]);

        DB::insert('INSERT INTO inquiry_project_stones (inquiry_project_id, stone_id, edge_profile_id, finish_id, quantity, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $project->id,
            $stone->id,
            $edgeProfile->id,
            $finish->id,
            5,
            1,
            now(),
            now(),
        ]);

        $this->expectException(\Exception::class);

        DB::insert('INSERT INTO inquiry_project_stones (inquiry_project_id, stone_id, edge_profile_id, finish_id, quantity, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
            $project->id,
            $stone->id,
            $edgeProfile->id,
            $finish->id,
            3,
            2,
            now(),
            now(),
        ]);
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

    public function test_inquiry_statuses_reject_the_retired_values(): void
    {
        $project = $this->makeProject();

        $this->assertRejectedBy('chk_inquiries_status', fn () => DB::table('inquiries')->where('id', $project->inquiry_id)->update(['status' => 'cancelled']));
        $this->assertRejectedBy('chk_inquiry_status_history_to_status', fn () => $this->insertHistory(null, 'quoted'));
        $this->assertRejectedBy('chk_inquiry_status_history_from_status', fn () => $this->insertHistory('paid', 'ongoing', $project->inquiry_id));

        $this->insertHistory('draft', 'lost', $project->inquiry_id);
        $this->assertDatabaseHas('inquiry_status_history', ['inquiry_id' => $project->inquiry_id, 'to_status' => 'lost']);
    }

    public function test_project_quotation_job_order_and_payment_statuses_reject_unknown_values(): void
    {
        $project = $this->makeProject();
        $quotationId = $this->insertQuotation($project->id, 1, 'closeable');

        $this->assertRejectedBy('chk_inquiry_projects_status', fn () => DB::table('inquiry_projects')->where('id', $project->id)->update(['status' => 'in_progress']));
        $this->assertRejectedBy('chk_quotations_status', fn () => $this->insertQuotation($project->id, 2, 'accepted'));
        $this->assertRejectedBy('chk_job_orders_status', fn () => $this->insertJobOrder($project->id, $quotationId, 'installing'));
        $this->assertRejectedBy('chk_payments_status', fn () => $this->insertPayment($project->id, $quotationId, 'paid', '0'));

        $this->insertJobOrder($project->id, $quotationId, 'for_install');
        $this->assertDatabaseHas('job_orders', ['quotation_id' => $quotationId, 'status' => 'for_install']);
    }

    public function test_a_payment_status_has_to_agree_with_the_amounts(): void
    {
        $project = $this->makeProject();
        $quotationId = $this->insertQuotation($project->id, 1, 'closeable');

        $this->assertRejectedBy('chk_payments_status_amounts', fn () => $this->insertPayment($project->id, $quotationId, 'awaiting', '100.00'));
        $this->assertRejectedBy('chk_payments_status_amounts', fn () => $this->insertPayment($project->id, $quotationId, 'partial', '0'));
        $this->assertRejectedBy('chk_payments_status_amounts', fn () => $this->insertPayment($project->id, $quotationId, 'partial', '1000.00'));
        $this->assertRejectedBy('chk_payments_status_amounts', fn () => $this->insertPayment($project->id, $quotationId, 'fully_paid', '999.99'));
        $this->assertRejectedBy('chk_payments_amount_paid', fn () => $this->insertPayment($project->id, $quotationId, 'refunded', '1000.01'));

        $this->insertPayment($project->id, $quotationId, 'partial', '400.00');
        $this->assertDatabaseHas('payments', ['quotation_id' => $quotationId, 'status' => 'partial', 'amount_paid' => '400.00']);
    }

    public function test_a_project_has_one_live_quotation_at_a_time(): void
    {
        $project = $this->makeProject();
        $firstId = $this->insertQuotation($project->id, 1, 'pending');

        $this->assertRejectedBy('idx_quotations_one_live', fn () => $this->insertQuotation($project->id, 2, 'closeable'));
        $this->assertRejectedBy('quotations_inquiry_project_id_revision_no_unique', fn () => $this->insertQuotation($project->id, 1, 'rejected'));

        DB::table('quotations')->where('id', $firstId)->update(['status' => 'revision']);
        $this->insertQuotation($project->id, 2, 'pending');
        $this->assertDatabaseCount('quotations', 2);
    }

    public function test_a_quotation_has_one_payment_record(): void
    {
        $project = $this->makeProject();
        $quotationId = $this->insertQuotation($project->id, 1, 'closeable');
        $this->insertPayment($project->id, $quotationId, 'awaiting', '0');

        $this->assertRejectedBy('payments_quotation_id_unique', fn () => $this->insertPayment($project->id, $quotationId, 'awaiting', '0'));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_project_card_needs_the_project_quotation_and_payment_status(): void
    {
        $project = $this->makeProject();
        $conversationId = $this->insertConversation();
        $card = [
            'conversation_id' => $conversationId,
            'type' => 'project_card',
            'inquiry_project_id' => $project->id,
            'announced_status' => 'ongoing',
            'announced_quotation_status' => 'closeable',
            'announced_payment_status' => 'partial',
            'created_at' => now(),
        ];

        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_status' => null]));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_quotation_status' => null]));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'announced_payment_status' => null]));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([...$card, 'inquiry_project_id' => null]));
        $this->assertRejectedBy('chk_messages_announced_payment_status', fn () => DB::table('messages')->insert([...$card, 'announced_payment_status' => 'paid']));

        DB::table('messages')->insert($card);
        $this->assertDatabaseHas('messages', ['type' => 'project_card', 'inquiry_project_id' => $project->id]);
    }

    public function test_a_project_card_snapshot_is_posted_once(): void
    {
        $project = $this->makeProject();
        $conversationId = $this->insertConversation();
        $card = [
            'conversation_id' => $conversationId,
            'type' => 'project_card',
            'inquiry_project_id' => $project->id,
            'announced_status' => 'ongoing',
            'announced_quotation_status' => 'closeable',
            'announced_payment_status' => 'partial',
            'created_at' => now(),
        ];
        DB::table('messages')->insert($card);

        $this->assertRejectedBy('idx_messages_project_card_once', fn () => DB::table('messages')->insert($card));

        DB::table('messages')->insert([...$card, 'announced_payment_status' => 'fully_paid']);
        $this->assertDatabaseCount('messages', 2);
    }

    public function test_a_message_that_is_not_a_project_card_cannot_carry_the_project_columns(): void
    {
        $project = $this->makeProject();
        $conversationId = $this->insertConversation();

        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'type' => 'text',
            'body' => 'Hello',
            'inquiry_project_id' => $project->id,
            'created_at' => now(),
        ]));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'type' => 'inquiry_card',
            'inquiry_id' => $project->inquiry_id,
            'announced_status' => 'ongoing',
            'announced_payment_status' => 'partial',
            'created_at' => now(),
        ]));
        $this->assertRejectedBy('chk_messages_type', fn () => DB::table('messages')->insert([
            'conversation_id' => $conversationId,
            'type' => 'system',
            'system_event' => 'assigned',
            'announced_quotation_status' => 'pending',
            'created_at' => now(),
        ]));

        $this->assertDatabaseCount('messages', 0);
    }

    /**
     * Runs a write that a constraint must refuse, in a savepoint so the test's
     * transaction survives it, and checks it was that constraint which refused.
     */
    private function assertRejectedBy(string $constraint, Closure $write): void
    {
        try {
            DB::transaction(fn () => $write());
        } catch (QueryException $exception) {
            $this->assertStringContainsString($constraint, $exception->getMessage());

            return;
        }

        $this->fail("Expected {$constraint} to refuse the write.");
    }

    private function makeProject(): InquiryProject
    {
        $inquiry = Inquiry::create([
            'reference' => 'INQ-S'.random_int(1000, 9999),
            'client_id' => User::factory()->create()->id,
            'status' => InquiryStatus::Ongoing,
            'status_changed_at' => now(),
        ]);

        return $inquiry->projects()->create([
            'name' => 'Kitchen',
            'sort_order' => 0,
            'location' => 'Cebu',
            'status' => InquiryProjectStatus::Ongoing,
            'status_changed_at' => now(),
        ]);
    }

    private function insertHistory(?string $from, string $to, ?int $inquiryId = null): void
    {
        DB::table('inquiry_status_history')->insert([
            'inquiry_id' => $inquiryId ?? Inquiry::query()->value('id'),
            'from_status' => $from,
            'to_status' => $to,
            'changed_at' => now(),
        ]);
    }

    private function insertQuotation(int $projectId, int $revision, string $status): int
    {
        return DB::table('quotations')->insertGetId([
            'inquiry_project_id' => $projectId,
            'revision_no' => $revision,
            'status' => $status,
            'total_amount' => '1000.00',
            'issued_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertJobOrder(int $projectId, int $quotationId, string $status): void
    {
        DB::table('job_orders')->insert([
            'reference' => 'JO-'.DB::selectOne("SELECT nextval('job_order_reference_seq') AS number")->number,
            'inquiry_project_id' => $projectId,
            'quotation_id' => $quotationId,
            'status' => $status,
            'status_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPayment(int $projectId, int $quotationId, string $status, string $amountPaid): void
    {
        DB::table('payments')->insert([
            'inquiry_project_id' => $projectId,
            'quotation_id' => $quotationId,
            'amount_due' => '1000.00',
            'amount_paid' => $amountPaid,
            'status' => $status,
            'status_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
