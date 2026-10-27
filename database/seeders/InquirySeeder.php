<?php

namespace Database\Seeders;

use App\Enums\InquiryStatus;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\ProjectCategory;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Message\SupportConversationService;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The demo clients' inquiries and everything that happened to them: projects,
 * quotations, payments with their transactions, job orders, status history,
 * and the project cards in each client's Regray Team thread.
 *
 * Written in that order, from database/seeders/data/inquiries.php. Each
 * project is booked as its schedule through ScheduleService, the same path
 * sending an inquiry takes, and a lost inquiry's visits are cancelled with it.
 *
 * Demo data only: it throws in production, like DemoSeeder.
 */
class InquirySeeder extends Seeder
{
    /** @var array<string, string> */
    private const STAFF_EMAILS = [
        'maria' => 'maria.alvarez@example.com',
        'daniel' => 'daniel.okafor@example.com',
        'sofia' => 'sofia.bianchi@example.com',
        'priya' => 'priya.raman@example.com',
    ];

    /** @var array<string, string> */
    private const CLIENT_EMAILS = [
        'cl-elena' => 'elena.villanueva@brightleafdesign.example',
        'cl-rafael' => 'rafael.doming@thenorthgatecafe.example',
        'cl-nadine' => 'nadine.delacroix@lumenandco.example',
        'cl-ivan' => 'ivan.mercado@hillcrestshowroom.example',
        'cl-haruki' => 'haruki.nakamura@groveresidences.example',
        'cl-grace' => 'grace.lim@maplelane.example',
    ];

    public function run(
        ScheduleService $scheduleService,
        SupportConversationService $support,
        MessageRepositoryInterface $messages,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'InquirySeeder cannot run in production. It writes demo inquiries for the demo clients.'
            );
        }

        $data = require database_path('seeders/data/inquiries.php');

        foreach ($data['inquiries'] as $inquiryData) {
            $client = User::where('email', self::CLIENT_EMAILS[$inquiryData['client_id']])->firstOrFail();
            $lastStatusChange = end($inquiryData['status_history']);

            $inquiry = Inquiry::query()->forceCreate([
                'reference' => $inquiryData['reference'],
                'client_id' => $client->id,
                'status' => $inquiryData['status'],
                'status_changed_at' => Carbon::parse($lastStatusChange['at']),
                'created_at' => Carbon::parse($inquiryData['created_at']),
                'updated_at' => Carbon::parse($inquiryData['updated_at']),
            ]);

            foreach ($inquiryData['handlers'] as $handlerName) {
                $inquiry->handlers()->create([
                    'user_id' => $this->staff($handlerName)->id,
                    'assigned_by' => $client->id,
                    'assigned_at' => Carbon::parse($inquiryData['created_at']),
                ]);
            }

            $projects = [];

            foreach ($inquiryData['projects'] as $projectSort => $projectData) {
                $projects[] = $this->seedProject($scheduleService, $inquiry, $client, $projectSort, $projectData);
            }

            $liveQuotations = [];

            foreach ($inquiryData['projects'] as $index => $projectData) {
                $liveQuotations[$index] = $this->seedQuotations($projects[$index], $projectData['quotations']);
            }

            foreach ($inquiryData['projects'] as $index => $projectData) {
                foreach ($projectData['job_orders'] as $jobOrderData) {
                    $this->seedJobOrder($projects[$index], $liveQuotations[$index], $jobOrderData);
                }

                if ($projectData['payment'] !== null) {
                    $this->seedPayment($projects[$index], $liveQuotations[$index], $projectData['payment']);
                }
            }

            foreach ($inquiryData['status_history'] as $historyEntry) {
                $changedBy = str_starts_with($historyEntry['changed_by'], 'cl-')
                    ? $client
                    : $this->staff($historyEntry['changed_by']);

                InquiryStatusHistory::create([
                    'inquiry_id' => $inquiry->id,
                    'from_status' => $historyEntry['from_status'],
                    'to_status' => $historyEntry['to_status'],
                    'changed_by' => $changedBy->id,
                    'changed_at' => Carbon::parse($historyEntry['at']),
                ]);
            }

            if ($inquiry->status === InquiryStatus::Lost) {
                $scheduleService->cancelForInquiry($inquiry, $inquiry->status_changed_at);
            }

            $cards = [];

            foreach ($inquiryData['projects'] as $index => $projectData) {
                foreach ($projectData['cards'] as $card) {
                    $cards[] = [
                        'project' => $card['project'],
                        'quotation' => $card['quotation'],
                        'payment' => $card['payment'],
                        'at' => $card['at'],
                        'project_id' => $projects[$index]->id,
                    ];
                }
            }

            $this->postProjectCards($support, $messages, $inquiry, $cards);
        }

        $maxReference = Inquiry::max('id') ?? 1000;
        $this->setSequence('inquiries_id_seq', $maxReference + 1);

        $lastJobOrderNumber = JobOrder::query()->pluck('reference')
            ->map(fn (string $reference): int => (int) preg_replace('/\D/', '', $reference))
            ->max();

        if ($lastJobOrderNumber !== null) {
            $this->setSequence('job_order_reference_seq', $lastJobOrderNumber);
        }
    }

    /**
     * The project with its schedule, stones, services, categories and files.
     *
     * @param  array<string, mixed>  $projectData
     */
    private function seedProject(ScheduleService $scheduleService, Inquiry $inquiry, User $client, int $projectSort, array $projectData): InquiryProject
    {
        $schedule = $scheduleService->createForInquiryProject(
            $inquiry,
            $client,
            $projectData['name'],
            $projectData['location'],
            $projectData['deadline_date'],
            $projectData['deadline_time'],
        );

        $project = InquiryProject::query()->forceCreate([
            'inquiry_id' => $inquiry->id,
            'name' => $projectData['name'],
            'location' => $projectData['location'],
            'landmark' => $projectData['landmark'],
            'area_sqm' => $projectData['area_sqm'],
            'schedule_id' => $schedule->id,
            'notes' => $projectData['notes'],
            'sort_order' => $projectSort,
            'status' => $projectData['status'],
            'status_changed_at' => Carbon::parse($projectData['status_changed_at']),
            'created_at' => $inquiry->created_at,
            'updated_at' => Carbon::parse($projectData['status_changed_at']),
        ]);

        foreach ($projectData['stones'] as $stoneSort => $stoneSelection) {
            $stone = Stone::query()
                ->whereHas('category', fn ($query) => $query->where('slug', $stoneSelection['stone_slug']))
                ->orderBy('sort_order')->orderBy('id')->firstOrFail();
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

        foreach ($projectData['services'] as $serviceName) {
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
                'uploaded_by' => $client->id,
            ]);
        }

        return $project;
    }

    /**
     * Every revision, oldest first. Returns the live one: the last revision,
     * which job orders and the payment are written against.
     *
     * @param  list<array<string, mixed>>  $quotations
     */
    private function seedQuotations(InquiryProject $project, array $quotations): Quotation
    {
        $quotation = null;

        foreach ($quotations as $quotationData) {
            $quotation = Quotation::query()->forceCreate([
                'inquiry_project_id' => $project->id,
                'revision_no' => $quotationData['revision_no'],
                'status' => $quotationData['status'],
                'total_amount' => $quotationData['total_amount'],
                'issued_at' => Carbon::parse($quotationData['issued_at']),
                'responded_at' => $quotationData['responded_at'] === null ? null : Carbon::parse($quotationData['responded_at']),
                'created_by' => $this->staff($quotationData['created_by'])->id,
                'created_at' => Carbon::parse($quotationData['issued_at']),
                'updated_at' => Carbon::parse($quotationData['updated_at'] ?? $quotationData['responded_at'] ?? $quotationData['issued_at']),
            ]);
        }

        return $quotation ?? throw new RuntimeException("Project {$project->name} has no quotation.");
    }

    /**
     * @param  array<string, mixed>  $jobOrderData
     */
    private function seedJobOrder(InquiryProject $project, Quotation $quotation, array $jobOrderData): void
    {
        JobOrder::query()->forceCreate([
            'reference' => $jobOrderData['reference'],
            'inquiry_project_id' => $project->id,
            'quotation_id' => $quotation->id,
            'status' => $jobOrderData['status'],
            'status_changed_at' => Carbon::parse($jobOrderData['status_changed_at']),
            'notes' => $jobOrderData['notes'],
            'created_at' => Carbon::parse($jobOrderData['created_at']),
            'updated_at' => Carbon::parse($jobOrderData['status_changed_at']),
        ]);
    }

    /**
     * The payment on the quotation's total, and its transactions. What is paid
     * is the sum of the `payment` transactions, added up in centavos.
     *
     * @param  array<string, mixed>  $paymentData
     */
    private function seedPayment(InquiryProject $project, Quotation $quotation, array $paymentData): void
    {
        $paidCentavos = 0;

        foreach ($paymentData['transactions'] as $transaction) {
            if ($transaction['kind'] === 'payment') {
                $paidCentavos += (int) round((float) $transaction['amount'] * 100);
            }
        }

        $payment = Payment::query()->forceCreate([
            'inquiry_project_id' => $project->id,
            'quotation_id' => $quotation->id,
            'amount_due' => $quotation->total_amount,
            'amount_paid' => number_format($paidCentavos / 100, 2, '.', ''),
            'status' => $paymentData['status'],
            'status_changed_at' => Carbon::parse($paymentData['status_changed_at']),
            'created_at' => Carbon::parse($paymentData['created_at']),
            'updated_at' => Carbon::parse($paymentData['status_changed_at']),
        ]);

        foreach ($paymentData['transactions'] as $transaction) {
            PaymentTransaction::query()->forceCreate([
                'payment_id' => $payment->id,
                'kind' => $transaction['kind'],
                'amount' => $transaction['amount'],
                'reference' => $transaction['reference'],
                'recorded_by' => $this->staff($transaction['recorded_by'])->id,
                'received_at' => Carbon::parse($transaction['received_at']),
                'created_at' => Carbon::parse($transaction['received_at']),
            ]);
        }
    }

    /**
     * Each project card into the thread the inquiry's cards go to, oldest
     * first across all of the inquiry's projects, so the thread reads in the
     * order things happened.
     *
     * @param  list<array{project: string, quotation: string, payment: string, at: string, project_id: int}>  $cards
     */
    private function postProjectCards(SupportConversationService $support, MessageRepositoryInterface $messages, Inquiry $inquiry, array $cards): void
    {
        $conversation = $support->cardThreadFor($inquiry);

        usort($cards, fn (array $first, array $second): int => strcmp($first['at'], $second['at']));

        foreach ($cards as $card) {
            $messages->createProjectCardOnce(
                $conversation->id,
                $card['project_id'],
                $card['project'],
                $card['quotation'],
                $card['payment'],
                Carbon::parse($card['at']),
            );
        }
    }

    private function staff(string $name): User
    {
        return User::where('email', self::STAFF_EMAILS[$name])->firstOrFail();
    }

    private function setSequence(string $sequenceName, int $nextValue): void
    {
        DB::statement("SELECT setval('{$sequenceName}', {$nextValue})");
    }
}
