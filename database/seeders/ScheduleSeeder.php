<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Booking-style visits (`inquiry_id` NULL) for the sample client.
 *
 * Inquiry visits are not seeded here: InquirySeeder books one per inquiry
 * project through ScheduleService. These rows are looked up with
 * `inquiry_id` NULL, so they never match or duplicate one of those.
 */
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/schedule.php');

        $today = Carbon::now()->startOfDay();
        $monday = $today->copy()->startOfWeek();
        $todayIndex = (int) $today->copy()->diffInDays($monday);

        $clientEmail = 'rafael.doming@thenorthgatecafe.example';
        $client = User::where('email', $clientEmail)->first();
        $approver = User::role(Role::Sales->value)->first() ?? $client;

        foreach ($data['items'] as $itemData) {
            $dayOffset = $itemData['week'] === 'next' ? 7 + $itemData['day'] : min(max($todayIndex + $itemData['day'], 0), 6);
            $arrivalAt = $monday->copy()->addDays($dayOffset)->setHour($itemData['hour'])->setMinute(0);

            // Approved by staff, before the visit: a client never approves their own.
            $approvedAt = null;
            $approvedBy = null;
            if ($itemData['status'] === 'confirmed' || $itemData['status'] === 'served') {
                $approvedAt = $arrivalAt->copy()->subDay();
                $approvedBy = $approver->id;
            }
            $isCancelled = $itemData['status'] === 'cancelled';

            Schedule::query()->firstOrCreate(
                ['title' => $itemData['title'], 'arrival_at' => $arrivalAt, 'inquiry_id' => null],
                [
                    'client_id' => $client->id,
                    'booked_by' => $client->id,
                    'booked_by_type' => 'client',
                    'status' => $itemData['status'],
                    'notes' => $itemData['notes'],
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'cancelled_at' => $isCancelled ? $arrivalAt->copy()->subDay() : null,
                    'cancel_reason' => $isCancelled ? $itemData['notes'] : null,
                    'created_at' => $arrivalAt,
                    'updated_at' => $arrivalAt,
                ]
            );
        }
    }
}
