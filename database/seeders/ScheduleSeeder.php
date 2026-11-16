<?php

namespace Database\Seeders;

use App\Enums\BookedByType;
use App\Enums\ClientKind;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\Client;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Booking-style visits (`inquiry_id` NULL) for the sample client, in every
 * step of the sales confirmation flow (data/schedule.php): waiting for
 * marketing to assign them, declined back to marketing, awaiting a sales
 * member, confirmed, served and cancelled; some booked by the client, some by
 * marketing, two for a walk-in with no account. They are spread over two
 * sales members, so each reads only theirs.
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

        $client = User::where('email', 'rafael.doming@thenorthgatecafe.example')->first();
        $marketing = User::where('email', 'marketing@example.com')->first();

        if ($client === null || $marketing === null) {
            return;
        }

        $walkIn = Client::query()->where('kind', ClientKind::Direct->value)->orderBy('id')->first();

        // sales@example.com first, so index 0 is the demo account.
        $sales = User::role(Role::Sales->value)->orderByRaw("email = 'sales@example.com' DESC")->orderBy('id')->get()->values();

        foreach ($data['items'] as $itemData) {
            $isWalkIn = ($itemData['walkIn'] ?? false) === true;

            if ($isWalkIn && $walkIn === null) {
                continue;
            }

            $dayOffset = $itemData['week'] === 'next' ? 7 + $itemData['day'] : min(max($todayIndex + $itemData['day'], 0), 6);
            $arrivalAt = $monday->copy()->addDays($dayOffset)->setHour($itemData['hour'])->setMinute(0);
            $status = ScheduleStatus::from($itemData['status']);
            $byMarketing = $itemData['by'] === 'marketing';
            $assignee = $itemData['sales'] === null || $sales->isEmpty() ? null : $sales[$itemData['sales'] % $sales->count()];
            $decliner = isset($itemData['declined']) && $sales->isNotEmpty() ? $sales[$sales->count() - 1] : null;
            $isCancelled = $status === ScheduleStatus::Cancelled;
            // Confirmed by its sales member the day before, so served visits were confirmed too.
            $isConfirmed = $assignee !== null && ($status === ScheduleStatus::Confirmed || $status === ScheduleStatus::Served);
            $isAssigned = $assignee !== null && ($isConfirmed || $status === ScheduleStatus::AwaitingSales);

            Schedule::query()->firstOrCreate(
                ['title' => $itemData['title'], 'arrival_at' => $arrivalAt, 'inquiry_id' => null],
                [
                    'client_id' => $isWalkIn ? null : $client->id,
                    'client_record_id' => $isWalkIn ? $walkIn->id : null,
                    'booked_by' => $byMarketing ? $marketing->id : $client->id,
                    'booked_by_type' => $byMarketing ? BookedByType::Staff : BookedByType::Client,
                    'status' => $status,
                    'notes' => $itemData['notes'],
                    'approved_by' => $isConfirmed ? $assignee->id : null,
                    'approved_at' => $isConfirmed ? $arrivalAt->copy()->subDay() : null,
                    'assigned_to' => $isAssigned ? $assignee->id : null,
                    'assigned_by' => $isAssigned || $decliner !== null ? $marketing->id : null,
                    'assigned_at' => $isAssigned ? $arrivalAt->copy()->subDays(2) : null,
                    'declined_by' => $decliner?->id,
                    'declined_at' => $decliner === null ? null : $arrivalAt->copy()->subDays(2),
                    'decline_reason' => $decliner === null ? null : $itemData['declined'],
                    'cancelled_at' => $isCancelled ? $arrivalAt->copy()->subDay() : null,
                    'cancel_reason' => $isCancelled ? $itemData['notes'] : null,
                    'created_at' => $arrivalAt->copy()->subDays(3),
                    'updated_at' => $arrivalAt->copy()->subDays(2),
                ]
            );
        }
    }
}
