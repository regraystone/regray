<?php

use App\Enums\AuditEvent;
use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryProject;
use App\Models\InquiryStatusHistory;
use App\Models\Stone;
use App\Policies\InquiryActions;
use App\Repositories\Contracts\InquiryRepositoryInterface;
use App\Repositories\Eloquent\EloquentInquiryRepository;
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
 * An inquiry sent the way the Inquire page sends it, so it has its schedules
 * and stored files.
 *
 * @param  list<array<string, mixed>>  $projects
 */
function sentInquiry(mixed $test, array $projects): Inquiry
{
    $test->actingAs($test->client)
        ->post(route('quote.inquire.store'), ['projects' => $projects])
        ->assertSessionHasNoErrors();

    return Inquiry::query()->latest('id')->with('projects.files', 'projects.schedule')->firstOrFail();
}

/**
 * The edit of one stored project, as the page sends it: the create answers,
 * the project's id and the files it keeps.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function projectEdit(InquiryProject $project, array $overrides = []): array
{
    return inquiryProjectInput([
        'id' => $project->id,
        'name' => $project->name,
        'keepFileIds' => $project->files->pluck('id')->all(),
        ...$overrides,
    ]);
}

describe('the edit page', function (): void {
    it('opens the client\'s own pending inquiry at Review with real ids', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput([
            'name' => 'Kitchen',
            'attachments' => [UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')],
        ])]);
        $project = $inquiry->projects->sole();

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('inquire')
                ->where('mode', 'edit')
                ->where('reference', $inquiry->reference)
                ->has('services')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->where('projects.0.name', 'Kitchen')
                ->where('projects.0.serviceSlugs', ['natural-stone-supply'])
                ->where('projects.0.stones.0.stoneId', Stone::query()->active()->orderBy('id')->value('slug'))
                ->where('projects.0.deadlineLocked', false)
                ->where('projects.0.attachments.0.id', $project->files->sole()->id)
                ->where('projects.0.attachments.0.name', 'plan.pdf')
                ->where('projects.0.attachments.0.status', 'saved'));
    });

    it('sends a client to the details page with the reason when the inquiry can no longer change', function (InquiryStatus $status, string $reason): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput()]);
        $inquiry->forceFill(['status' => $status])->save();

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertRedirect(route('quote.show', $inquiry->reference))
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'error')
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.message', $reason);
    })->with([
        'quoted' => [InquiryStatus::Quoted, InquiryActions::UPDATE_LOCKED_WHEN_QUOTED],
        'paid' => [InquiryStatus::Paid, InquiryActions::UPDATE_NOT_AVAILABLE],
        'completed' => [InquiryStatus::Completed, InquiryActions::UPDATE_NOT_AVAILABLE],
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
    it('changes the fields, replaces stones and services, moves the visit, and writes an audit row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $project = $inquiry->projects->sole();
        $otherStone = Stone::query()->active()->orderByDesc('id')->value('slug');
        $historyRows = InquiryStatusHistory::query()->count();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, [
                'name' => 'Kitchen and island',
                'serviceIds' => ['natural-stone-supply', 'custom-stone-fabrication'],
                'stones' => [[...inquiryProjectInput()['stones'][0], 'stoneId' => $otherStone, 'quantity' => 9]],
                'location' => 'Taguig',
                'neededBy' => '2030-05-06',
            ])]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quote.show', $inquiry->reference))
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success');

        $project->refresh();

        expect($project->name)->toBe('Kitchen and island')
            ->and($project->location)->toBe('Taguig')
            ->and($project->services()->pluck('slug')->all())->toEqualCanonicalizing(['natural-stone-supply', 'custom-stone-fabrication'])
            ->and($project->stones()->sole()->quantity)->toBe(9)
            ->and($project->stones()->sole()->stone->slug)->toBe($otherStone)
            ->and($project->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-05-06 10:00', 'Asia/Manila')))->toBeTrue()
            ->and($project->schedule->title)->toBe('Kitchen and island')
            ->and($inquiry->fresh()->status)->toBe(InquiryStatus::Pending)
            ->and(InquiryStatusHistory::query()->count())->toBe($historyRows);

        $audit = AuditLog::query()->sole();

        expect($audit->event)->toBe(AuditEvent::Updated)
            ->and($audit->auditable_id)->toBe($inquiry->id)
            ->and($audit->user_id)->toBe($this->client->id)
            ->and($audit->old_values['projects'][$project->id]['name'])->toBe('Kitchen')
            ->and($audit->new_values['projects'][$project->id]['name'])->toBe('Kitchen and island');
    });

    it('refuses to move a visit that is already confirmed, but saves the rest when it stays put', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2030-05-06'])]);
        $project = $inquiry->projects->sole();
        $project->schedule->forceFill(['status' => ScheduleStatus::Confirmed])->save();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, ['neededBy' => '2030-05-07'])]])
            ->assertSessionHasErrors('projects.0.neededBy');

        expect($project->schedule->fresh()->arrival_at->equalTo(CarbonImmutable::parse('2030-05-06 10:00', 'Asia/Manila')))->toBeTrue();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, ['neededBy' => '2030-05-06', 'notes' => 'Back door'])]])
            ->assertSessionHasNoErrors();

        expect($project->fresh()->notes)->toBe('Back door');
    });

    it('refuses a project id from another inquiry', function (): void {
        $mine = sentInquiry($this, [inquiryProjectInput()]);
        $theirs = sentInquiry($this, [inquiryProjectInput(['name' => 'Not mine'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $mine->reference), ['projects' => [projectEdit($theirs->projects->sole(), ['name' => 'Taken'])]])
            ->assertSessionHasErrors('projects');

        expect($theirs->projects->sole()->fresh()->name)->toBe('Not mine');
    });

    it('deletes removed files and stores new ones', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['attachments' => [
            UploadedFile::fake()->create('keep.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->create('drop.pdf', 10, 'application/pdf'),
        ]])]);
        $project = $inquiry->projects->sole();
        $keep = $project->files->firstWhere('original_name', 'keep.pdf');
        $drop = $project->files->firstWhere('original_name', 'drop.pdf');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, [
                'keepFileIds' => [$keep->id],
                'attachments' => [UploadedFile::fake()->create('new.pdf', 10, 'application/pdf')],
            ])]])
            ->assertSessionHasNoErrors();

        $disk = Storage::disk(config('inquiry.disk'));
        $files = $project->files()->get();

        expect($files->pluck('original_name')->all())->toEqualCanonicalizing(['keep.pdf', 'new.pdf'])
            ->and(InquiryFile::query()->find($drop->id))->toBeNull();
        $disk->assertExists($keep->path);
        $disk->assertMissing($drop->path);
        $disk->assertExists($files->firstWhere('original_name', 'new.pdf')->path);
    });

    it('leaves nothing changed and no new files behind when a write fails partway', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'attachments' => [
            UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'),
        ]])]);
        $project = $inquiry->projects->sole();
        $old = $project->files->sole();
        $this->app->bind(InquiryRepositoryInterface::class, FailsOnSecondNewFileRepository::class);

        $this->actingAs($this->client)->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, [
            'name' => 'Changed',
            'keepFileIds' => [],
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'),
            ],
        ])]])->assertServerError();

        $disk = Storage::disk(config('inquiry.disk'));

        expect($project->fresh()->name)->toBe('Kitchen')
            ->and($project->files()->pluck('id')->all())->toBe([$old->id])
            ->and($disk->allFiles())->toBe([$old->path])
            ->and(AuditLog::query()->count())->toBe(0);
    });

    it('refuses the save when the inquiry was quoted after the page was opened', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $project = $inquiry->projects->sole();
        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))->assertOk();
        $inquiry->forceFill(['status' => InquiryStatus::Quoted])->save();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [projectEdit($project, ['name' => 'Changed'])]])
            ->assertSessionHasErrors(['status' => InquiryActions::UPDATE_LOCKED_WHEN_QUOTED]);

        expect($project->fresh()->name)->toBe('Kitchen');
    });
});

/** Fails on the second new file of an edit, after the first is on the disk. */
class FailsOnSecondNewFileRepository extends EloquentInquiryRepository
{
    private int $files = 0;

    public function addFile(InquiryProject $project, array $attributes): InquiryFile
    {
        if (++$this->files === 2) {
            throw new RuntimeException('forced failure');
        }

        return parent::addFile($project, $attributes);
    }
}
