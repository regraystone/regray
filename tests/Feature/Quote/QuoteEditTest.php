<?php

use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryStatusHistory;
use App\Models\Service;
use App\Models\Stone;
use App\Models\User;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Eloquent\EloquentInquiryRepository;
use App\Services\InquiryStatusRules;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

/**
 * An inquiry sent the way the Inquire page sends it, so it has its schedule
 * and stored files. One project, so one inquiry.
 *
 * @param  list<array<string, mixed>>  $projects
 */
function sentInquiry(mixed $test, array $projects): Inquiry
{
    $test->actingAs($test->client)
        ->post(route('quote.inquire.store'), ['projects' => $projects])
        ->assertSessionHasNoErrors();

    return Inquiry::query()->latest('id')->with('files', 'schedule')->firstOrFail();
}

/**
 * The edit of the inquiry, as the page sends it: its one project, with the
 * create answers, the inquiry's id and the files it keeps.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function projectEdit(Inquiry $inquiry, array $overrides = []): array
{
    return inquiryProjectInput([
        'id' => $inquiry->id,
        'name' => $inquiry->name,
        'keepFileIds' => $inquiry->files->pluck('id')->all(),
        ...$overrides,
    ]);
}

describe('the edit page', function (): void {
    it('opens the client\'s own pending inquiry at Review with real ids', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput([
            'name' => 'Kitchen',
            'attachments' => [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')],
        ])]);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inquire')
                ->where('mode', 'edit')
                ->where('reference', $inquiry->reference)
                ->has('serviceTypes')
                ->has('projects', 1)
                ->where('projects.0.id', $inquiry->id)
                ->where('projects.0.name', 'Kitchen')
                ->where('projects.0.deliveryMethod', 'delivery')
                ->where('projects.0.heldStones.0.slug', Stone::query()->active()->orderBy('id')->value('slug'))
                ->where('projects.0.serviceTypeId', 'natural-stone-applications')
                ->where('projects.0.serviceId', 'slab-supply-only')
                ->where('projects.0.stones.0.stoneId', Stone::query()->active()->orderBy('id')->value('slug'))
                ->where('projects.0.deadlineLocked', false)
                ->where('projects.0.attachments.0.id', $inquiry->files->sole()->id)
                ->where('projects.0.attachments.0.name', 'plan.pdf')
                ->where('projects.0.attachments.0.status', 'saved'));
    });

    it('sends a client to the details page with the reason when the inquiry can no longer change', function (InquiryStatus $status, string $reason): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput()]);
        showInquiryAs($inquiry, $status);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertRedirect(route('quote.show', $inquiry->reference))
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'error')
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.message', $reason);
    })->with([
        'won' => [InquiryStatus::Won, InquiryActions::UPDATE_LOCKED_WHEN_QUOTED],
        'production' => [InquiryStatus::Production, InquiryActions::UPDATE_LOCKED_WHEN_QUOTED],
        'closed' => [InquiryStatus::Closed, InquiryActions::UPDATE_NOT_AVAILABLE],
        'cancelled' => [InquiryStatus::Cancelled, InquiryActions::UPDATE_LOCKED_WHEN_CANCELLED],
    ]);

    it('does not open another client\'s inquiry', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput()]);

        $this->actingAs(inquiryClient())->get(route('quote.edit', $inquiry->reference))->assertNotFound();
    });

    it('is not open to staff', function (Role $role): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput()]);

        $this->actingAs(inquiryClient($role))->get(route('quote.edit', $inquiry->reference))->assertForbidden();
        $this->actingAs(inquiryClient($role))->patch(route('quote.update', $inquiry->reference), [])->assertForbidden();
    })->with([
        'sales' => [Role::Sales],
        'marketing' => [Role::Marketing],
    ]);
});

describe('saving the edit', function (): void {
    it('changes the fields and the service, replaces the stones, moves the visit, and writes an audit row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $otherStone = Stone::query()->active()->orderByDesc('id')->value('slug');
        $historyRows = InquiryStatusHistory::query()->count();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'name' => 'Kitchen and island',
                'stones' => [[...inquiryProjectInput()['stones'][0], 'stoneId' => $otherStone, 'quantity' => 9]],
                'serviceId' => 'countertop',
                'location' => 'Taguig',
                'neededBy' => '2030-05-06',
            ])]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quote.show', $inquiry->reference))
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success')
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.message', "We'll review your changes and get back to you within 1-3 days.");

        $inquiry->refresh();

        expect($inquiry->name)->toBe('Kitchen and island')
            ->and($inquiry->location)->toBe('Taguig')
            ->and($inquiry->service->slug)->toBe('countertop')
            ->and($inquiry->stones()->sole()->quantity)->toBe(9)
            ->and($inquiry->stones()->sole()->stone->slug)->toBe($otherStone)
            ->and($inquiry->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-05-06 10:00', 'Asia/Manila')))->toBeTrue()
            ->and($inquiry->schedule->title)->toBe('Kitchen and island')
            ->and($inquiry->schedule->notes)->toBe("Inquiry {$inquiry->reference} · Kitchen and island · Taguig")
            ->and($inquiry->status)->toBe(InquiryStatus::Pending)
            ->and(InquiryStatusHistory::query()->count())->toBe($historyRows);

        $audit = AuditLog::query()->sole();

        expect($audit->event)->toBe(AuditEvent::Updated)
            ->and($audit->auditable_id)->toBe($inquiry->id)
            ->and($audit->user_id)->toBe($this->client->id)
            ->and($audit->old_values['name'])->toBe('Kitchen')
            ->and($audit->new_values['name'])->toBe('Kitchen and island');
    });

    it('changes the service and records it in the audit row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $before = $inquiry->service_id;

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'serviceId' => 'flooring',
            ])]])
            ->assertSessionHasNoErrors();

        $flooring = Service::query()->where('slug', 'flooring')->value('id');

        expect($inquiry->fresh()->service_id)->toBe($flooring)
            ->and(AuditLog::query()->sole()->old_values['service_id'])->toBe($before)
            ->and(AuditLog::query()->sole()->new_values['service_id'])->toBe($flooring);
    });

    it('needs a service on an edit too', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['serviceId' => ''])]])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'Choose what you need.']);
    });

    it('keeps a service no longer offered that the inquiry already has, but offers no other', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        Service::query()->whereIn('slug', ['slab-supply-only', 'countertop'])->update(['is_active' => false]);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.heldServiceType.id', 'natural-stone-applications')
                ->where('projects.0.heldServiceType.services.0.id', 'slab-supply-only'));

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry)]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'serviceId' => 'countertop',
            ])]])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);
    });

    it('moves an inquiry to pickup and back, keeping the location to the delivery', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'deliveryMethod' => 'pickup',
                'location' => '',
            ])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->refresh()->location)->toBeNull()
            ->and($inquiry->schedule->notes)->toEndWith('· Pickup');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['location' => 'Taguig'])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->refresh()->location)->toBe('Taguig');
    });

    it('refuses to move a visit that is already confirmed, but saves the rest when it stays put', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2030-05-06'])]);
        $inquiry->schedule->forceFill(['status' => ScheduleStatus::Confirmed])->save();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['neededBy' => '2030-05-07'])]])
            ->assertSessionHasErrors('projects.0.neededBy');

        expect($inquiry->schedule->fresh()->arrival_at->equalTo(CarbonImmutable::parse('2030-05-06 10:00', 'Asia/Manila')))->toBeTrue();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['neededBy' => '2030-05-06', 'notes' => 'Back door'])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->notes)->toBe('Back door');
    });

    it('refuses the id of another inquiry, and more than the one project', function (): void {
        $mine = sentInquiry($this, [inquiryProjectInput()]);
        $theirs = sentInquiry($this, [inquiryProjectInput(['name' => 'Not mine'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $mine->reference), ['projects' => [projectEdit($theirs, ['name' => 'Taken'])]])
            ->assertSessionHasErrors('projects');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $mine->reference), ['projects' => [projectEdit($mine), projectEdit($theirs, ['name' => 'Taken'])]])
            ->assertSessionHasErrors('projects');

        expect($theirs->fresh()->name)->toBe('Not mine');
    });

    it('deletes removed files and stores new ones', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['attachments' => [
            UploadedFile::fake()->create('keep.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('drop.pdf', 10, 'application/pdf'),
        ]])]);
        $keep = $inquiry->files->firstWhere('original_name', 'keep.pdf');
        $drop = $inquiry->files->firstWhere('original_name', 'drop.pdf');
        addInquiryFile($inquiry, DocumentType::Quotation, revision: 1);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'keepFileIds' => [$keep->id],
                'attachments' => [UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')],
            ])]])
            ->assertSessionHasNoErrors();

        $disk = Storage::disk(config('inquiry.disk'));
        $files = $inquiry->files()->where('document_type', DocumentType::ClientAttachment)->get();

        // A staff document is never the client's to keep or drop.
        expect($files->pluck('original_name')->all())->toEqualCanonicalizing(['keep.pdf', 'new.pdf'])
            ->and($inquiry->files()->where('document_type', DocumentType::Quotation)->count())->toBe(1)
            ->and(InquiryFile::query()->find($drop->id))->toBeNull();
        $disk->assertExists($keep->path);
        $disk->assertMissing($drop->path);
        $disk->assertExists($files->firstWhere('original_name', 'new.pdf')->path);
    });

    it('leaves nothing changed and no new files behind when a write fails partway', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'attachments' => [
            UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'),
        ]])]);
        $old = $inquiry->files->sole();
        $this->app->bind(InquiryRepositoryInterface::class, FailsOnSecondNewFileRepository::class);

        $this->actingAs($this->client)->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
            'name' => 'Changed',
            'keepFileIds' => [],
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            ],
        ])]])->assertServerError();

        $disk = Storage::disk(config('inquiry.disk'));

        expect($inquiry->fresh()->name)->toBe('Kitchen')
            ->and($inquiry->files()->pluck('id')->all())->toBe([$old->id])
            ->and($disk->allFiles())->toBe([$old->path])
            ->and(AuditLog::query()->count())->toBe(0);
    });

    it('refuses the save when the client approved the quotation after the page was opened', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))->assertOk();
        showInquiryAs($inquiry, InquiryStatus::Won);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['name' => 'Changed'])]])
            ->assertSessionHasErrors(['status' => InquiryActions::UPDATE_LOCKED_WHEN_QUOTED]);

        expect($inquiry->fresh()->name)->toBe('Kitchen');
    });
});

describe('staff editing', function (): void {
    it('lets marketing and sales edit an inquiry the client has not approved yet', function (Role $role): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        showInquiryAs($inquiry, InquiryStatus::Quoted);
        $staff = User::factory()->role($role)->create();

        $this->actingAs($staff)->get(route('inquiry.edit', $inquiry->reference))->assertOk();
        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['name' => 'Changed'])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->name)->toBe('Changed');
    })->with([Role::Marketing, Role::Sales]);

    it('lets staff change the service, and never the client\'s files', function (): void {
        Storage::fake(config('inquiry.disk'));

        $inquiry = sentInquiry($this, [inquiryProjectInput([
            'name' => 'Kitchen',
            'attachments' => [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')],
        ])]);
        $file = $inquiry->files()->sole();
        $staff = User::factory()->role(Role::Sales)->create();

        // No keepFileIds: the stored file stays anyway, as it is not staff's to remove.
        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['serviceId' => 'tables', 'keepFileIds' => []])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->service->slug)->toBe('tables')
            ->and($inquiry->files()->pluck('id')->all())->toBe([$file->id])
            ->and(Storage::disk(config('inquiry.disk'))->exists($file->path))->toBeTrue();

        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, [
                'attachments' => [UploadedFile::fake()->create('other.pdf', 10, 'application/pdf')],
            ])]])
            ->assertSessionHasErrors('projects.0.attachments.0');

        expect($inquiry->files()->count())->toBe(1);
    });

    it('locks the project for everyone once the client approves the quotation', function (Role $role): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        showInquiryAs($inquiry, InquiryStatus::Won);
        $staff = User::factory()->role($role)->create();

        $this->actingAs($staff)->get(route('inquiry.edit', $inquiry->reference))
            ->assertRedirect(route('inquiry.index'));
        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['name' => 'Changed', 'serviceId' => 'tables'])]])
            ->assertSessionHasErrors(['status' => InquiryStatusRules::LOCKED_AFTER_APPROVAL]);

        expect($inquiry->fresh()->name)->toBe('Kitchen')
            ->and($inquiry->fresh()->service->slug)->toBe('slab-supply-only');
    })->with([Role::Sales, Role::Marketing, Role::Admin]);

    it('hides the edit control from staff once the client approves the quotation', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $admin = User::factory()->role(Role::Admin)->create();
        $actions = fn (): array => $this->actingAs($admin)->get(route('inquiry.index'))->viewData('page')['props']['inquiries']['data'][0]['actions'];

        showInquiryAs($inquiry, InquiryStatus::Quoted);
        expect($actions())->toContain('edit');

        showInquiryAs($inquiry, InquiryStatus::Won);
        expect($actions())->not->toContain('edit');
    });
});

/** Fails on the second new file of an edit, after the first is on the disk. */
class FailsOnSecondNewFileRepository extends EloquentInquiryRepository
{
    private int $files = 0;

    public function addFile(Inquiry $inquiry, array $attributes): InquiryFile
    {
        if (++$this->files === 2) {
            throw new RuntimeException('forced failure');
        }

        return parent::addFile($inquiry, $attributes);
    }
}
