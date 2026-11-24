<?php

use App\Enums\AuditEvent;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Enums\ScheduleStatus;
use App\Models\AuditLog;
use App\Models\EdgeProfile;
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
        ->post(route('quote.inquire.store'), clientInquiryInput($projects))
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

/**
 * The same edit as a client sends it: their project keeps the address it has
 * (`keepAddress`) rather than sending the picker's, which only staff use.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function clientProjectEdit(Inquiry $inquiry, array $overrides = []): array
{
    $project = projectEdit($inquiry, $overrides);

    if (! array_key_exists('address', $overrides)) {
        unset($project['address']);
        // A pickup sends no address at all.
        $project += isset($overrides['addressId']) || ($overrides['deliveryMethod'] ?? null) === 'pickup' ? [] : ['keepAddress' => '1'];
    }

    return $project;
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

    it('fills the edit with the answers the service asked, in the shape the page sends them', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput([
            'serviceId' => 'kitchen-islands',
            'answers' => ['area_sqm' => '6.5', 'waterfall_effect' => '0'],
        ])]);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.answers', ['area_sqm' => 6.5, 'waterfall_effect' => false])
                ->where('projects.0.stones.0.slabSize', null)
                ->where('projects.0.stones.0.thickness', null)
                ->where('projects.0.stones.0.edge', EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->value('name'))
                ->missing('projects.0.landmark')
                ->missing('projects.0.areaSqm'));
    });

    it('sends the listed answers and brands it already has, so the edit shows them though taken off their lists', function (): void {
        $stone = Stone::query()->offeredFor(inquiryService('slab-supply-only'))->orderBy('id')->firstOrFail();
        $inquiry = sentInquiry($this, [inquiryProjectInput(['brandNoPreference' => '0', 'brands' => [$stone->brand->slug]])]);
        $row = $inquiry->stones()->with(['edgeProfile', 'finish'])->sole();
        $row->finish->delete();
        $stone->brand->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.heldChoices.finish', [['value' => $row->finish->name, 'label' => $row->finish->name, 'isAdvisory' => false]])
                ->where('projects.0.heldChoices.edge_profile.0.value', $row->edgeProfile->name)
                ->where('projects.0.heldBrands.0.id', $stone->brand->slug));
    });

    it('fills a slab row with its size and thickness as the labels the form holds, and the stone\'s slabs on its card', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['stones' => [inquiryStoneRow('slab-supply-only', ['slabSize' => '5X10FT', 'thickness' => '0.75 IN'])]])]);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.stones.0.slabSize', '5 x 10 ft')
                ->where('projects.0.stones.0.thickness', '0.75 in')
                ->has('projects.0.heldStones.0.variants')
                ->has('projects.0.heldStones.0.sizes')
                ->has('projects.0.heldStones.0.thicknesses')
                ->has('projects.0.heldStones.0.sizeNote'));
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
    it('changes a slab row\'s size and thickness in place, with no second row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput()]);
        $row = inquiryStoneRow('slab-supply-only');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['stones' => [[...$row, 'slabSize' => '1.6 x 3.2 m', 'thickness' => '0.75 in']]])]])
            ->assertSessionHasNoErrors();

        $stone = $inquiry->stones()->sole();

        expect([$stone->slab_width, $stone->slab_length, $stone->slab_unit?->value, $stone->thickness, $stone->thickness_unit?->value])
            ->toBe(['1.60', '3.20', 'm', '0.75', 'in']);
    });

    it('changes the fields and the service, replaces the stones, moves the visit, and writes an audit row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $otherStone = offeredStoneSlug('countertop-backsplash', 1);
        $historyRows = InquiryStatusHistory::query()->count();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'name' => 'Kitchen and island',
                'stones' => [inquiryStoneRow('countertop-backsplash', ['stoneId' => $otherStone])],
                'serviceId' => 'countertop-backsplash',
                'answers' => ['area_sqm' => '20', 'fascia' => '0'],
                'addressId' => (string) saveTestAddress($this->client, 'Site', 'Kapitolyo')->id,
                'neededBy' => '2030-05-06',
            ])]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('quote.show', $inquiry->reference))
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.type', 'success')
            ->assertSessionHas(SessionKey::FLASH_DATA.'.toast.message', "We'll review your changes and get back to you within 1-3 days.");

        $inquiry->refresh();

        expect($inquiry->name)->toBe('Kitchen and island')
            ->and($inquiry->address_city_name)->toBe('City of Pasig')
            ->and($inquiry->service->slug)->toBe('countertop-backsplash')
            // The new service asks no slab size, thickness or pieces, so the row holds none of them.
            ->and($inquiry->stones()->sole()->quantity)->toBeNull()
            ->and($inquiry->stones()->sole()->slabSizeLabel())->toBeNull()
            ->and($inquiry->stones()->sole()->thicknessLabel())->toBeNull()
            ->and($inquiry->detailAnswers()->orderBy('detail_field_id')->pluck('number_value')->filter()->values()->all())->toBe(['20.00'])
            ->and($inquiry->detailAnswers()->whereNotNull('boolean_value')->sole()->boolean_value)->toBeFalse()
            ->and($inquiry->stones()->sole()->stone->slug)->toBe($otherStone)
            ->and($inquiry->schedule->arrival_at->equalTo(CarbonImmutable::parse('2030-05-06 10:00', 'Asia/Manila')))->toBeTrue()
            ->and($inquiry->schedule->title)->toBe('Kitchen and island')
            ->and($inquiry->schedule->notes)->toBe("Inquiry {$inquiry->reference} · Kitchen and island · City of Pasig")
            ->and($inquiry->status)->toBe(InquiryStatus::Pending)
            ->and(InquiryStatusHistory::query()->count())->toBe($historyRows);

        $audit = AuditLog::query()->sole();

        expect($audit->event)->toBe(AuditEvent::Updated)
            ->and($audit->auditable_id)->toBe($inquiry->id)
            ->and($audit->user_id)->toBe($this->client->id)
            ->and($audit->old_values['name'])->toBe('Kitchen')
            ->and($audit->new_values['name'])->toBe('Kitchen and island')
            ->and($audit->old_values['answers'])->toBe([])
            ->and($audit->new_values['answers'])->toHaveCount(2);
    });

    it('changes the service and records it in the audit row', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $before = $inquiry->service_id;

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'serviceId' => 'flooring',
            ])]])
            ->assertSessionHasNoErrors();

        $flooring = Service::query()->where('slug', 'flooring')->value('id');

        expect($inquiry->fresh()->service_id)->toBe($flooring)
            ->and(AuditLog::query()->sole()->old_values['service_id'])->toBe($before)
            ->and(AuditLog::query()->sole()->new_values['service_id'])->toBe($flooring);
    });

    it('keeps the inquiry\'s own stone while it keeps its service, though the service no longer takes its type', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $stone = $inquiry->stones()->sole()->stone;
        $service = Service::query()->where('slug', 'slab-supply-only')->firstOrFail();
        $service->stoneTypes()->detach($stone->stone_type_id);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['notes' => 'Third floor.', 'stones' => [inquiryStoneRow(overrides: ['stoneId' => $stone->slug])]])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->notes)->toBe('Third floor.')
            ->and($inquiry->stones()->sole()->stone_id)->toBe($stone->id);

        // Another stone of that type is not the inquiry's own, so it is not offered.
        $other = Stone::factory()->for($stone->brand)->create(['stone_type_id' => $stone->stone_type_id]);
        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['stones' => [inquiryStoneRow(overrides: ['stoneId' => $other->slug])]])]])
            ->assertSessionHasErrors(['projects.0.stones.0.stoneId' => 'That stone is not offered for this service.']);
    });

    it('keeps the inquiry\'s own stone though it is switched off, archived, or its brand archived since', function (Closure $change): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $stone = $inquiry->stones()->sole()->stone;
        $change($stone);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['notes' => 'Third floor.', 'stones' => [inquiryStoneRow(overrides: ['stoneId' => $stone->slug])]])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->notes)->toBe('Third floor.')
            ->and($inquiry->stones()->sole()->stone_id)->toBe($stone->id);
    })->with([
        'switched off' => [fn (Stone $stone) => $stone->update(['is_active' => false])],
        'archived' => [fn (Stone $stone) => $stone->delete()],
        'its brand archived' => [fn (Stone $stone) => $stone->brand->forceFill(['archived_at' => now()])->save()],
    ]);

    it('holds no stone of its own once the service changes', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $stone = $inquiry->stones()->sole()->stone;
        $stone->update(['is_active' => false]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'serviceId' => 'flooring',
                'answers' => inquiryAnswers('flooring'),
                'stones' => [inquiryStoneRow('flooring', ['stoneId' => $stone->slug])],
            ])]])
            ->assertSessionHasErrors(['projects.0.stones.0.stoneId' => 'That stone is no longer available.']);
    });

    it('keeps the inquiry\'s own edge profile, finish and answers though taken off their lists since', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        $row = $inquiry->stones()->with(['edgeProfile', 'finish'])->sole();
        $row->edgeProfile->update(['is_active' => false]);
        $row->finish->delete();
        $choice = $inquiry->detailAnswers()->with(['detailField', 'option'])->get()->first(fn ($answer) => $answer->option !== null);
        $choice?->option->update(['is_active' => false]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'notes' => 'Third floor.',
                'stones' => [inquiryStoneRow(overrides: ['stoneId' => $row->stone->slug, 'edge' => $row->edgeProfile->name, 'finish' => $row->finish->name])],
            ])]])
            ->assertSessionHasNoErrors();

        $kept = $inquiry->stones()->sole();
        expect($inquiry->fresh()->notes)->toBe('Third floor.')
            ->and($kept->edge_profile_id)->toBe($row->edge_profile_id)
            ->and($kept->finish_id)->toBe($row->finish_id);

        if ($choice !== null) {
            expect($inquiry->detailAnswers()->where('detail_field_id', $choice->detail_field_id)->value('option_id'))->toBe($choice->option_id);
        }

        // One the inquiry never had is still refused.
        $retired = EdgeProfile::query()->create(['name' => 'Retired', 'is_advisory' => false, 'is_active' => false, 'sort_order' => 9]);
        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'stones' => [inquiryStoneRow(overrides: ['stoneId' => $row->stone->slug, 'edge' => $retired->name, 'finish' => $row->finish->name])],
            ])]])
            ->assertSessionHasErrors('projects.0.stones.0.edge');
    });

    it('keeps the inquiry\'s own brand though archived since', function (): void {
        $stone = Stone::query()->offeredFor(inquiryService('slab-supply-only'))->orderBy('id')->firstOrFail();
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'brandNoPreference' => '0', 'brands' => [$stone->brand->slug]])]);
        $stone->brand->forceFill(['archived_at' => now()])->save();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'notes' => 'Third floor.', 'brandNoPreference' => '0', 'brands' => [$stone->brand->slug],
                'stones' => [inquiryStoneRow(overrides: ['stoneId' => $stone->slug])],
            ])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->notes)->toBe('Third floor.')
            ->and($inquiry->brands()->pluck('slug')->all())->toBe([$stone->brand->slug]);
    });

    it('refuses a change of service that leaves a stone the new service does not offer, for the client and for staff', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'stones' => [[...$row, 'stoneId' => unofferedStoneSlug('countertop-backsplash')]]])]);
        $changes = ['serviceId' => 'countertop-backsplash', 'stones' => [inquiryStoneRow('countertop-backsplash', ['stoneId' => unofferedStoneSlug('countertop-backsplash')])]];
        $edit = clientProjectEdit($inquiry, $changes);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [$edit]])
            ->assertSessionHasErrors(['projects.0.stones.0.stoneId' => 'That stone is not offered for this service.']);

        $this->actingAs(User::factory()->role(Role::Sales)->create())
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [[...projectEdit($inquiry, $changes), 'keepFileIds' => []]]])
            ->assertSessionHasErrors(['projects.0.stones.0.stoneId' => 'That stone is not offered for this service.']);

        expect($inquiry->fresh()->service->slug)->toBe('slab-supply-only');
    });

    it('opens the edit on the stones and stone types of the inquiry\'s service', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['serviceId' => 'pedestal'])]);
        $storage = Service::query()->where('slug', 'pedestal')->firstOrFail();

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stoneFilters.service', 'pedestal')
                ->where('stoneTypes', fn ($types) => collect($types)->pluck('value')->sort()->values()->all() === $storage->stoneTypes->pluck('slug')->sort()->values()->all())
                ->where('stones.data', fn ($stones) => collect($stones)->every(fn ($stone) => in_array($stone['type']['slug'], $storage->stoneTypes->pluck('slug')->all(), true))));
    });

    it('needs a service on an edit too', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['serviceId' => ''])]])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'Choose what you need.']);
    });

    it('keeps a service no longer offered that the inquiry already has, but offers no other', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        Service::query()->whereIn('slug', ['slab-supply-only', 'countertop-backsplash'])->update(['is_active' => false]);

        $this->actingAs($this->client)->get(route('quote.edit', $inquiry->reference))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.heldServiceType.id', 'natural-stone-applications')
                ->where('projects.0.heldServiceType.services.0.id', 'slab-supply-only'));

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry)]])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'serviceId' => 'countertop-backsplash',
            ])]])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);
    });

    it('moves an inquiry to pickup and back, a delivery needing a saved address again', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'deliveryMethod' => 'pickup',
            ])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->refresh()->address_city_code)->toBeNull()
            ->and($inquiry->schedule->notes)->toEndWith('· Pickup');

        // Nothing is held any more, so keeping it is refused.
        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['deliveryMethod' => 'delivery'])]])
            ->assertSessionHasErrors(['projects.0.addressId' => 'Choose one of your saved addresses.']);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
                'deliveryMethod' => 'delivery',
                'addressId' => (string) saveTestAddress($this->client, 'Site', 'San Isidro')->id,
            ])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->refresh()->address_city_name)->toBe('City of Antipolo');
    });

    it('moves its confirmed visit with the due date, and refuses to move one already served while saving the rest', function (): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen', 'neededBy' => '2030-05-06'])]);

        // An inquiry's visit is confirmed from the start, and still moves with its inquiry.
        expect($inquiry->schedule->status)->toBe(ScheduleStatus::Confirmed);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['neededBy' => '2030-05-07'])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->schedule->fresh()->arrival_at->equalTo(CarbonImmutable::parse('2030-05-07 10:00', 'Asia/Manila')))->toBeTrue();

        $inquiry->schedule->forceFill(['status' => ScheduleStatus::Served])->save();

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['neededBy' => '2030-05-08'])]])
            ->assertSessionHasErrors('projects.0.neededBy');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['neededBy' => '2030-05-07', 'notes' => 'Back door'])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->notes)->toBe('Back door')
            ->and($inquiry->schedule->fresh()->arrival_at->equalTo(CarbonImmutable::parse('2030-05-07 10:00', 'Asia/Manila')))->toBeTrue();
    });

    it('refuses the id of another inquiry, and more than the one project', function (): void {
        $mine = sentInquiry($this, [inquiryProjectInput()]);
        $theirs = sentInquiry($this, [inquiryProjectInput(['name' => 'Not mine'])]);

        $this->actingAs($this->client)
            ->patch(route('quote.update', $mine->reference), ['projects' => [clientProjectEdit($theirs, ['name' => 'Taken'])]])
            ->assertSessionHasErrors('projects');

        $this->actingAs($this->client)
            ->patch(route('quote.update', $mine->reference), ['projects' => [clientProjectEdit($mine), clientProjectEdit($theirs, ['name' => 'Taken'])]])
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
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
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

        $this->actingAs($this->client)->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, [
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
            ->patch(route('quote.update', $inquiry->reference), ['projects' => [clientProjectEdit($inquiry, ['name' => 'Changed'])]])
            ->assertSessionHasErrors(['status' => InquiryActions::UPDATE_LOCKED_WHEN_QUOTED]);

        expect($inquiry->fresh()->name)->toBe('Kitchen');
    });
});

describe('staff editing', function (): void {
    it('lets marketing and sales edit an inquiry the client has not approved yet', function (Role $role): void {
        $inquiry = sentInquiry($this, [inquiryProjectInput(['name' => 'Kitchen'])]);
        showInquiryAs($inquiry, InquiryStatus::Quoted);
        $staff = User::factory()->role($role)->create();
        letStaffRead($inquiry, $staff);

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
        letStaffRead($inquiry, $staff);

        // No keepFileIds: the stored file stays anyway, as it is not staff's to remove.
        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['serviceId' => 'staircase', 'keepFileIds' => []])]])
            ->assertSessionHasNoErrors();

        expect($inquiry->fresh()->service->slug)->toBe('staircase')
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
        letStaffRead($inquiry, $staff);

        $this->actingAs($staff)->get(route('inquiry.edit', $inquiry->reference))
            ->assertRedirect(route('inquiry.index'));
        $this->actingAs($staff)
            ->patch(route('inquiry.update', $inquiry->reference), ['projects' => [projectEdit($inquiry, ['name' => 'Changed', 'serviceId' => 'staircase'])]])
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
