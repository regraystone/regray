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

    it('refuses a payment that does not add up to the quoted total', function (): void {
        $accounting = User::factory()->role(Role::Accounting)->create();
        $inquiry = movableInquiry($this->client);
        $inquiry->forceFill(['payment_status' => PaymentStatus::Awaiting, 'payment_status_changed_at' => now()])->save();
        $payment = fn (string $to, string $amount) => $this->actingAs($accounting)->postJson(route('inquiries.status', 'INQ-R1'), [
            'track' => 'payment',
            'to' => $to,
            'note' => 'Paid at the showroom.',
            'amountReceived' => $amount,
            'files' => ['receipt' => [pdf('receipt.pdf')], 'invoice' => [pdf('invoice.pdf')]],
        ]);

        $payment('fully_paid', '900.00')->assertUnprocessable()->assertJsonValidationErrors('amountReceived');
        $payment('partial', '1000.00')->assertUnprocessable()->assertJsonValidationErrors('amountReceived');

        $payment('partial', '400.00')->assertRedirect();
        $payment('fully_paid', '600.00')->assertRedirect();

        expect($inquiry->refresh()->payment_status)->toBe(PaymentStatus::FullyPaid)
            ->and($inquiry->paymentTransactions()->sum('amount'))->toEqual(1000);
    });

    it('starts payment from nothing only with a billing statement', function (): void {
        $accounting = User::factory()->role(Role::Accounting)->create();
        $inquiry = movableInquiry($this->client);
        $awaiting = ['track' => 'payment', 'to' => 'awaiting', 'note' => 'Deposit billed.'];

        expect($inquiry->payment_status)->toBeNull();

        $this->actingAs($accounting)->postJson(route('inquiries.status', 'INQ-R1'), $awaiting)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.billing_statement');

        $this->actingAs($accounting)->post(route('inquiries.status', 'INQ-R1'), [...$awaiting, 'files' => ['billing_statement' => [pdf('billing.pdf')]]])->assertRedirect();

        expect($inquiry->refresh()->payment_status)->toBe(PaymentStatus::Awaiting)
            ->and($inquiry->files()->where('document_type', DocumentType::BillingStatement)->value('status_history_id'))
            ->toBe($inquiry->statusHistory()->where('track', InquiryStatusTrack::Payment)->value('id'));
    });

    it('lets each track be moved only with its own permission', function (): void {
        $this->actingAs(User::factory()->role(Role::Accounting)->create())
            ->postJson(route('inquiries.status', 'INQ-S1'), ($this->quote)())
            ->assertForbidden();
    });
});

it('never puts a job order on the details page, but shares the rest of the update', function (): void {
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

    foreach ([$staff, $client] as $details) {
        expect($details['documents'])->toHaveCount(1)
            ->and($details['documents'][0]['status'])->toBe('quoted')
            ->and(array_column($details['documents'][0]['files'], 'type'))->toBe(['quotation'])
            ->and(json_encode($details))->not->toContain('job-order-secret')->not->toContain('job_order');
    }

    expect($inquiry->files()->where('document_type', DocumentType::JobOrder)->exists())->toBeTrue();
});

it('shows only the latest quotation on the details page', function (): void {
    makeInquiry($this->client, attributes: ['reference' => 'INQ-L1', 'status' => InquiryStatus::Negotiation]);
    $sales = User::factory()->role(Role::Sales)->create();
    $move = fn (string $to, string $total, string $file) => $this->actingAs($sales)->post(route('inquiries.status', 'INQ-L1'), [
        'track' => 'sales', 'to' => $to, 'note' => 'Sent.', 'quotedTotal' => $total, 'files' => ['quotation' => [pdf($file)]],
    ])->assertRedirect();

    $move('quoted', '900.00', 'quote-r1.pdf');
    $move('revision', '850.00', 'quote-r2.pdf');
    $move('revision', '800.00', 'quote-r3.pdf');

    $staff = $this->actingAs(User::factory()->role(Role::Owner)->create())->get(route('inquiry.show', 'INQ-L1'))->viewData('page')['props']['inquiry'];
    $client = $this->actingAs($this->client)->get(route('quote.show', 'INQ-L1'))->viewData('page')['props']['inquiry'];

    foreach ([$staff, $client] as $details) {
        expect($details['documents'])->toHaveCount(1)
            ->and($details['documents'][0]['revision'])->toBe(3)
            ->and(array_column($details['documents'][0]['files'], 'name'))->toBe(['quote-r3.pdf']);
    }
});

it('keeps a booking\'s stored instant and shows it in Manila time', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Manila');

    $schedule = app(ScheduleService::class)->createForBooking($this->client, 'Showroom visit', CarbonImmutable::parse('2030-01-02 10:00', 'Asia/Manila'), null);

    expect(DB::selectOne("SELECT to_char(arrival_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS at FROM schedules WHERE id = ?", [$schedule->id])->at)->toBe('2030-01-02 02:00')
        ->and(ScheduleResource::make($schedule->fresh())->resolve(Request::create('/'))['arrivalAt'])->toBe('2030-01-02T10:00:00+08:00');
});
