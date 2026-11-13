<?php

use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\InquiryStatusTrack;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Http\Resources\ScheduleResource;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\User;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Services\InquiryStatusUpdateService;
use App\Services\ScheduleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * The Inquiry list's role-based actions and the Update Status endpoint.
 */

beforeEach(function (): void {
    seedInquiryCatalog();
    Storage::fake(config('inquiry.disk'));
    $this->client = inquiryClient();
});

function pdf(string $name): UploadedFile
{
    return UploadedFile::fake()->create($name, 12, 'application/pdf');
}

/** Won with the job order and its total, payment not started (so logistics has not either). */
function movableInquiry(User $client): Inquiry
{
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-R1', 'status' => InquiryStatus::Won]);
    $inquiry->forceFill(['payment_status' => null, 'payment_status_changed_at' => null, 'quoted_total' => '1000.00', 'quote_revision' => 1])->save();

    return $inquiry;
}

it('gives each role its header and row actions on the Inquiry list', function (Role $role, bool $canAdd, array $actions): void {
    // Won, partly paid, so logistics has received it: every track can move.
    $inquiry = movableInquiry($this->client);
    $inquiry->forceFill(['payment_status' => PaymentStatus::Partial, 'payment_status_changed_at' => now(), 'process_status' => ProcessStatus::OrderReceived, 'process_status_changed_at' => now()])->save();
    $user = User::factory()->role($role)->create();
    letStaffRead($inquiry, $user);

    $page = $this->actingAs($user)->get(route('inquiry.index'))->assertOk()->viewData('page')['props'];

    expect(in_array('inquiries.create', $page['auth']['permissions'], true))->toBe($canAdd)
        ->and($page['inquiries']['data'][0]['actions'])->toBe($actions)
        ->and($page['inquiries']['meta'])->toMatchArray(['currentPage' => 1, 'total' => 1, 'from' => 1, 'to' => 1]);
})->with([
    // Won: the client approved the quotation, so only the admin may still edit it.
    'marketing' => [Role::Marketing, true, ['view']],
    // Won moves only to for refund.
    'sales' => [Role::Sales, true, ['view', 'status.sales']],
    'technical' => [Role::Technical, false, ['view']],
    'accounting' => [Role::Accounting, false, ['view', 'status.payment']],
    'logistics' => [Role::Logistics, false, ['view', 'status.process']],
    'owner' => [Role::Owner, false, ['view']],
    'admin' => [Role::Admin, true, ['view', 'status.sales', 'status.payment', 'status.process']],
]);

it('lists to each role only its own share of the inquiries', function (Role $role, array $references): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1']);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-N1', 'status' => InquiryStatus::Negotiation]);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-Q1', 'status' => InquiryStatus::Quoted])->forceFill(['quote_revision' => 1])->save();
    // Quoted once, then back in negotiation: still waiting on the client.
    makeInquiry($this->client, attributes: ['reference' => 'INQ-R1', 'status' => InquiryStatus::Negotiation])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'status' => InquiryStatus::Won])->forceFill(['quote_revision' => 1])->save();
    // Partly paid, so logistics has received it.
    makeInquiry($this->client, attributes: ['reference' => 'INQ-L1', 'status' => InquiryStatus::Measure])->forceFill(['quote_revision' => 1, 'payment_status' => PaymentStatus::Partial])->save();
    // Each reads every one of them, so what is left out is the role's own filter.
    $user = User::factory()->role($role)->create();
    Inquiry::query()->each(fn (Inquiry $inquiry) => letStaffRead($inquiry, $user));

    $page = $this->actingAs($user)->get(route('inquiry.index'))->viewData('page')['props'];

    expect(collect($page['inquiries']['data'])->pluck('reference')->sort()->values()->all())->toBe($references);
})->with([
    'marketing, pending too' => [Role::Marketing, ['INQ-L1', 'INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'sales, pending too' => [Role::Sales, ['INQ-L1', 'INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'technical' => [Role::Technical, ['INQ-L1', 'INQ-N1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'accounting, won only' => [Role::Accounting, ['INQ-L1', 'INQ-W1']],
    'logistics, once a payment started it' => [Role::Logistics, ['INQ-L1']],
    'owner, pending too' => [Role::Owner, ['INQ-L1', 'INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'admin, pending too' => [Role::Admin, ['INQ-L1', 'INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
]);

it('filters accounting by payment, logistics by process, and everyone else by the inquiry status', function (Role $role, array $options, string $filter, array $references): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1']);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-Q1', 'status' => InquiryStatus::Quoted])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'status' => InquiryStatus::Won])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-M1', 'status' => InquiryStatus::Measure])->forceFill(['quote_revision' => 1, 'payment_status' => PaymentStatus::Partial])->save();
    $user = User::factory()->role($role)->create();
    Inquiry::query()->each(fn (Inquiry $inquiry) => letStaffRead($inquiry, $user));

    $page = fn (array $query) => $this->actingAs($user)->get(route('inquiry.index', $query))->viewData('page')['props'];
    $filtered = $page(['status' => $filter]);

    expect(array_column($page([])['statusOptions'], 'value'))->toBe(['all', ...$options])
        ->and(collect($filtered['inquiries']['data'])->pluck('reference')->sort()->values()->all())->toBe($references)
        ->and($filtered['filters']['status'])->toBe($filter);
})->with(function (): array {
    // Sales won shows as quoted, so there is no won to filter by.
    $options = array_values(array_diff(InquiryStatus::values(), ['won']));
    $display = array_values(array_diff($options, ['pending']));

    return [
        'marketing, pending too' => [Role::Marketing, $options, 'pending', ['INQ-P1']],
        'sales, pending too' => [Role::Sales, $options, 'quoted', ['INQ-Q1', 'INQ-W1']],
        'technical' => [Role::Technical, $display, 'measure', ['INQ-M1']],
        'owner, pending too' => [Role::Owner, $options, 'pending', ['INQ-P1']],
        'admin, pending too' => [Role::Admin, $options, 'quoted', ['INQ-Q1', 'INQ-W1']],
        'accounting, by payment' => [Role::Accounting, array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases()), 'partial', ['INQ-M1']],
        'logistics, by process' => [Role::Logistics, array_map(fn (ProcessStatus $status): string => $status->value, ProcessStatus::cases()), 'measure', ['INQ-M1']],
    ];
});

describe('Update Status', function (): void {
    beforeEach(function (): void {
        $this->sales = User::factory()->role(Role::Sales)->create();
        $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-S1', 'status' => InquiryStatus::Negotiation]);
        letStaffRead($this->inquiry, $this->sales);
        $this->quote = fn (array $overrides = []) => [
            'track' => 'sales',
            'to' => 'quoted',
            'note' => 'Sent to the client.',
            'quotedTotal' => '1500.00',
            'files' => ['quotation' => [pdf('quote.pdf')]],
            ...$overrides,
        ];
        $this->move = fn (string $to, array $overrides = []) => $this->actingAs($this->sales)->postJson(route('inquiries.status', 'INQ-S1'), ($this->quote)(['to' => $to, ...$overrides]));
    });

    it('quotes with the quotation and total as one group, starting neither logistics nor payment', function (): void {
        $this->actingAs($this->sales)->post(route('inquiries.status', 'INQ-S1'), ($this->quote)())->assertRedirect()->assertSessionHasNoErrors();

        $inquiry = $this->inquiry->refresh();
        $history = $inquiry->statusHistory()->where('track', InquiryStatusTrack::Sales)->sole();

        expect($inquiry->sales_status)->toBe(SalesStatus::Quoted)
            ->and($inquiry->quote_revision)->toBe(1)
            ->and($inquiry->quoted_total)->toBe('1500.00')
            ->and($inquiry->process_status)->toBeNull()
            ->and($inquiry->payment_status)->toBeNull()
            ->and($history->note)->toBe('Sent to the client.')
            ->and(InquiryFile::query()->pluck('status_history_id', 'document_type')->all())
            ->toEqual([DocumentType::Quotation->value => $history->id]);
    });

    it('refuses what the dialog would not allow', function (string $field, array $overrides): void {
        $this->actingAs($this->sales)
            ->postJson(route('inquiries.status', 'INQ-S1'), ($this->quote)($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Negotiation)
            ->and(InquiryFile::query()->exists())->toBeFalse();
    })->with([
        'the current status' => ['to', ['to' => 'negotiation', 'quotedTotal' => null, 'files' => []]],
        'no note' => ['note', ['note' => '']],
        'no quotation' => ['files.quotation', ['files' => []]],
        'a job order with the quote' => ['files.job_order', ['files' => ['quotation' => [pdf('quote.pdf')], 'job_order' => [pdf('jo.pdf')]]]],
        'a quotation that is not a PDF' => ['files.quotation.0', ['files' => ['quotation' => [UploadedFile::fake()->image('quote.png')]]]],
        'no quoted total' => ['quotedTotal', ['quotedTotal' => null]],
        'a revision before any quote' => ['to', ['to' => 'revision']],
        'won before any quote' => ['to', ['to' => 'won', 'quotedTotal' => null, 'files' => []]],
    ]);

    it('revises a sent quotation as a new revision, again if need be, and wins with the job order', function (): void {
        ($this->move)('quoted')->assertRedirect();
        ($this->move)('revision', ['quotedTotal' => '1400.00', 'files' => ['quotation' => [pdf('quote-r2.pdf')]]])->assertRedirect();
        ($this->move)('revision', ['quotedTotal' => '1350.00', 'files' => ['quotation' => [pdf('quote-r3.pdf')]]])->assertRedirect();

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Revision)
            ->and($this->inquiry->quote_revision)->toBe(3)
            ->and($this->inquiry->quoted_total)->toBe('1350.00')
            ->and($this->inquiry->status)->toBe(InquiryStatus::Revision);

        // Won takes the job order alone, and no total.
        ($this->move)('won', ['files' => ['quotation' => [pdf('quote.pdf')]]])->assertJsonValidationErrors('files.quotation');
        ($this->move)('won', ['quotedTotal' => null, 'files' => ['job_order' => [pdf('jo.pdf')]]])->assertRedirect();

        $won = $this->inquiry->statusHistory()->where('track', InquiryStatusTrack::Sales)->where('to_status', 'won')->sole();

        // Logistics waits for the first payment.
        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Won)
            ->and($this->inquiry->quoted_total)->toBe('1350.00')
            ->and($this->inquiry->process_status)->toBeNull()
            ->and($this->inquiry->status)->toBe(InquiryStatus::Quoted)
            ->and($this->inquiry->payment_status)->toBeNull()
            ->and($this->inquiry->files()->where('document_type', DocumentType::JobOrder)->value('status_history_id'))->toBe($won->id);

        // Won is final.
        ($this->move)('revision', ['quotedTotal' => '1200.00'])->assertJsonValidationErrors('to');
    });

    it('wins only with the job order', function (): void {
        ($this->move)('quoted')->assertRedirect();

        ($this->move)('won', ['quotedTotal' => null, 'files' => []])->assertJsonValidationErrors(['files.job_order' => 'Add the job order.']);

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Quoted);
    });

    it('still takes the job order on its own for a win from before it was required, once', function (): void {
        // Won with no job order: only an inquiry won before the job order was required.
        $this->inquiry->forceFill(['sales_status' => SalesStatus::Won, 'quote_revision' => 1, 'quoted_total' => '1500.00'])->save();
        $row = fn () => collect($this->actingAs($this->sales)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-S1');

        expect($this->inquiry->refresh()->status)->toBe(InquiryStatus::Quoted)
            ->and($row()['actions'])->toContain('job_order');

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.'])->assertJsonValidationErrors('files');

        $this->actingAs($this->sales)->post(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.', 'files' => ['job_order' => [pdf('jo.pdf')]]])->assertRedirect();

        // Nothing paid yet, so the job order waits on its own for the first payment.
        expect($this->inquiry->refresh()->process_status)->toBeNull()
            ->and($this->inquiry->status)->toBe(InquiryStatus::Quoted)
            ->and($this->inquiry->files()->where('document_type', DocumentType::JobOrder)->value('status_history_id'))->toBeNull()
            ->and($row()['actions'])->not->toContain('job_order');

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Again.', 'files' => ['job_order' => [pdf('jo2.pdf')]]])
            ->assertJsonValidationErrors(['files.job_order' => 'The job order is already in.']);
    });

    it('takes the job order as a PDF only', function (): void {
        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.', 'files' => ['job_order' => [UploadedFile::fake()->image('jo.png')]]])
            ->assertJsonValidationErrors(['files.job_order.0' => 'The job order must be a PDF.']);
    });

    it('refuses the job order on its own before the client has approved the quotation', function (): void {
        ($this->move)('quoted')->assertRedirect();

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Too early.', 'files' => ['job_order' => [pdf('jo.pdf')]]])
            ->assertJsonValidationErrors(['files.job_order' => 'The job order comes once the client has approved the quotation.']);
    });

    it('starts payment only once the client has approved the quotation', function (): void {
        ($this->move)('quoted')->assertRedirect();
        $accounting = User::factory()->role(Role::Accounting)->create();
        letStaffRead($this->inquiry, $accounting);

        $this->actingAs($accounting)
            ->postJson(route('inquiries.status', 'INQ-S1'), ['track' => 'payment', 'to' => 'awaiting', 'note' => 'Deposit billed.', 'files' => ['billing_statement' => [pdf('billing.pdf')]]])
            ->assertJsonValidationErrors(['to' => 'Payment starts once the client has approved the quotation.']);
    });

    it('refuses a payment that does not add up to the contract total', function (): void {
        $accounting = User::factory()->role(Role::Accounting)->create();
        $inquiry = movableInquiry($this->client);
        letStaffRead($inquiry, $accounting);
        // Quoted at 1,000, contracted at 1,200: payments count against the contract.
        $inquiry->forceFill(['payment_status' => PaymentStatus::Awaiting, 'payment_status_changed_at' => now(), 'contract_total' => '1200.00'])->save();
        $payment = fn (string $to, string $amount) => $this->actingAs($accounting)->postJson(route('inquiries.status', 'INQ-R1'), [
            'track' => 'payment',
            'to' => $to,
            'note' => 'Paid at the showroom.',
            'amountReceived' => $amount,
            'files' => ['receipt' => [pdf('receipt.pdf')], 'invoice' => [pdf('invoice.pdf')]],
        ]);

        $payment('fully_paid', '1000.00')->assertUnprocessable()->assertJsonValidationErrors('amountReceived');
        $payment('partial', '1200.00')->assertUnprocessable()->assertJsonValidationErrors('amountReceived');

        $payment('partial', '400.00')->assertRedirect();
        $payment('fully_paid', '800.00')->assertRedirect();

        expect($inquiry->refresh()->payment_status)->toBe(PaymentStatus::FullyPaid)
            ->and($inquiry->paymentTransactions()->sum('amount'))->toEqual(1200);
    });

    it('starts payment from nothing only with a billing statement and the total contract amount', function (): void {
        $accounting = User::factory()->role(Role::Accounting)->create();
        $inquiry = movableInquiry($this->client);
        letStaffRead($inquiry, $accounting);
        $awaiting = ['track' => 'payment', 'to' => 'awaiting', 'note' => 'Deposit billed.'];
        $billing = ['files' => ['billing_statement' => [pdf('billing.pdf')]]];

        expect($inquiry->payment_status)->toBeNull();

        $this->actingAs($accounting)->postJson(route('inquiries.status', 'INQ-R1'), [...$awaiting, 'contractTotal' => '1100.00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.billing_statement');

        $this->actingAs($accounting)->postJson(route('inquiries.status', 'INQ-R1'), [...$awaiting, ...$billing])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contractTotal' => 'Enter the total contract amount, above 0.']);

        expect($inquiry->refresh()->payment_status)->toBeNull();

        $this->actingAs($accounting)->post(route('inquiries.status', 'INQ-R1'), [...$awaiting, ...$billing, 'contractTotal' => '1100.00'])->assertRedirect();

        expect($inquiry->refresh()->payment_status)->toBe(PaymentStatus::Awaiting)
            ->and($inquiry->contract_total)->toBe('1100.00')
            ->and($inquiry->files()->where('document_type', DocumentType::BillingStatement)->value('status_history_id'))
            ->toBe($inquiry->statusHistory()->where('track', InquiryStatusTrack::Payment)->value('id'));
    });

    it('lets each track be moved only with its own permission', function (): void {
        // Accounting reads the inquiry, so what refuses it is the track's permission.
        $accounting = User::factory()->role(Role::Accounting)->create();
        letStaffRead($this->inquiry, $accounting);

        $this->actingAs($accounting)
            ->postJson(route('inquiries.status', 'INQ-S1'), ($this->quote)())
            ->assertForbidden();
    });
});

it('shows the job order on the details page to staff, never to the client', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-J1', 'status' => InquiryStatus::Negotiation]);

    $sales = User::factory()->role(Role::Sales)->create();
    letStaffRead($inquiry, $sales);

    $this->actingAs($sales)->post(route('inquiries.status', 'INQ-J1'), [
        'track' => 'sales',
        'to' => 'quoted',
        'note' => 'Sent.',
        'quotedTotal' => '800.00',
        'files' => ['quotation' => [pdf('quote.pdf')]],
    ])->assertRedirect();
    $this->actingAs($sales)->post(route('inquiries.status', 'INQ-J1'), [
        'track' => 'sales',
        'to' => 'won',
        'note' => 'Approved.',
        'files' => ['job_order' => [pdf('job-order-secret.pdf')]],
    ])->assertRedirect();

    $staff = $this->actingAs(User::factory()->role(Role::Owner)->create())->get(route('inquiry.show', 'INQ-J1'))->viewData('page')['props']['inquiry'];
    $client = $this->actingAs($this->client)->get(route('quote.show', 'INQ-J1'))->viewData('page')['props']['inquiry'];

    expect(array_column($staff['documents'], 'status'))->toBe(['quoted', 'won'])
        ->and(array_column($staff['documents'][1]['files'], 'type'))->toBe(['job_order'])
        ->and(array_column($staff['documents'][1]['files'], 'name'))->toBe(['job-order-secret.pdf']);

    expect($client['documents'])->toHaveCount(1)
        ->and($client['documents'][0]['status'])->toBe('quoted')
        ->and(array_column($client['documents'][0]['files'], 'type'))->toBe(['quotation'])
        ->and(json_encode($client))->not->toContain('job-order-secret')->not->toContain('job_order');

    expect($inquiry->files()->where('document_type', DocumentType::JobOrder)->exists())->toBeTrue();
});

it('shows sales and the admin every quotation revision on the details page and everyone else only the latest', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-L1', 'status' => InquiryStatus::Negotiation]);
    $sales = User::factory()->role(Role::Sales)->create();
    letStaffRead($inquiry, $sales);
    $move = fn (string $to, string $total, string $file) => $this->actingAs($sales)->post(route('inquiries.status', 'INQ-L1'), [
        'track' => 'sales', 'to' => $to, 'note' => 'Sent.', 'quotedTotal' => $total, 'files' => ['quotation' => [pdf($file)]],
    ])->assertRedirect();

    $move('quoted', '900.00', 'quote-r1.pdf');
    $move('revision', '850.00', 'quote-r2.pdf');
    $move('revision', '800.00', 'quote-r3.pdf');

    $documentsFor = fn (User $viewer, string $route) => $this->actingAs($viewer)->get(route($route, 'INQ-L1'))->viewData('page')['props']['inquiry']['documents'];

    foreach ([$sales, User::factory()->role(Role::Admin)->create()] as $viewer) {
        $documents = $documentsFor($viewer, 'inquiry.show');

        expect(array_column($documents, 'revision'))->toBe([1, 2, 3])
            ->and(array_merge(...array_map(fn (array $group): array => array_column($group['files'], 'name'), $documents)))->toBe(['quote-r1.pdf', 'quote-r2.pdf', 'quote-r3.pdf']);
    }

    foreach ([[User::factory()->role(Role::Owner)->create(), 'inquiry.show'], [$this->client, 'quote.show']] as [$viewer, $route]) {
        $documents = $documentsFor($viewer, $route);

        expect($documents)->toHaveCount(1)
            ->and($documents[0]['revision'])->toBe(3)
            ->and(array_column($documents[0]['files'], 'name'))->toBe(['quote-r3.pdf']);
    }
});

it('keeps a booking\'s stored instant and shows it in Manila time', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Manila');

    $schedule = app(ScheduleService::class)->createForBooking($this->client, 'Showroom visit', CarbonImmutable::parse('2030-01-02 10:00', 'Asia/Manila'), null);

    expect(DB::selectOne("SELECT to_char(arrival_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS at FROM schedules WHERE id = ?", [$schedule->id])->at)->toBe('2030-01-02 02:00')
        ->and(ScheduleResource::make($schedule->fresh())->resolve(Request::create('/'))['arrivalAt'])->toBe('2030-01-02T10:00:00+08:00');
});

it('shows how each inquiry stands against its deadline: an open one by today, a completed one by the day it was completed', function (): void {
    // Late at night, so a project needed today is still due soon though its time has passed.
    $this->travelTo(CarbonImmutable::parse('2030-03-10 23:30', ScheduleService::timezone()));
    $schedules = app(ScheduleService::class);
    $completedOn = fn (string $date): CarbonImmutable => CarbonImmutable::parse("{$date} 18:00", ScheduleService::timezone());

    foreach (['INQ-DUE-PAST' => '2030-03-09', 'INQ-DUE-TODAY' => '2030-03-10', 'INQ-DUE-WEEK' => '2030-03-17', 'INQ-DUE-LATER' => '2030-03-18'] as $reference => $date) {
        $schedules->createForInquiry(makeInquiry($this->client, attributes: ['reference' => $reference, 'status' => InquiryStatus::Negotiation]), $this->client, $date, null);
    }

    // Completed on the deadline's day, though after its time; a day late; and with the deadline still days off.
    $schedules->createForInquiry(makeInquiry($this->client, attributes: ['reference' => 'INQ-DONE-ON-DAY', 'status' => InquiryStatus::Closed, 'completed_at' => $completedOn('2030-03-05')]), $this->client, '2030-03-05', null);
    $schedules->createForInquiry(makeInquiry($this->client, attributes: ['reference' => 'INQ-DONE-LATE', 'status' => InquiryStatus::Closed, 'completed_at' => $completedOn('2030-03-06')]), $this->client, '2030-03-05', null);
    $schedules->createForInquiry(makeInquiry($this->client, attributes: ['reference' => 'INQ-DONE-EARLY', 'status' => InquiryStatus::Closed, 'completed_at' => $completedOn('2030-03-09')]), $this->client, '2030-03-12', null);
    $schedules->createForInquiry(makeInquiry($this->client, attributes: ['reference' => 'INQ-DUE-CANCELLED', 'status' => InquiryStatus::Cancelled]), $this->client, '2030-03-01', null);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-DUE-NONE', 'status' => InquiryStatus::Negotiation]);

    $rows = collect($this->actingAs(User::factory()->role(Role::Owner)->create())->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])
        ->mapWithKeys(fn (array $row): array => [$row['reference'] => [$row['due'], $row['deadline'], $row['completedAt']]])
        ->all();

    expect($rows)->toMatchArray([
        'INQ-DUE-PAST' => ['overdue', '2030-03-09', null],
        'INQ-DUE-TODAY' => ['due_soon', '2030-03-10', null],
        'INQ-DUE-WEEK' => ['due_soon', '2030-03-17', null],
        'INQ-DUE-LATER' => ['on_track', '2030-03-18', null],
        'INQ-DONE-ON-DAY' => ['on_track', '2030-03-05', '2030-03-05'],
        'INQ-DONE-LATE' => ['overdue', '2030-03-05', '2030-03-06'],
        'INQ-DONE-EARLY' => ['on_track', '2030-03-12', '2030-03-09'],
        'INQ-DUE-CANCELLED' => [null, '2030-03-01', null],
        'INQ-DUE-NONE' => [null, null, null],
    ]);
});

it('records when logistics completes the project', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-C1', 'status' => InquiryStatus::Installation]);
    $this->travelTo(CarbonImmutable::parse('2030-04-02 15:00', ScheduleService::timezone()));

    expect($inquiry->completed_at)->toBeNull();

    $logistics = User::factory()->role(Role::Logistics)->create();
    letStaffRead($inquiry, $logistics);

    $this->actingAs($logistics)->post(route('inquiries.status', 'INQ-C1'), [
        'track' => 'process', 'to' => 'completed', 'note' => 'Installed.',
    ])->assertRedirect();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Closed)
        ->and($inquiry->completed_at?->equalTo(now()))->toBeTrue();
});

it('lists the payment records on the details page: the contract total, then each payment with what is paid and the balance', function (): void {
    $accounting = User::factory()->role(Role::Accounting)->create();
    letStaffRead(movableInquiry($this->client), $accounting);
    $move = function (CarbonImmutable $at, array $data) use ($accounting): void {
        $this->travelTo($at);
        $this->actingAs($accounting)->post(route('inquiries.status', 'INQ-R1'), ['track' => 'payment', 'note' => 'Recorded.', ...$data])->assertRedirect();
    };
    $receipts = ['receipt' => [pdf('receipt.pdf')], 'invoice' => [pdf('invoice.pdf')]];

    $move(CarbonImmutable::parse('2030-05-01 09:00'), ['to' => 'awaiting', 'contractTotal' => '1200.00', 'files' => ['billing_statement' => [pdf('billing.pdf')]]]);
    $move(CarbonImmutable::parse('2030-05-03 09:00'), ['to' => 'partial', 'amountReceived' => '500.00', 'files' => $receipts]);
    $move(CarbonImmutable::parse('2030-05-09 09:00'), ['to' => 'fully_paid', 'amountReceived' => '700.00', 'files' => $receipts]);

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-R1'))->viewData('page')['props']['inquiry'];
    $payments = $details['payments'];

    expect($details['contractTotal'])->toBe('1200.00');

    expect(array_map(fn (array $record): array => array_diff_key($record, ['at' => true]), $payments))->toBe([
        ['id' => 'payment-1', 'kind' => 'contract', 'amount' => '1200.00', 'currentBalance' => null, 'remainingBalance' => null],
        ['id' => 'payment-2', 'kind' => 'payment', 'amount' => '500.00', 'currentBalance' => '1200.00', 'remainingBalance' => '700.00'],
        ['id' => 'payment-3', 'kind' => 'payment', 'amount' => '700.00', 'currentBalance' => '700.00', 'remainingBalance' => '0.00'],
    ])->and(array_column($payments, 'at'))->toBe([
        CarbonImmutable::parse('2030-05-01 09:00')->toIso8601String(),
        CarbonImmutable::parse('2030-05-03 09:00')->toIso8601String(),
        CarbonImmutable::parse('2030-05-09 09:00')->toIso8601String(),
    ]);
});

it('has no payment records before payment has started', function (): void {
    movableInquiry($this->client);

    $details = $this->actingAs($this->client)->get(route('quote.show', 'INQ-R1'))->viewData('page')['props']['inquiry'];

    expect($details['payments'])->toBe([])
        ->and($details['contractTotal'])->toBeNull();
});

it('hands an inquiry to logistics only once it has the job order and a payment', function (): void {
    $sales = User::factory()->role(Role::Sales)->create();
    $accounting = User::factory()->role(Role::Accounting)->create();
    $logistics = User::factory()->role(Role::Logistics)->create();
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-G1', 'status' => InquiryStatus::Negotiation]);
    letStaffRead($inquiry, $sales, $accounting, $logistics);
    $listedFor = fn (User $user): bool => collect($this->actingAs($user)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->contains('reference', 'INQ-G1');
    $move = fn (User $by, array $data) => $this->actingAs($by)->post(route('inquiries.status', 'INQ-G1'), ['note' => 'Moved.', ...$data])->assertRedirect();

    $move($sales, ['track' => 'sales', 'to' => 'quoted', 'quotedTotal' => '1000.00', 'files' => ['quotation' => [pdf('quote.pdf')]]]);
    $move($sales, ['track' => 'sales', 'to' => 'won', 'files' => ['job_order' => [pdf('jo.pdf')]]]);
    $move($accounting, ['track' => 'payment', 'to' => 'awaiting', 'contractTotal' => '1000.00', 'files' => ['billing_statement' => [pdf('billing.pdf')]]]);

    expect($inquiry->refresh()->process_status)->toBeNull()
        ->and($inquiry->status)->toBe(InquiryStatus::Quoted)
        ->and($listedFor($logistics))->toBeFalse();

    $this->actingAs($logistics)->postJson(route('inquiries.status', 'INQ-G1'), ['track' => 'process', 'to' => 'completed', 'note' => 'Done.'])
        ->assertUnprocessable();

    $move($accounting, ['track' => 'payment', 'to' => 'partial', 'amountReceived' => '400.00', 'files' => ['receipt' => [pdf('receipt.pdf')], 'invoice' => [pdf('invoice.pdf')]]]);

    $received = $inquiry->statusHistory()->where('track', InquiryStatusTrack::Process)->sole();

    expect($inquiry->refresh()->process_status)->toBe(ProcessStatus::OrderReceived)
        ->and($inquiry->status)->toBe(InquiryStatus::OrderReceived)
        ->and($received->changed_by)->toBe($accounting->id)
        ->and($listedFor($logistics))->toBeTrue();
});

describe('refunds', function (): void {
    beforeEach(function (): void {
        $this->sales = User::factory()->role(Role::Sales)->create();
        $this->accounting = User::factory()->role(Role::Accounting)->create();
        // Won, billed at 1,000 and paid 600, so logistics has received it.
        $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-RF1', 'status' => InquiryStatus::OrderReceived]);
        $this->inquiry->forceFill(['quoted_total' => '1000.00', 'quote_revision' => 1, 'contract_total' => '1000.00', 'payment_status' => PaymentStatus::Partial])->save();
        app(InquiryRepositoryInterface::class)->addPayment($this->inquiry, '600.00', $this->accounting->id, now());
        letStaffRead($this->inquiry, $this->sales, $this->accounting);
        $this->move = fn (User $by, array $data) => $this->actingAs($by)->postJson(route('inquiries.status', 'INQ-RF1'), ['note' => 'Refund.', ...$data]);
        $this->askRefund = fn () => ($this->move)($this->sales, ['track' => 'sales', 'to' => 'for_refund', 'files' => ['refund_request' => [pdf('refund-request.pdf')]]]);
        $this->refund = fn (string $to, string $amount) => ($this->move)($this->accounting, ['track' => 'payment', 'to' => $to, 'amountRefunded' => $amount, 'files' => ['refund_settlement' => [pdf('refund-settled.pdf')]]]);
    });

    it('lets sales move a won inquiry only to for refund, with the refund request form and a note', function (): void {
        ($this->move)($this->sales, ['track' => 'sales', 'to' => 'rejected'])->assertJsonValidationErrors('to');
        ($this->move)($this->sales, ['track' => 'sales', 'to' => 'for_refund'])->assertJsonValidationErrors('files.refund_request');
        ($this->move)($this->sales, ['track' => 'sales', 'to' => 'for_refund', 'note' => '', 'files' => ['refund_request' => [pdf('r.pdf')]]])->assertJsonValidationErrors('note');

        ($this->askRefund)()->assertRedirect();

        $update = $this->inquiry->statusHistory()->where('track', InquiryStatusTrack::Sales)->latest('id')->first();

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::ForRefund)
            ->and($this->inquiry->status)->toBe(InquiryStatus::ForRefund)
            ->and($this->inquiry->files()->where('document_type', DocumentType::RefundRequest)->value('status_history_id'))->toBe($update->id);

        // For refund is final for sales, and logistics stops.
        ($this->move)($this->sales, ['track' => 'sales', 'to' => 'rejected'])->assertJsonValidationErrors('to');
        $logistics = User::factory()->role(Role::Logistics)->create();
        letStaffRead($this->inquiry, $logistics);
        $this->actingAs($logistics)->postJson(route('inquiries.status', 'INQ-RF1'), ['track' => 'process', 'to' => 'measure', 'note' => 'Go.'])
            ->assertJsonValidationErrors('to');
    });

    it('never offers a refund before the inquiry is won', function (): void {
        $quoted = makeInquiry($this->client, attributes: ['reference' => 'INQ-RF2', 'status' => InquiryStatus::Quoted]);
        $quoted->forceFill(['quote_revision' => 1, 'quoted_total' => '1000.00'])->save();
        letStaffRead($quoted, $this->sales);

        $this->actingAs($this->sales)->postJson(route('inquiries.status', 'INQ-RF2'), ['track' => 'sales', 'to' => 'for_refund', 'note' => 'Refund.', 'files' => ['refund_request' => [pdf('r.pdf')]]])
            ->assertJsonValidationErrors('to');
    });

    it('lets accounting refund part, then the rest, each with the settled file and an amount that adds up', function (): void {
        // No refund until sales asks for one.
        ($this->refund)('partially_refunded', '100.00')->assertJsonValidationErrors('to');

        ($this->askRefund)()->assertRedirect();

        $listed = collect($this->actingAs($this->accounting)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-RF1');
        expect($listed['actions'])->toContain('status.payment');

        ($this->move)($this->accounting, ['track' => 'payment', 'to' => 'partially_refunded', 'amountRefunded' => '100.00'])->assertJsonValidationErrors('files.refund_settlement');
        ($this->refund)('partially_refunded', '600.00')->assertJsonValidationErrors(['amountRefunded' => 'A partial refund has to be less than the ₱600.00 still held; this would refund ₱600.00.']);
        ($this->refund)('refunded', '200.00')->assertJsonValidationErrors(['amountRefunded' => 'A full refund has to return the ₱600.00 still held; this would refund ₱200.00.']);

        ($this->refund)('partially_refunded', '200.00')->assertRedirect();

        // A partial refund leaves sales at for refund.
        expect($this->inquiry->refresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
            ->and($this->inquiry->sales_status)->toBe(SalesStatus::ForRefund)
            ->and($this->inquiry->status)->toBe(InquiryStatus::ForRefund);

        ($this->refund)('refunded', '400.00')->assertRedirect();

        // The full refund rejects sales on its own, which cancels the inquiry.
        $salesMove = $this->inquiry->statusHistory()->where('track', InquiryStatusTrack::Sales)->reorder()->latest('id')->firstOrFail();

        expect($this->inquiry->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($this->inquiry->sales_status)->toBe(SalesStatus::Rejected)
            ->and($this->inquiry->status)->toBe(InquiryStatus::Cancelled)
            ->and($salesMove->only(['from_status', 'to_status', 'changed_by', 'note']))->toBe([
                'from_status' => SalesStatus::ForRefund->value,
                'to_status' => SalesStatus::Rejected->value,
                'changed_by' => $this->accounting->id,
                'note' => InquiryStatusUpdateService::REFUNDED_NOTE,
            ])
            ->and($this->inquiry->paymentTransactions()->where('kind', 'refund')->pluck('amount')->all())->toEqual(['200.00', '400.00'])
            ->and($this->inquiry->files()->where('document_type', DocumentType::RefundSettlement)->count())->toBe(2);

        // Accounting still has it on its list, as refunded.
        $listed = collect($this->actingAs($this->accounting)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-RF1');
        expect($listed)->toMatchArray(['status' => 'cancelled', 'paymentStatus' => 'refunded']);

        // Refunded is final.
        ($this->refund)('refunded', '1.00')->assertJsonValidationErrors('to');
    });

    it('offers no refund until a partial or full payment is in', function (): void {
        $this->inquiry->paymentTransactions()->delete();

        foreach ([null, PaymentStatus::Awaiting] as $payment) {
            $this->inquiry->forceFill(['payment_status' => $payment, 'process_status' => null])->save();

            ($this->askRefund)()->assertJsonValidationErrors('to');

            $row = collect($this->actingAs($this->sales)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-RF1');
            expect($row['actions'])->not->toContain('status.sales');
        }

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Won);

        // Should one get there anyway, accounting still has nothing to give back.
        $this->inquiry->forceFill(['sales_status' => SalesStatus::ForRefund])->save();
        ($this->refund)('refunded', '100.00')->assertJsonValidationErrors(['amountRefunded' => 'Nothing has been paid, so there is nothing to refund.']);
    });

    it('offers no refund once logistics has completed the project', function (): void {
        $this->inquiry->forceFill(['payment_status' => PaymentStatus::FullyPaid, 'process_status' => ProcessStatus::Completed])->save();

        expect($this->inquiry->refresh()->status)->toBe(InquiryStatus::Closed);

        ($this->askRefund)()->assertJsonValidationErrors('to');

        $row = collect($this->actingAs($this->sales)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-RF1');
        expect($row['actions'])->not->toContain('status.sales')
            ->and($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Won);
    });
});
