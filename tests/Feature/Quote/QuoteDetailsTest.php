<?php

use App\Data\SizeMeasurement;
use App\Data\ThicknessMeasurement;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\LeadStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\DetailField;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\Stone;
use App\Models\User;
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
        ['name' => 'Kitchen', 'stones' => $test->slugs, 'service' => 'countertop-backsplash'],
        ['reference' => 'INQ-D1'],
    );

    $inquiry->update(['notes' => 'Ground floor']);
    $inquiry->detailAnswers()->create(DetailField::query()->with('options')->where('slug', 'area_sqm')->firstOrFail()->answerAttributes('12.5'));
    app(ScheduleService::class)->createForInquiry($inquiry, $test->client, '2030-01-02', '09:30');
    $inquiry->files()->create(['document_type' => DocumentType::ClientAttachment, 'disk' => 'local', 'path' => 'inquiries/INQ-D1/0b9f1c2e-plan-stored.pdf', 'stored_name' => '0b9f1c2e-plan-stored.pdf', 'original_name' => 'plan.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 2048, 'sha256' => str_repeat('a', 64), 'uploaded_by' => $test->client->id]);
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
        ->and($inquiry['project']['service'])->toBe('Countertop and Backsplash');

    $project = $inquiry['project'];

    expect(array_column($project['stones'], 'id'))->toBe($this->slugs)
        ->and($project['stones'][0])->toMatchArray([
            // Only the slab services ask how many pieces.
            'quantity' => null,
            'slabSize' => null,
            'thickness' => null,
            // Only a row asked for a thickness has a slab to compare.
            'isStandardSlab' => null,
            'edge' => EdgeProfile::query()->value('name'),
            'finish' => Finish::query()->value('name'),
            // Only what its service asks, labelled, in the service's order.
            'details' => [
                ['label' => 'Edge profile', 'value' => EdgeProfile::query()->value('name')],
                ['label' => 'Finish', 'value' => Finish::query()->value('name')],
            ],
        ])
        ->and($project['requiresStones'])->toBeTrue()
        ->and($project['answers'])->toBe([['label' => 'Project area (sqm)', 'value' => '12.5']])
        ->and($project['requirements'])->not->toHaveKeys(['landmark', 'areaSqm'])
        ->and($project['requirements'])->toMatchArray([
            'location' => 'Makati',
            'neededBy' => '2030-01-02',
            'notes' => 'Ground floor',
        ])
        // The client's own attachment only: the quotation is staff's.
        ->and($project['requirements']['attachments'])->toBe([
            [
                'id' => 'file-1',
                'name' => 'plan.pdf',
                'sizeBytes' => 2048,
                'mimeType' => 'application/pdf',
                'url' => route('inquiry.files.show', ['INQ-D1', '0b9f1c2e-plan-stored.pdf'], absolute: false),
                'previewable' => true,
                'uploadedAt' => InquiryFile::query()->where('stored_name', '0b9f1c2e-plan-stored.pdf')->firstOrFail()->created_at->toIso8601String(),
                'format' => 'PDF',
            ],
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
        ->and(array_keys($documents[0]['files'][0]))->toBe(['id', 'type', 'name', 'sizeBytes', 'mimeType', 'url', 'previewable', 'uploadedAt', 'format']);
});

it('sends each assigned staff member as a name and a role label, and nothing else', function (): void {
    $inquiry = detailedInquiry($this);
    assignInquiry($inquiry, User::factory()->role(Role::Sales)->create());

    $staff = $this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->viewData('page')['props']['inquiry']['assignedStaff'];

    expect($staff)->toHaveCount(1)
        ->and(array_keys($staff[0]))->toBe(['name', 'roleLabel']);
});

it('carries nothing a client should not see', function (): void {
    detailedInquiry($this);

    $json = json_encode($this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))->viewData('page')['props']['inquiry']);

    foreach (['client_id', 'changed_by', 'handler', 'inquiries\/INQ', 'sha256', str_repeat('a', 64), 'stored_name', 'uploaded_by', '"disk"', '"path"', 'job_order'] as $forbidden) {
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
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-RF9', 'status' => InquiryStatus::Cancelled]);
    $inquiry->forceFill(['contract_total' => '1000.00', 'payment_status' => PaymentStatus::Refunded])->save();
    $payments = app(InquiryRepositoryInterface::class);
    $payments->addPayment($inquiry, '600.00', null, now()->subDays(3));
    $payments->addRefund($inquiry, '600.00', null, now()->subDay());
    addInquiryFile($inquiry, DocumentType::RefundRequest);
    addInquiryFile($inquiry, DocumentType::RefundSettlement);

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-RF9'))->viewData('page')['props']['inquiry'];
    $types = array_merge(...array_map(fn (array $group): array => array_column($group['files'], 'type'), $details['documents']));
    $refund = collect($details['payments'])->firstWhere('kind', 'refund');

    // Refunded in full is cancelled, with accounting's badge reading refunded.
    expect($details['status'])->toBe('cancelled')
        ->and($details['paymentStatus'])->toBe('refunded')
        ->and($types)->toContain('refund_request', 'refund_settlement')
        // A refund reads against what was held: 600 before, nothing after.
        ->and($refund)->toMatchArray(['amount' => '600.00', 'currentBalance' => '600.00', 'remainingBalance' => '0.00']);
});

it('shows the service type and the service, and no work per stone', function (): void {
    detailedInquiry($this);

    $project = $this->actingAs($this->client)->get(route('quote.show', 'INQ-D1'))
        ->viewData('page')['props']['inquiry']['project'];

    expect($project['serviceType'])->toBe('Natural Stone & Applications')
        ->and($project['service'])->toBe('Countertop and Backsplash')
        ->and($project)->not->toHaveKeys(['services', 'unassignedWork'])
        ->and($project['stones'][0])->not->toHaveKey('work');
});

it('shows a slab row\'s size and thickness, and whether they are one of the stone\'s own slabs', function (string $size, string $thickness, bool $standard): void {
    $stone = Stone::factory()->withVariants([
        [[160, 320, 'cm'], [20, 'mm']],
        [[60, 120, 'cm'], [30, 'mm']],
        [null, [2, 'cm']],
    ])->create(['stone_type_id' => Stone::query()->offeredFor(inquiryService('slab-supply-only'))->value('stone_type_id')]);

    $this->actingAs($this->client)
        ->post(route('quote.inquire.store'), ['projects' => [inquiryProjectInput(['stones' => [inquiryStoneRow('slab-supply-only', ['stoneId' => $stone->slug, 'slabSize' => $size, 'thickness' => $thickness])]])]])
        ->assertSessionHasNoErrors();

    $row = $this->actingAs($this->client)->get(route('quote.show', Inquiry::query()->sole()->reference))->viewData('page')['props']['inquiry']['project']['stones'][0];

    expect($row)->toMatchArray([
        'slabSize' => SizeMeasurement::parse($size)?->label(),
        'thickness' => ThicknessMeasurement::parse($thickness)?->label(),
        'isStandardSlab' => $standard,
    ])->and($row['details'])->toContain(['label' => 'Thickness', 'value' => ThicknessMeasurement::parse($thickness)?->label()]);
})->with([
    'a listed slab' => ['160 x 320 cm', '20 mm', true],
    'a listed slab, written another way' => ['160X320 CM', '20MM', true],
    'a listed size in a thickness it is not cut in' => ['160 x 320 cm', '30 mm', false],
    'a size of its own' => ['150 x 300 cm', '20 mm', false],
    'a thickness of its own' => ['60 x 120 cm', '25 mm', false],
]);
