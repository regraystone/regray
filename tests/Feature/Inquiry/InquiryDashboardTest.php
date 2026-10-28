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

/** An inquiry every track can move on: quoted, with its total, logistics received, payment not started. */
function movableInquiry(User $client): Inquiry
{
    $inquiry = makeInquiry($client, attributes: ['reference' => 'INQ-R1', 'status' => InquiryStatus::Quoted]);
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
    'sales' => [Role::Sales, true, ['view', 'edit', 'status.sales']],
    'technical' => [Role::Technical, false, ['view']],
    'accounting' => [Role::Accounting, false, ['view', 'status.payment']],
    'logistics' => [Role::Logistics, false, ['view', 'status.process']],
    'owner' => [Role::Owner, false, ['view']],
    'admin' => [Role::Admin, true, ['view', 'edit', 'status.sales', 'status.payment', 'status.process']],
]);

describe('Update Status', function (): void {
    beforeEach(function (): void {
        $this->sales = User::factory()->role(Role::Sales)->create();
        $this->inquiry = makeInquiry($this->client, attributes: ['reference' => 'INQ-S1', 'status' => InquiryStatus::Negotiation]);
        $this->quote = fn (array $overrides = []) => [
            'track' => 'sales',
            'to' => 'quoted',
            'note' => 'Approved in the group chat.',
            'quotedTotal' => '1500.00',
            'files' => ['quotation' => [pdf('quote.pdf')], 'job_order' => [pdf('jo.pdf')]],
            ...$overrides,
        ];
    });

    it('quotes with the quotation, job order and total as one group, starting logistics and not payment', function (): void {
        $this->actingAs($this->sales)->post(route('inquiries.status', 'INQ-S1'), ($this->quote)())->assertRedirect()->assertSessionHasNoErrors();

        $inquiry = $this->inquiry->refresh();
        $history = $inquiry->statusHistory()->where('track', InquiryStatusTrack::Sales)->sole();

        expect($inquiry->sales_status)->toBe(SalesStatus::Quoted)
            ->and($inquiry->quote_revision)->toBe(1)
            ->and($inquiry->quoted_total)->toBe('1500.00')
            ->and($inquiry->process_status)->toBe(ProcessStatus::OrderReceived)
            ->and($inquiry->payment_status)->toBeNull()
            ->and($history->note)->toBe('Approved in the group chat.')
            ->and(InquiryFile::query()->pluck('status_history_id', 'document_type')->all())
            ->toEqual([DocumentType::Quotation->value => $history->id, DocumentType::JobOrder->value => $history->id]);
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
        'no quotation' => ['files.quotation', ['files' => ['job_order' => [pdf('jo.pdf')]]]],
        'no quoted total' => ['quotedTotal', ['quotedTotal' => null]],
    ]);

    it('quotes without the job order, which sales uploads later to start logistics at order received', function (): void {
        $this->actingAs($this->sales)->post(route('inquiries.status', 'INQ-S1'), ($this->quote)(['files' => ['quotation' => [pdf('quote.pdf')]]]))->assertRedirect();

        $inquiry = $this->inquiry->refresh();
        $row = fn () => collect($this->actingAs($this->sales)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'])->firstWhere('reference', 'INQ-S1');

        expect($inquiry->status)->toBe(InquiryStatus::Quoted)
            ->and($inquiry->process_status)->toBeNull()
            ->and($row()['actions'])->toContain('job_order');

        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.'])->assertJsonValidationErrors('files');

        $this->actingAs($this->sales)->post(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Job order in.', 'files' => ['job_order' => [pdf('jo.pdf')]]])->assertRedirect();

        $process = $inquiry->statusHistory()->where('track', InquiryStatusTrack::Process)->sole();

        expect($inquiry->refresh()->process_status)->toBe(ProcessStatus::OrderReceived)
            ->and($inquiry->status)->toBe(InquiryStatus::OrderReceived)
            ->and($inquiry->files()->where('document_type', DocumentType::JobOrder)->value('status_history_id'))->toBe($process->id)
            ->and($row()['actions'])->not->toContain('job_order');

        // Once only.
        $this->actingAs($this->sales)->postJson(route('inquiries.job-order', 'INQ-S1'), ['note' => 'Again.', 'files' => ['job_order' => [pdf('jo2.pdf')]]])
            ->assertJsonValidationErrors(['files.job_order' => 'The job order is already in.']);
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

    $this->actingAs(User::factory()->role(Role::Sales)->create())->post(route('inquiries.status', 'INQ-J1'), [
        'track' => 'sales',
        'to' => 'quoted',
        'note' => 'Approved.',
        'quotedTotal' => '800.00',
        'files' => ['quotation' => [pdf('quote.pdf')], 'job_order' => [pdf('job-order-secret.pdf')]],
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

it('keeps a booking\'s stored instant and shows it in Manila time', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Manila');

    $schedule = app(ScheduleService::class)->createForBooking($this->client, 'Showroom visit', CarbonImmutable::parse('2030-01-02 10:00', 'Asia/Manila'), null);

    expect(DB::selectOne("SELECT to_char(arrival_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS at FROM schedules WHERE id = ?", [$schedule->id])->at)->toBe('2030-01-02 02:00')
        ->and(ScheduleResource::make($schedule->fresh())->resolve(Request::create('/'))['arrivalAt'])->toBe('2030-01-02T10:00:00+08:00');
});
