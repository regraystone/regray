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

/** An inquiry every track can move on: won, with its total, logistics received, payment not started. */
function movableInquiry(User $client): Inquiry
{
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-R1', 'status' => InquiryStatus::Won]);
    $inquiry->forceFill(['payment_status' => null, 'payment_status_changed_at' => null, 'quoted_total' => '1000.00', 'quote_revision' => 1])->save();

    return $inquiry;
}

it('gives each role its header and row actions on the Inquiry list', function (Role $role, bool $canAdd, array $actions): void {
    movableInquiry($this->client);
    $user = User::factory()->role($role)->create();

    $page = $this->actingAs($user)->get(route('inquiry.index'))->assertOk()->viewData('page')['props'];

    expect(in_array('inquiries.create', $page['auth']['permissions'], true))->toBe($canAdd)
        ->and($page['inquiries']['data'][0]['actions'])->toBe($actions)
        ->and($page['inquiries']['meta'])->toMatchArray(['currentPage' => 1, 'total' => 1, 'from' => 1, 'to' => 1]);
})->with([
    'marketing' => [Role::Marketing, true, ['view', 'edit']],
    // Won is final, so sales has no status left to move it to.
    'sales' => [Role::Sales, true, ['view', 'edit']],
    'technical' => [Role::Technical, false, ['view']],
    'accounting' => [Role::Accounting, false, ['view', 'status.payment']],
    'logistics' => [Role::Logistics, false, ['view', 'status.process']],
    'owner' => [Role::Owner, false, ['view']],
    'admin' => [Role::Admin, true, ['view', 'edit', 'status.payment', 'status.process']],
]);

it('lists to each role only its own share of the inquiries', function (Role $role, array $references): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1']);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-N1', 'status' => InquiryStatus::Negotiation]);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-Q1', 'status' => InquiryStatus::Quoted])->forceFill(['quote_revision' => 1])->save();
    // Quoted once, then back in negotiation: still waiting on the client.
    makeInquiry($this->client, attributes: ['reference' => 'INQ-R1', 'status' => InquiryStatus::Negotiation])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'status' => InquiryStatus::Won])->forceFill(['quote_revision' => 1])->save();

    $page = $this->actingAs(User::factory()->role($role)->create())->get(route('inquiry.index'))->viewData('page')['props'];

    expect(collect($page['inquiries']['data'])->pluck('reference')->sort()->values()->all())->toBe($references);
})->with([
    'marketing' => [Role::Marketing, ['INQ-N1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'sales' => [Role::Sales, ['INQ-N1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'technical' => [Role::Technical, ['INQ-N1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'accounting, won only' => [Role::Accounting, ['INQ-W1']],
    'logistics, won only' => [Role::Logistics, ['INQ-W1']],
    'owner, pending too' => [Role::Owner, ['INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
    'admin, pending too' => [Role::Admin, ['INQ-N1', 'INQ-P1', 'INQ-Q1', 'INQ-R1', 'INQ-W1']],
]);

it('filters accounting by payment, logistics by process, and everyone else by the inquiry status', function (Role $role, array $options, string $filter, array $references): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-P1']);
    makeInquiry($this->client, attributes: ['reference' => 'INQ-Q1', 'status' => InquiryStatus::Quoted])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-W1', 'status' => InquiryStatus::Won])->forceFill(['quote_revision' => 1])->save();
    makeInquiry($this->client, attributes: ['reference' => 'INQ-M1', 'status' => InquiryStatus::Measure])->forceFill(['quote_revision' => 1, 'payment_status' => PaymentStatus::Partial])->save();

    $page = fn (array $query) => $this->actingAs(User::factory()->role($role)->create())->get(route('inquiry.index', $query))->viewData('page')['props'];
    $filtered = $page(['status' => $filter]);

    expect(array_column($page([])['statusOptions'], 'value'))->toBe(['all', ...$options])
        ->and(collect($filtered['inquiries']['data'])->pluck('reference')->sort()->values()->all())->toBe($references)
        ->and($filtered['filters']['status'])->toBe($filter);
})->with(function (): array {
    $display = array_values(array_diff(InquiryStatus::values(), ['pending']));

    return [
        'marketing' => [Role::Marketing, $display, 'quoted', ['INQ-Q1']],
        'sales' => [Role::Sales, $display, 'won', ['INQ-W1']],
        'technical' => [Role::Technical, $display, 'measure', ['INQ-M1']],
        'owner, pending too' => [Role::Owner, InquiryStatus::values(), 'pending', ['INQ-P1']],
        'admin, pending too' => [Role::Admin, InquiryStatus::values(), 'quoted', ['INQ-Q1']],
        'accounting, by payment' => [Role::Accounting, array_map(fn (PaymentStatus $status): string => $status->value, PaymentStatus::cases()), 'partial', ['INQ-M1']],
        'logistics, by process' => [Role::Logistics, array_map(fn (ProcessStatus $status): string => $status->value, ProcessStatus::cases()), 'measure', ['INQ-M1']],
    ];
});

describe('Update Status', function (): void {
    beforeEach(function (): void {
        $this->sales = User::factory()->role(Role::Sales)->create();
        $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-S1', 'status' => InquiryStatus::Negotiation]);
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
        'no quoted total' => ['quotedTotal', ['quotedTotal' => null]],
        'a revision before any quote' => ['to', ['to' => 'revision']],
        'won before any quote' => ['to', ['to' => 'won', 'quotedTotal' => null, 'files' => []]],
    ]);

    it('revises a sent quotation as a new revision, again if need be, and wins with the job order to start logistics', function (): void {
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

        expect($this->inquiry->refresh()->sales_status)->toBe(SalesStatus::Won)
            ->and($this->inquiry->quoted_total)->toBe('1350.00')
            ->and($this->inquiry->process_status)->toBe(ProcessStatus::OrderReceived)
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

        expect($this->inquiry->refresh()->status)->toBe(InquiryStatus::Won)
            ->and($row()['actions'])->toContain('job_order');

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.'])->assertJsonValidationErrors('files');

        $this->actingAs($this->sales)->post(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.', 'files' => ['job_order' => [pdf('jo.pdf')]]])->assertRedirect();

        $process = $this->inquiry->statusHistory()->where('track', InquiryStatusTrack::Process)->sole();

        expect($this->inquiry->refresh()->process_status)->toBe(ProcessStatus::OrderReceived)
            ->and($this->inquiry->status)->toBe(InquiryStatus::OrderReceived)
            ->and($this->inquiry->files()->where('document_type', DocumentType::JobOrder)->value('status_history_id'))->toBe($process->id)
            ->and($row()['actions'])->not->toContain('job_order');

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Again.', 'files' => ['job_order' => [pdf('jo2.pdf')]]])
            ->assertJsonValidationErrors(['files.job_order' => 'The job order is already in.']);
    });

    it('refuses the job order on its own before the client has approved the quotation', function (): void {
        ($this->move)('quoted')->assertRedirect();

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Too early.', 'files' => ['job_order' => [pdf('jo.pdf')]]])
            ->assertJsonValidationErrors(['files.job_order' => 'The job order comes once the client has approved the quotation.']);
    });

    it('starts payment only once the client has approved the quotation', function (): void {
        ($this->move)('quoted')->assertRedirect();

        $this->actingAs(User::factory()->role(Role::Accounting)->create())
            ->postJson(route('inquiries.status', 'INQ-S1'), ['track' => 'payment', 'to' => 'awaiting', 'note' => 'Deposit billed.', 'files' => ['billing_statement' => [pdf('billing.pdf')]]])
            ->assertJsonValidationErrors(['to' => 'Payment starts once the client has approved the quotation.']);
    });

    it('refuses a payment that does not add up to the contract total', function (): void {
        $accounting = User::factory()->role(Role::Accounting)->create();
        $inquiry = movableInquiry($this->client);
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
        $this->actingAs(User::factory()->role(Role::Accounting)->create())
            ->postJson(route('inquiries.status', 'INQ-S1'), ($this->quote)())
            ->assertForbidden();
    });
});

it('shows the job order on the details page to staff, never to the client', function (): void {
    $inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-J1', 'status' => InquiryStatus::Negotiation]);

    $sales = User::factory()->role(Role::Sales)->create();

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
    makeInquiry($this->client, attributes: ['reference' => 'INQ-L1', 'status' => InquiryStatus::Negotiation]);
    $sales = User::factory()->role(Role::Sales)->create();
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

    $this->actingAs(User::factory()->role(Role::Logistics)->create())->post(route('inquiries.status', 'INQ-C1'), [
        'track' => 'process', 'to' => 'completed', 'note' => 'Installed.',
    ])->assertRedirect();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Closed)
        ->and($inquiry->completed_at?->equalTo(now()))->toBeTrue();
});

it('lists the payment records on the details page: the contract total, then each payment with what is paid and the balance', function (): void {
    movableInquiry($this->client);
    $accounting = User::factory()->role(Role::Accounting)->create();
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
