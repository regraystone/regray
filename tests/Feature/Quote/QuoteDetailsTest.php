<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Stone;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\ScheduleService;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->slugs = Stone::query()->orderBy('id')->limit(2)->pluck('slug')->all();
});

/** An inquiry with its schedule, a client attachment and staff documents. */
function detailedInquiry(mixed $test): Inquiry
{
    $inquiry = makeInquiry(
        $test->client,
        ['name' => 'Kitchen', 'stones' => $test->slugs, 'services' => ['natural-stone-supply', 'custom-stone-fabrication']],
        ['reference' => 'INQ-D1'],
    );

    $inquiry->update(['landmark' => 'Near the church', 'area_sqm' => 12.5, 'notes' => 'Ground floor']);
    app(ScheduleService::class)->createForInquiry($inquiry, $test->client, '2030-01-02', '09:30');
    $inquiry->files()->create(['document_type' => DocumentType::ClientAttachment, 'disk' => 'local', 'path' => 'inquiries/INQ-D1/secret-path.pdf', 'original_name' => 'plan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $test->client->id]);
    addInquiryFile($inquiry, DocumentType::Quotation, revision: 1);
    addInquiryFile($inquiry, DocumentType::JobOrder);
    addInquiryFile($inquiry, DocumentType::Receipt);

    return $inquiry;
}

it('opens the signed-in client\'s own inquiry as one flat project with its statuses', function (): void {
    detailedInquiry($this);

    $response = $this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->assertOk();
    $inquiry = $response->viewData('page')['props']['inquiry'];

    expect($response->viewData('page')['component'])->toBe('inquiry-details')
        ->and($inquiry['reference'])->toBe('INQ-D1')
        ->and($inquiry['status'])->toBe('pending')
        ->and($inquiry)->not->toHaveKeys(['marketingStatus', 'lead'])
        // Stages not started are sent as null, never as a placeholder.
        ->and($inquiry['salesStatus'])->toBeNull()
        ->and($inquiry['paymentStatus'])->toBeNull()
        ->and($inquiry['processStatus'])->toBeNull()
        ->and($inquiry)->not->toHaveKeys(['projects', 'statusHistory'])
        ->and($inquiry['project']['name'])->toBe('Kitchen')
        ->and($inquiry['project']['services'])->toHaveCount(2);

    $project = $inquiry['project'];

    expect(array_column($project['stones'], 'id'))->toBe($this->slugs)
        ->and($project['stones'][0])->toMatchArray([
            'quantity' => 1,
            'edge' => EdgeProfile::query()->value('name'),
            'finish' => Finish::query()->value('name'),
        ])
        ->and($project['requirements'])->toMatchArray([
            'location' => 'Makati',
            'landmark' => 'Near the church',
            'areaSqm' => 12.5,
            'neededBy' => '2030-01-02',
            'neededByTime' => null,
            'notes' => 'Ground floor',
        ])
        // The client's own attachment only: the quotation is staff's.
        ->and($project['requirements']['attachments'])->toBe([
            ['id' => 'file-1', 'name' => 'plan.pdf', 'sizeBytes' => 2048, 'mimeType' => 'application/pdf'],
        ]);
});

it('sends the display status and the role statuses it came from', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-D3', 'status' => InquiryStatus::Production, 'payment_status' => PaymentStatus::Partial]);

    $details = $this->actingAs($this->client)->get(route('quote.show', $inquiry->reference))->viewData('page')['props']['inquiry'];

    expect($details)->toMatchArray([
        'status' => 'production',
        'salesStatus' => 'won',
        'paymentStatus' => 'partial',
        'processStatus' => 'production',
    ]);
});

it('sends the client\'s activity log: sales, payment and logistics changes, oldest first, without the lead or who made it', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-H1', 'lead' => makeLead($this->client), 'marketing_status' => LeadStatus::Won]);
    $staff = inquiryClient(Role::Sales);
    $at = now()->startOfSecond()->subDays(3);

    foreach ([
        [InquiryStatusTrack::Sales, null, 'negotiation', 1],
        [InquiryStatusTrack::Sales, 'negotiation', 'site_visited', 2],
    ] as [$track, $from, $to, $days]) {
        $inquiry->statusHistory()->create(['track' => $track, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $staff->id, 'changed_at' => $at->copy()->addDays($days)]);
    }

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-H1'))->viewData('page')['props']['inquiry'];

    expect($details['history'])->toBe([
        ['track' => 'sales', 'status' => 'negotiation', 'at' => $at->copy()->addDay()->toIso8601String()],
        ['track' => 'sales', 'status' => 'site_visited', 'at' => $at->copy()->addDays(2)->toIso8601String()],
    ])->and(json_encode($details['history']))->not->toContain('changed_by')->not->toContain($staff->name)
        ->and($details)->not->toHaveKey('lead');
});

it('shows an inquiry marketing lost as cancelled in the activity log, with nothing about the lead', function (): void {
    $lead = makeLead($this->client);
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-H2', 'lead' => $lead, 'marketing_status' => LeadStatus::Lost]);

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-H2'))->viewData('page')['props']['inquiry'];

    expect($details['status'])->toBe('cancelled')
        ->and($details['history'])->toBe([['track' => 'inquiry', 'status' => 'cancelled', 'at' => $inquiry->marketing_status_changed_at->toIso8601String()]])
        ->and(json_encode($details))->not->toContain($lead->reference)->not->toContain('"lost"')->not->toContain('marketing');
});

it('shares quotations and payment receipts with the client, never the job order or where a file is stored', function (): void {
    detailedInquiry($this);

    $documents = $this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->viewData('page')['props']['inquiry']['documents'];

    // Files that came with no status update each stand as their own group.
    expect(array_merge(...array_map(fn (array $group): array => array_column($group['files'], 'type'), $documents)))->toBe(['quotation', 'receipt'])
        ->and($documents[0])->toMatchArray(['id' => 'group-1', 'status' => null, 'revision' => 1])
        ->and($documents[0]['files'][0])->toMatchArray(['id' => 'document-1', 'type' => 'quotation', 'name' => 'quotation.pdf', 'mimeType' => 'application/pdf'])
        ->and($documents[1]['revision'])->toBeNull()
        ->and(array_keys($documents[0]))->toBe(['id', 'status', 'revision', 'at', 'files'])
        ->and(array_keys($documents[0]['files'][0]))->toBe(['id', 'type', 'name', 'sizeBytes', 'mimeType']);
});

it('shows a visit\'s target deadline with the time from its schedule', function (): void {
    $inquiry = makeInquiry($this->client, ['name' => 'Survey', 'stones' => [], 'services' => ['stone-relocation-dismantling-reinstallation']], ['reference' => 'INQ-V1']);
    app(ScheduleService::class)->createForInquiry($inquiry, $this->client, '2030-01-02', '14:15');

    $requirements = $this->actingAs($this->client)->get(route('quote.show', 'INQ-V1'))
        ->viewData('page')['props']['inquiry']['project']['requirements'];

    expect($requirements['neededBy'])->toBe('2030-01-02')
        ->and($requirements['neededByTime'])->toBe('14:15');
});

it('carries nothing a client should not see', function (): void {
    detailedInquiry($this);

    $json = json_encode($this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->viewData('page')['props']['inquiry']);

    foreach (['client_id', 'changed_by', 'handler', 'secret-path', 'inquiries\/INQ', 'sha256', 'uploaded_by', '"disk"', '"path"', 'job_order'] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }
});

it('gives another client a 404', function (): void {
    detailedInquiry($this);

    $this->actingAs(inquiryClient())->get(route('quote.show', 'INQ-D1'))->assertNotFound();
});

it('answers an unknown and a malformed reference with the same 404', function (): void {
    $this->actingAs($this->client)->get(route('quote.show', 'INQ-NOPE'))->assertNotFound();
    $this->actingAs($this->client)->get(route('quote.show', 'bad ref!'))->assertNotFound();
});

it('opens any inquiry for staff on the inquiry route, and only their own for a client', function (): void {
    detailedInquiry($this);
    makeInquiry(inquiryClient(), attributes: ['reference' => 'INQ-D2']);

    $this->actingAs(inquiryClient(Role::Owner))->get(route('inquiry.show', 'INQ-D1'))->assertOk();
    $this->actingAs($this->client)->get(route('inquiry.show', 'INQ-D1'))->assertOk();
    $this->actingAs($this->client)->get(route('inquiry.show', 'INQ-D2'))->assertNotFound();
});

it('shows the client their refund: the request form, the settled file, and the refund in the payment records', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-RF9', 'status' => InquiryStatus::Refunded]);
    $inquiry->forceFill(['contract_total' => '1000.00'])->save();
    $payments = app(InquiryRepositoryInterface::class);
    $payments->addPayment($inquiry, '600.00', null, now()->subDays(3));
    $payments->addRefund($inquiry, '600.00', null, now()->subDay());
    addInquiryFile($inquiry, DocumentType::RefundRequest);
    addInquiryFile($inquiry, DocumentType::RefundSettlement);

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-RF9'))->viewData('page')['props']['inquiry'];
    $types = array_merge(...array_map(fn (array $group): array => array_column($group['files'], 'type'), $details['documents']));
    $refund = collect($details['payments'])->firstWhere('kind', 'refund');

    expect($details['status'])->toBe('refunded')
        ->and($types)->toContain('refund_request', 'refund_settlement')
        // A refund reads against what was held: 600 before, nothing after.
        ->and($refund)->toMatchArray(['amount' => '600.00', 'currentBalance' => '600.00', 'remainingBalance' => '0.00']);
});
