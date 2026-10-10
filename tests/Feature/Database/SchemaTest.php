<?php

namespace Tests\Feature\Database;

use App\Enums\InquiryStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Stone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_unique_indexes_allow_reuse_after_soft_delete(): void
    {
        $stone = Stone::create([
            'slug' => 'test-stone',
            'name' => 'Test Stone',
            'thumbnail_path' => '/path/to/thumb.jpg',
            'description' => 'A test stone',
            'origin' => 'Test',
            'thickness_mm' => 50,
            'recommended' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $stone->delete();

        $newStone = Stone::create([
            'slug' => 'test-stone',
            'name' => 'Test Stone',
            'thumbnail_path' => '/path/to/thumb2.jpg',
            'description' => 'Another test stone',
            'origin' => 'Test',
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
        $stone = Stone::create([
            'slug' => 'test-stone-2',
            'name' => 'Test Stone 2',
            'thumbnail_path' => '/path/to/thumb.jpg',
            'description' => 'A test stone',
            'origin' => 'Test',
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

        DB::insert('INSERT INTO messages (conversation_id, sender_id, type, body, inquiry_id, agenda_id, announced_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
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
}
