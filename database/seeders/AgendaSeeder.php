<?php

namespace Database\Seeders;

use App\Models\Agenda;
use App\Models\Inquiry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('seed.demo_accounts')) {
            return;
        }

        $data = require database_path('seeders/data/agenda.php');

        $today = Carbon::now()->startOfDay();
        $monday = $today->copy()->startOfWeek();
        $todayIndex = (int) $today->copy()->diffInDays($monday);

        $clientEmail = 'rafael.doming@thenorthgatecafe.example';
        $client = User::where('email', $clientEmail)->first();

        $inquiryMap = [
            'apt-04' => 'INQ-1048',
            'apt-06' => 'INQ-1044',
            'apt-03' => 'INQ-1044',
        ];

        foreach ($data['items'] as $itemData) {
            $dayOffset = $itemData['week'] === 'next' ? 7 + $itemData['day'] : min(max($todayIndex + $itemData['day'], 0), 6);
            $arrivalAt = $monday->copy()->addDays($dayOffset)->setHour($itemData['hour'])->setMinute(0);

            $inquiryRef = $inquiryMap[$itemData['id']] ?? null;
            $inquiry = $inquiryRef ? Inquiry::where('reference', $inquiryRef)->first() : null;

            $approvedAt = null;
            $approvedBy = null;
            if ($itemData['status'] === 'confirmed' || $itemData['status'] === 'completed') {
                $approvedAt = $arrivalAt->copy()->addHours(1);
                $approvedBy = $client->id;
            }

            Agenda::query()->firstOrCreate(
                ['title' => $itemData['title'], 'arrival_at' => $arrivalAt],
                [
                    'client_id' => $client->id,
                    'booked_by' => $client->id,
                    'booked_by_type' => 'client',
                    'inquiry_id' => $inquiry->id ?? null,
                    'status' => $itemData['status'],
                    'notes' => $itemData['notes'],
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'created_at' => $arrivalAt,
                    'updated_at' => $arrivalAt,
                ]
            );
        }
    }
}
