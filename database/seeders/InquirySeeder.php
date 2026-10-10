<?php

namespace Database\Seeders;

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryStatusHistory;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InquirySeeder extends Seeder
{
    public function run(): void
    {
        if (! config('seed.demo_accounts')) {
            return;
        }

        $data = require database_path('seeders/data/inquiries.php');

        $roleMapping = [
            'maria' => 'maria.alvarez@example.com',
            'daniel' => 'daniel.okafor@example.com',
            'sofia' => 'sofia.bianchi@example.com',
            'priya' => 'priya.raman@example.com',
        ];

        $clientEmails = [
            'cl-rafael' => 'rafael.doming@thenorthgatecafe.example',
            'cl-nadine' => 'nadine.delacroix@lumenandco.example',
            'cl-ivan' => 'ivan.mercado@hillcrestshowroom.example',
            'cl-haruki' => 'haruki.nakamura@groveresidences.example',
            'cl-grace' => 'grace.lim@maplelane.example',
        ];

        foreach ($data['inquiries'] as $inquiryData) {
            $clientEmail = $clientEmails[$inquiryData['client_id']] ?? null;
            $clientUser = $clientEmail ? User::where('email', $clientEmail)->firstOrFail() : null;

            $lastStatusChange = end($inquiryData['status_history']);

            $inquiry = Inquiry::query()->firstOrCreate(
                ['reference' => $inquiryData['reference']],
                [
                    'client_id' => $clientUser->id,
                    'status' => $inquiryData['status'],
                    'status_changed_at' => Carbon::parse($lastStatusChange['at']),
                    'created_at' => Carbon::parse($inquiryData['created_at']),
                    'updated_at' => Carbon::parse($inquiryData['updated_at']),
                ]
            );

            foreach ($inquiryData['handlers'] as $handlerName) {
                $handlerEmail = $roleMapping[$handlerName] ?? null;
                if ($handlerEmail) {
                    $handler = User::where('email', $handlerEmail)->firstOrFail();
                    $inquiry->handlers()->create([
                        'user_id' => $handler->id,
                        'assigned_by' => $clientUser->id,
                        'assigned_at' => Carbon::parse($inquiryData['created_at']),
                    ]);
                }
            }

            foreach ($inquiryData['projects'] as $projectSort => $projectData) {
                $project = $inquiry->projects()->create([
                    'name' => $projectData['name'],
                    'location' => $projectData['location'],
                    'landmark' => $projectData['landmark'],
                    'area_sqm' => $projectData['area_sqm'],
                    'deadline_date' => $projectData['deadline_date'],
                    'deadline_time' => $projectData['deadline_time'],
                    'notes' => $projectData['notes'],
                    'sort_order' => $projectSort,
                ]);

                foreach ($projectData['stones'] as $stoneSort => $stoneSelection) {
                    $stone = Stone::where('slug', $stoneSelection['stone_slug'])->firstOrFail();
                    $edgeProfile = EdgeProfile::where('name', $stoneSelection['edge'])->firstOrFail();
                    $finish = Finish::where('name', $stoneSelection['finish'])->firstOrFail();

                    $project->stones()->create([
                        'stone_id' => $stone->id,
                        'edge_profile_id' => $edgeProfile->id,
                        'finish_id' => $finish->id,
                        'quantity' => $stoneSelection['quantity'],
                        'sort_order' => $stoneSort,
                    ]);
                }

                foreach ($projectData['services'] as $serviceSort => $serviceName) {
                    $service = Service::where('name', $serviceName)->firstOrFail();
                    $project->services()->attach($service->id);
                }

                foreach ($projectData['categories'] ?? [] as $categorySort => $categorySlug) {
                    $category = ProjectCategory::where('slug', $categorySlug)->firstOrFail();
                    $project->categories()->attach($category->id, ['sort_order' => $categorySort]);
                }

                foreach ($projectData['attachments'] ?? [] as $fileData) {
                    $project->files()->create([
                        'disk' => 'local',
                        'path' => 'inquiries/'.$inquiry->id.'/'.$fileData['name'],
                        'original_name' => $fileData['name'],
                        'mime_type' => $fileData['mime_type'],
                        'size_bytes' => $fileData['size_bytes'],
                        'sha256' => hash('sha256', $fileData['name']),
                        'uploaded_by' => $clientUser->id,
                    ]);
                }
            }

            foreach ($inquiryData['status_history'] as $historySort => $historyEntry) {
                $changedByEmail = $roleMapping[$historyEntry['changed_by']] ?? $clientEmail;
                $changedByUser = $changedByEmail ? User::where('email', $changedByEmail)->first() : $clientUser;

                InquiryStatusHistory::create([
                    'inquiry_id' => $inquiry->id,
                    'from_status' => $historyEntry['from_status'],
                    'to_status' => $historyEntry['to_status'],
                    'changed_by' => $changedByUser->id,
                    'changed_at' => Carbon::parse($historyEntry['at']),
                ]);
            }
        }

        $maxReference = Inquiry::max('id') ?? 1000;
        $this->setSequence('inquiries_id_seq', $maxReference + 1);
    }

    private function setSequence(string $sequenceName, int $nextValue): void
    {
        \DB::statement("SELECT setval('{$sequenceName}', {$nextValue})");
    }
}
