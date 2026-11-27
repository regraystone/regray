<?php

use App\Data\PhAddress;
use App\Data\ThicknessMeasurement;
use App\Enums\ClientKind;
use App\Enums\ConversationMemberRole;
use App\Enums\ConversationType;
use App\Enums\DetailAnswerType;
use App\Enums\DetailFieldScope;
use App\Enums\DocumentType;
use App\Enums\InquiryStatus;
use App\Enums\LeadStatus;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Enums\ProcessStatus;
use App\Enums\Role;
use App\Enums\SalesStatus;
use App\Enums\SizeUnit;
use App\Enums\ThicknessUnit;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Conversation;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\InquiryFile;
use App\Models\InquiryHandler;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Showcase;
use App\Models\ShowcaseCategory;
use App\Models\Stone;
use App\Models\StoneType;
use App\Models\Thickness;
use App\Models\User;
use App\Models\UserNotification;
use App\Repositories\Contracts\ConversationMemberRepositoryInterface;
use App\Services\Message\SupportConversationService;
use App\Services\PhAddressService;
use App\Support\PhPlaceName;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\ServiceStoneTypeSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Inquiry', 'Unit/Quote');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The rows an inquiry is made from: roles, service types and services, edge
 * profiles, and the stones and finishes the stone seeder brings with it.
 */
function seedInquiryCatalog(): void
{
    test()->seed([
        RolePermissionSeeder::class,
        ServiceSeeder::class,
        EdgeProfileSeeder::class,
        StoneSeeder::class,
        ServiceStoneTypeSeeder::class,
    ]);
}

function inquiryClient(Role $role = Role::Client): User
{
    return User::factory()->role($role)->create();
}

/** The slug of an active stone offered for this service, the `$skip`th in id order. */
function offeredStoneSlug(string $service, int $skip = 0): string
{
    return Stone::query()
        ->offeredFor(Service::query()->where('slug', $service)->firstOrFail())
        ->orderBy('id')
        ->skip($skip)
        ->firstOrFail()
        ->slug;
}

/** The slug of an active stone that is not offered for this service. */
function unofferedStoneSlug(string $service): string
{
    $offered = Stone::query()->offeredFor(Service::query()->where('slug', $service)->firstOrFail())->select('id');

    return Stone::query()->active()->whereNotIn('id', $offered)->orderBy('id')->firstOrFail()->slug;
}

/**
 * The service by its slug, with the questions it asks. A slug no service has
 * (a test sending a bad one) stands in as Slab Supply Only, so the rest of the
 * project is still well formed.
 */
function inquiryService(string $slug): Service
{
    return Service::query()->with('detailFields.options.services')->where('slug', $slug)->first()
        ?? Service::query()->with('detailFields.options.services')->where('slug', 'slab-supply-only')->firstOrFail();
}

/**
 * One stone row in the shape the Inquire page posts for this service: a stone
 * it offers (or the first active stone when it offers none), and an answer to
 * each stone-scope question it asks and no other, with `$overrides` laid over
 * it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryStoneRow(string $service = 'slab-supply-only', array $overrides = [], ?string $offeredBy = null): array
{
    // An application's stone is asked what Slab Supply and Installation asks, from the application's stones.
    if (Service::query()->where('slug', $service)->where('is_application', true)->exists()) {
        $offeredBy ??= $service;
        $service = 'slab-supply-and-installation';
    }

    $asked = inquiryService($service)->detailFields->keyBy('slug');
    // An application's own stones, where the project names one.
    $offered = Stone::query()->offeredFor(inquiryService($offeredBy ?? $service))->orderBy('id')->value('slug');
    $stoneId = $overrides['stoneId'] ?? $offered ?? Stone::query()->active()->orderBy('id')->value('slug');

    return [
        'stoneId' => $stoneId,
        ...$asked->has(DetailField::QUANTITY) ? ['quantity' => 4] : [],
        ...$asked->has(DetailField::SLAB_SIZE) ? ['slabSize' => '160 x 320 cm'] : [],
        // Picked from the list: one the stone comes in, 20 mm where it does, or Regray's advice.
        ...$asked->has(DetailField::THICKNESS) ? ['thickness' => inquiryThickness($stoneId)] : [],
        ...$asked->has(DetailField::EDGE_PROFILE) ? ['edge' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->value('name')] : [],
        ...$asked->has(DetailField::FINISH) ? ['finish' => Finish::query()->where('is_active', true)->orderBy('sort_order')->value('name')] : [],
        ...$overrides,
    ];
}

/**
 * Give the stone slabs in these thicknesses (labels, "20 mm"), with no size,
 * so a test can pick them from its list.
 *
 * @param  list<string>  $labels
 */
function stoneComesIn(string $stoneSlug, array $labels): void
{
    $stone = Stone::query()->where('slug', $stoneSlug)->firstOrFail();

    foreach ($labels as $label) {
        $measurement = ThicknessMeasurement::parse($label) ?? throw new RuntimeException("{$label} is not a thickness.");
        $thickness = Thickness::query()->firstOrCreate(
            ['value' => $measurement->value, 'unit' => $measurement->unit],
            ['sort_order' => $measurement->unit->position()],
        );

        $stone->variants()->firstOrCreate(['size_id' => null, 'thickness_id' => $thickness->id], ['sort_order' => 99]);
    }
}

/**
 * A thickness the stone comes in, as the thickness list sends it: 20 mm when
 * it is one, its first otherwise, "To be advised by Regray" with none listed.
 */
function inquiryThickness(?string $stoneSlug): string
{
    $thicknesses = Stone::query()->withTrashed()->where('slug', $stoneSlug)->first()?->thicknesses ?? collect();

    if ($thicknesses->isEmpty()) {
        return DetailField::THICKNESS_ADVICE;
    }

    return $thicknesses->firstWhere('key', '20-mm')?->label ?? $thicknesses->first()->label;
}

/**
 * An answer to each project-scope question the service asks, as the page
 * sends it: a number, free text, a closed option's slug (never "Same as
 * stone"), yes.
 *
 * @return array<string, string|bool>
 */
function inquiryAnswers(string $service): array
{
    $offered = inquiryService($service);

    return $offered->detailFields
        ->filter(fn (DetailField $field): bool => $field->scope === DetailFieldScope::Project)
        ->mapWithKeys(fn (DetailField $field): array => [$field->slug => match ($field->answer_type) {
            DetailAnswerType::Number => '12.5',
            // Width x length, as the first table shape (Rectangular) asks it.
            DetailAnswerType::Text => $field->slug === DetailField::SIZE ? '120 x 80 cm' : 'Custom size',
            // The first ordinary choice offered for the service: no "Same as stone", custom or advise me.
            DetailAnswerType::Choice => $field->closedOptions($offered->id)
                ->first(fn (DetailFieldOption $option): bool => ! $option->links_to_stone && ! $option->is_custom && ! $option->is_advisory)->slug,
            DetailAnswerType::Boolean => true,
        }])
        ->all();
}

/**
 * One valid project in the shape the Inquire page posts, with `$overrides`
 * laid over it: one service (`serviceId`, Slab Supply Only unless given), a
 * stone row it takes (none for a service that asks for no stones) and its
 * answers.
 *
 * An application named as the service (`'serviceId' => 'flooring'`) is sent
 * as the page sends it: under Slab Supply and Installation (or the service
 * given as `underService`), with the application's stones and project
 * answers and the service's stone answers.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryProjectInput(array $overrides = []): array
{
    $service = $overrides['serviceId'] ?? 'slab-supply-only';
    $application = null;

    if (Service::query()->where('slug', $service)->where('is_application', true)->exists()) {
        $application = $service;
        $service = $overrides['underService'] ?? 'slab-supply-and-installation';
        unset($overrides['serviceId']);
    }

    unset($overrides['underService']);

    // A service that asks an application gets the first, Countertop and Backsplash, unless one is given.
    if ($application === null && ! array_key_exists('applicationId', $overrides) && inquiryService($service)->has_applications) {
        $application = 'countertop-backsplash';
    }

    $stones = $overrides['stones'] ?? (inquiryService($service)->requires_stones ? [inquiryStoneRow($service, [], $application)] : []);

    return [
        'name' => 'Kitchen counters',
        'serviceId' => $service,
        ...$application === null ? [] : ['applicationId' => $application],
        'stones' => $stones,
        'answers' => inquiryAnswers($application ?? $service),
        'brandNoPreference' => '1',
        // Asked only of a service that is delivered or picked up.
        ...(inquiryService($service)->fulfillment->asksDeliveryMethod() ? ['deliveryMethod' => 'delivery'] : []),
        // As staff send it (the picker); clientInquiryInput() swaps in a saved one.
        'address' => testAddressInput(),
        'neededBy' => now()->addDays(10)->toDateString(),
        'notes' => 'Ground floor.',
        ...$overrides,
    ];
}

/**
 * The whole body a client posts to send their own inquiry. A client picks a
 * project's address from their address book, so each project's picker
 * address becomes `addressId`, the id of `$client`'s default saved address
 * (the signed-in user's unless given), saved first when they have none. A
 * project that already names one (`addressId`, `keepAddress`) or has none
 * (a pickup) sends no picker address either way.
 *
 * @param  list<array<string, mixed>>  $projects
 * @return array{projects: list<array<string, mixed>>}
 */
function clientInquiryInput(array $projects, ?User $client = null): array
{
    $client ??= auth()->user();

    return [
        'projects' => array_map(function (array $project) use ($client): array {
            $picked = $project['address'] ?? null;
            unset($project['address']);

            // A pickup sends none, and a project that names its address keeps it.
            if ($picked === null || isset($project['addressId']) || isset($project['keepAddress']) || ! $client instanceof User) {
                return $project;
            }

            return [...$project, 'addressId' => (string) defaultTestAddress($client)->id];
        }, $projects),
    ];
}

/**
 * An edit as a client saves it (quote.update): the address the inquiry holds
 * is kept (keepAddress) unless a project names a saved address of its own. A
 * client never sends the picker's codes.
 *
 * @param  list<array<string, mixed>>  $projects
 * @return array{projects: list<array<string, mixed>>}
 */
function clientEditInput(array $projects): array
{
    return [
        'projects' => array_map(function (array $project): array {
            $picked = $project['address'] ?? null;
            unset($project['address']);

            if ($picked === null || isset($project['addressId']) || isset($project['keepAddress'])) {
                return $project;
            }

            return [...$project, 'keepAddress' => '1'];
        }, $projects),
    ];
}

/**
 * The PSGC places the tests' addresses are in, inserted when missing: Bel-Air
 * (City of Makati, NCR, no province), Kapitolyo (City of Pasig) and San
 * Isidro (City of Antipolo, Rizal), each with one postal code.
 */
function seedTestPlaces(): void
{
    $row = fn (array $place): array => [...$place, 'search_name' => PhPlaceName::fold($place['name'])];

    DB::table('ph_regions')->insertOrIgnore([
        $row(['code' => '130000000', 'name' => 'National Capital Region', 'designation' => 'National Capital Region']),
        $row(['code' => '040000000', 'name' => 'CALABARZON', 'designation' => 'Region IV-A']),
    ]);
    DB::table('ph_provinces')->insertOrIgnore([
        $row(['code' => '045800000', 'name' => 'Rizal', 'region_code' => '040000000']),
    ]);
    DB::table('ph_cities')->insertOrIgnore([
        $row(['code' => '137602000', 'name' => 'City of Makati', 'region_code' => '130000000', 'province_code' => null, 'is_city' => true]),
        $row(['code' => '137403000', 'name' => 'City of Pasig', 'region_code' => '130000000', 'province_code' => null, 'is_city' => true]),
        $row(['code' => '045802000', 'name' => 'City of Antipolo', 'region_code' => '040000000', 'province_code' => '045800000', 'is_city' => true]),
    ]);
    DB::table('ph_barangays')->insertOrIgnore([
        $row(['code' => '137602002', 'name' => 'Bel-Air', 'city_code' => '137602000']),
        $row(['code' => '137403009', 'name' => 'Kapitolyo', 'city_code' => '137403000']),
        $row(['code' => '045802007', 'name' => 'San Isidro (Pob.)', 'city_code' => '045802000']),
    ]);
    DB::table('ph_postal_codes')->insertOrIgnore([
        ['postal_code' => '1209', 'city_code' => '137602000'],
        ['postal_code' => '1603', 'city_code' => '137403000'],
        ['postal_code' => '1870', 'city_code' => '045802000'],
    ]);
}

/**
 * An address as the picker posts it (PhAddressService::FIELDS): `Bel-Air`
 * (Makati), `Kapitolyo` (Pasig) or `San Isidro` (Antipolo, Rizal), with
 * `$overrides` laid over it. Its places are seeded first.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function testAddressInput(string $place = 'Bel-Air', array $overrides = []): array
{
    seedTestPlaces();

    return [
        ...match ($place) {
            'Bel-Air' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137602000', 'barangayCode' => '137602002', 'postalCode' => '1209', 'lat' => '14.561800', 'lng' => '121.029600'],
            'Kapitolyo' => ['regionCode' => '130000000', 'provinceCode' => null, 'cityCode' => '137403000', 'barangayCode' => '137403009', 'postalCode' => '1603', 'lat' => '14.568700', 'lng' => '121.062400'],
            'San Isidro' => ['regionCode' => '040000000', 'provinceCode' => '045800000', 'cityCode' => '045802000', 'barangayCode' => '045802007', 'postalCode' => '1870', 'lat' => '14.586400', 'lng' => '121.176000'],
        },
        ...$overrides,
    ];
}

/** The same address as the app holds it, checked against the seeded places. */
function testAddress(string $place = 'Bel-Air'): PhAddress
{
    return app(PhAddressService::class)->check(testAddressInput($place))['address']
        ?? throw new LogicException("The test place {$place} did not check.");
}

/** Save an address to `$client`'s book; the first one saved is the default. */
function saveTestAddress(User $client, string $label = 'Home', string $place = 'Bel-Air', ?bool $default = null): ClientAddress
{
    return ClientAddress::query()->forceCreate([
        'user_id' => $client->id,
        'label' => $label,
        ...testAddress($place)->toColumns(),
        'is_default' => $default ?? ! ClientAddress::query()->where('user_id', $client->id)->exists(),
    ]);
}

/** `$client`'s default saved address, saved (Home, Bel-Air) when they have none. */
function defaultTestAddress(User $client): ClientAddress
{
    return ClientAddress::query()->where('user_id', $client->id)->where('is_default', true)->first()
        ?? saveTestAddress($client);
}

/**
 * Role statuses that show `$status`, for an inquiry or a card snapshot, NULL
 * for a stage that has not started: sales starts when the lead is won, payment
 * when the client approves, and logistics with the first payment. Won comes
 * with the job order (`job_order` true), billed and waiting for that payment,
 * and shows as quoted, so `$status` Won is the shorthand for that state only;
 * a logistics stage and closed have the job order too. On hold is sales' on
 * hold, before the client approved; cancelled is sales rejected.
 *
 * @return array{sales_status: ?SalesStatus, payment_status: ?PaymentStatus, process_status: ?ProcessStatus, job_order: bool}
 */
function roleStatusesShowing(InquiryStatus $status): array
{
    [$sales, $payment, $process, $jobOrder] = match ($status) {
        InquiryStatus::Pending => [null, null, null, false],
        InquiryStatus::Cancelled => [SalesStatus::Rejected, null, null, false],
        InquiryStatus::Closed => [SalesStatus::Won, PaymentStatus::Awaiting, ProcessStatus::Completed, true],
        InquiryStatus::Negotiation, InquiryStatus::SiteVisited, InquiryStatus::OnHold => [SalesStatus::from($status->value), null, null, false],
        InquiryStatus::Quoted, InquiryStatus::Revision => [SalesStatus::from($status->value), null, null, false],
        InquiryStatus::Won => [SalesStatus::Won, PaymentStatus::Awaiting, null, true],
        // The client asked for their money back after a deposit.
        InquiryStatus::ForRefund => [SalesStatus::ForRefund, PaymentStatus::Partial, ProcessStatus::OrderReceived, true],
        default => [SalesStatus::Won, PaymentStatus::Awaiting, ProcessStatus::from($status->value), true],
    };

    return ['sales_status' => $sales, 'payment_status' => $payment, 'process_status' => $process, 'job_order' => $jobOrder];
}

/**
 * A lead for `$client`, held by `$marketing` (a new marketing user when not
 * given), with the first row of its log. A lead has no status of its own:
 * marketing's decisions are its inquiries' (`marketing_status`).
 */
function makeLead(User $client, ?User $marketing = null): Lead
{
    $lead = Lead::factory()->create([
        'client_id' => $client->id,
        'marketing_user_id' => ($marketing ?? User::factory()->role(Role::Marketing)->create())->id,
    ]);

    $lead->history()->create(['to_status' => LeadStatus::Idle, 'changed_at' => now()]);

    return $lead;
}

/**
 * An inquiry in the database for `$client`, without going through the form.
 * One inquiry is one project: `$project` is `['name' => ..., 'stones' =>
 * [stone slugs], 'service' => service slug]`, its stones stored in the order
 * given.
 *
 * `$attributes` may set `reference` and `created_at`, `created_by` (the staff
 * member who opened it), `lead` (the lead it is linked to), `marketing_status` (marketing's decision; on a lead it defaults
 * to won once sales has started, as only a win starts it), `status` (a
 * display status, reached through roleStatusesShowing()), any of the three
 * role statuses, and `job_order` (whether sales has uploaded the job order
 * file). Without them it has no lead and no role status has started.
 *
 * @param  array{name?: string, stones?: list<string>, service?: string}  $project
 * @param  array{reference?: string, created_at?: mixed, created_by?: User, lead?: Lead, marketing_status?: LeadStatus|null, status?: InquiryStatus, sales_status?: SalesStatus, payment_status?: PaymentStatus, process_status?: ProcessStatus, completed_at?: mixed, job_order?: bool}  $attributes
 */
function makeInquiry(User $client, array $project = [], array $attributes = []): Inquiry
{
    static $sequence = 0;
    $sequence++;

    $statuses = [
        ...isset($attributes['status']) ? roleStatusesShowing($attributes['status']) : [],
        ...array_intersect_key($attributes, array_flip(['sales_status', 'payment_status', 'process_status', 'job_order'])),
    ];

    $inquiry = new Inquiry;
    $inquiry->forceFill([
        'reference' => $attributes['reference'] ?? sprintf('INQ-T%04d', $sequence),
        'client_id' => $client->id,
        'lead_id' => $attributes['lead']->id ?? null,
        'created_by' => $attributes['created_by']->id ?? null,
        'marketing_status' => $marketing = array_key_exists('marketing_status', $attributes)
            ? $attributes['marketing_status']
            : (isset($attributes['lead'], $statuses['sales_status']) ? LeadStatus::Won : null),
        'marketing_status_changed_at' => $marketing === null ? null : now(),
        'name' => $project['name'] ?? 'Project '.$sequence,
        // An application (Flooring…) is held under Slab Supply and Installation, as the form sends it.
        'service_id' => ($service = inquiryService(($named = inquiryService($project['service'] ?? 'slab-supply-only'))->is_application ? 'slab-supply-and-installation' : $named->slug))->id,
        'application_id' => $named->is_application ? $named->id : null,
        'brand_no_preference' => true,
        'delivery_method' => $service->fulfillment->asksDeliveryMethod() ? 'delivery' : null,
        ...testAddress()->toColumns(Inquiry::ADDRESS_PREFIX),
        'sales_status' => $statuses['sales_status'] ?? null,
        'sales_status_changed_at' => isset($statuses['sales_status']) ? now() : null,
        'payment_status' => $statuses['payment_status'] ?? null,
        'payment_status_changed_at' => isset($statuses['payment_status']) ? now() : null,
        'process_status' => $statuses['process_status'] ?? null,
        'process_status_changed_at' => isset($statuses['process_status']) ? now() : null,
        'completed_at' => $attributes['completed_at'] ?? (($statuses['process_status'] ?? null) === ProcessStatus::Completed ? now() : null),
        ...(isset($attributes['created_at']) ? ['created_at' => $attributes['created_at']] : []),
    ])->save();

    if ($statuses['job_order'] ?? false) {
        addInquiryFile($inquiry, DocumentType::JobOrder);
    }

    // Only the stone-row answers its service asks for, as the Inquire page sends them.
    $asked = $service->detailFields->keyBy('slug');
    $edge = $asked->has(DetailField::EDGE_PROFILE) ? EdgeProfile::query()->value('id') : null;
    $finish = $asked->has(DetailField::FINISH) ? Finish::query()->value('id') : null;
    $stones = $project['stones'] ?? [Stone::query()->orderBy('id')->value('slug')];

    foreach (array_values($stones) as $order => $slug) {
        $inquiry->stones()->create([
            'stone_id' => Stone::query()->where('slug', $slug)->value('id'),
            'edge_profile_id' => $edge,
            'finish_id' => $finish,
            'quantity' => $asked->has(DetailField::QUANTITY) ? 1 : null,
            ...$asked->has(DetailField::SLAB_SIZE) ? ['slab_width' => '160', 'slab_length' => '320', 'slab_unit' => SizeUnit::Centimetre] : [],
            ...$asked->has(DetailField::THICKNESS) ? ['thickness' => '20', 'thickness_unit' => ThicknessUnit::Millimetre] : [],
            'sort_order' => $order,
        ]);
    }

    return $inquiry;
}

/** Move an existing inquiry's role statuses so it shows `$status`, adding the job order file where one is needed. */
function showInquiryAs(Inquiry $inquiry, InquiryStatus $status): Inquiry
{
    $statuses = roleStatusesShowing($status);

    $inquiry->forceFill([
        ...array_diff_key($statuses, ['job_order' => true]),
        'sales_status_changed_at' => $statuses['sales_status'] === null ? null : now(),
        'payment_status_changed_at' => $statuses['payment_status'] === null ? null : now(),
        'process_status_changed_at' => $statuses['process_status'] === null ? null : now(),
    ])->save();

    if ($statuses['job_order'] && ! $inquiry->hasJobOrder()) {
        addInquiryFile($inquiry, DocumentType::JobOrder);
    }

    return $inquiry;
}

/**
 * A file row on the inquiry, written straight to the database under a
 * generated stored name, as an upload is. Nothing is put on a disk.
 */
function addInquiryFile(Inquiry $inquiry, DocumentType $type, ?User $uploader = null, ?int $revision = null): InquiryFile
{
    $storedName = Str::uuid()->toString().'.pdf';

    return $inquiry->files()->create([
        'document_type' => $type,
        'revision' => $revision,
        'disk' => 'local',
        'path' => "inquiries/{$inquiry->reference}/{$storedName}",
        'stored_name' => $storedName,
        'original_name' => "{$type->value}.pdf",
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'uploaded_by' => $uploader?->id,
    ]);
}

/*
 * The Quote card's button table, exactly as the product states it: for an
 * inquiry shown at each status (on hold being sales' on hold, before the client
 * approved), what each of the four buttons is. `null` is a button that is not
 * rendered; `[true]` is clickable; `[false, reason]` is shown and locked.
 */
dataset('inquiry button table', [
    'pending' => ['pending', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'negotiation' => ['negotiation', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'site_visited' => ['site_visited', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'on_hold' => ['on_hold', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'quoted' => ['quoted', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'revision' => ['revision', ['view' => [true], 'update' => [true], 'cancel' => [true], 'invoice' => null]],
    'won' => ['won', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
    'production' => ['production', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
    'closed' => ['closed', ['view' => [true], 'update' => null, 'cancel' => null, 'invoice' => [true]]],
    'cancelled' => ['cancelled', ['view' => [true], 'update' => [false, 'This inquiry was cancelled.'], 'cancel' => null, 'invoice' => null]],
    // Approved before the refund was asked for: locked like any approved inquiry.
    'for_refund' => ['for_refund', ['view' => [true], 'update' => [false, 'Locked once you approved the quotation. Message the team to request changes.'], 'cancel' => [false, 'You approved the quotation, so only the team can cancel now. Message the team.'], 'invoice' => [true]]],
]);

/**
 * What one action should look like on the wire, from its row in the table.
 *
 * @param  array{0?: bool, 1?: string}|null  $expectation
 * @return array{visible: bool, enabled: bool, reason?: string}
 */
function expectedAction(?array $expectation): array
{
    if ($expectation === null) {
        return ['visible' => false, 'enabled' => false];
    }

    return [
        'visible' => true,
        'enabled' => $expectation[0],
        ...($expectation[0] ? [] : ['reason' => $expectation[1]]),
    ];
}

/**
 * Assign staff to the inquiry (`inquiry_handlers`), as putting them in its
 * group chat does, so they read it.
 */
function assignInquiry(Inquiry $inquiry, User ...$staff): void
{
    foreach ($staff as $member) {
        InquiryHandler::query()->insertOrIgnore([
            'inquiry_id' => $inquiry->id,
            'user_id' => $member->id,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }
}

/**
 * Let each staff member read the inquiry by the relation their role reads by:
 * marketing as the one who opened it (`created_by`), sales, technical,
 * accounting and logistics as assigned. The admin and the owner read every
 * inquiry already, and a client reads their own. For the tests whose subject
 * is not who reads what.
 */
function letStaffRead(Inquiry $inquiry, User ...$staff): void
{
    foreach ($staff as $member) {
        if ($member->hasRole(Role::Marketing->value)) {
            $inquiry->forceFill(['created_by' => $member->id])->save();
        } elseif (! $member->hasAnyRole([Role::Admin->value, Role::Owner->value, Role::Client->value])) {
            assignInquiry($inquiry, $member);
        }
    }
}

/**
 * A member of staff for the Message tests. Marketing holds the support pool's
 * `support.claim`; sales holds neither it nor `conversations.create`.
 */
function messagingStaff(Role $role = Role::Marketing, string $name = 'Staff'): User
{
    return User::factory()->role($role)->create(['name' => $name]);
}

/** The client's Regray Team thread, as registration creates it. */
function supportThreadFor(User $client): Conversation
{
    return app(SupportConversationService::class)->ensureFor($client);
}

/**
 * A group conversation with `$members` in it, each a member unless `$roles`
 * names another role for their id. `$attributes` may bind it to an inquiry.
 *
 * @param  list<User>  $members
 * @param  array<int, ConversationMemberRole>  $roles
 * @param  array<string, mixed>  $attributes
 */
function groupConversation(array $members, array $roles = [], array $attributes = []): Conversation
{
    $conversation = Conversation::query()->create([
        'type' => ConversationType::Group,
        'name' => 'Kitchen project',
        'created_by' => $members[0]->id,
        ...$attributes,
    ]);

    foreach ($members as $member) {
        app(ConversationMemberRepositoryInterface::class)
            ->upsert($conversation->id, $member->id, $roles[$member->id] ?? ConversationMemberRole::Member);
    }

    return $conversation;
}

/** A text message written straight to the database, without the send rules. */
function textMessage(Conversation $conversation, User $sender, string $body = 'Hello'): Message
{
    return Message::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $sender->id,
        'type' => MessageType::Text,
        'body' => $body,
    ]);
}

/**
 * One staff member per role and an inquiry for each way of reading one, all
 * at production, which every role's list filters let through:
 *
 * - lead: on a lead the marketer holds;
 * - created: opened by the marketer, on another marketer's lead;
 * - foreign: on another marketer's lead, nobody's otherwise;
 * - salesCreated: opened by sales;
 * - salesAssigned: sales is assigned;
 * - assigned: technical, accounting and logistics are assigned.
 *
 * @return array{users: array<string, User>, inquiries: array<string, Inquiry>}
 */
function visibilityWorld(): array
{
    $users = [];

    foreach (Role::cases() as $role) {
        if ($role !== Role::Client) {
            $users[$role->value] = User::factory()->role($role)->create(['name' => ucfirst($role->value)]);
        }
    }

    $otherMarketing = User::factory()->role(Role::Marketing)->create();
    $production = ['status' => InquiryStatus::Production];
    $ownLead = makeLead(inquiryClient(), $users['marketing']);
    $otherLead = makeLead(inquiryClient(), $otherMarketing);

    $inquiries = [
        'lead' => makeInquiry($ownLead->client, attributes: ['reference' => 'INQ-V1', 'lead' => $ownLead, ...$production]),
        'created' => makeInquiry($otherLead->client, attributes: ['reference' => 'INQ-V2', 'lead' => $otherLead, 'created_by' => $users['marketing'], ...$production]),
        'foreign' => makeInquiry($otherLead->client, attributes: ['reference' => 'INQ-V3', 'lead' => $otherLead, ...$production]),
        'salesCreated' => makeInquiry($otherLead->client, attributes: ['reference' => 'INQ-V4', 'lead' => $otherLead, 'created_by' => $users['sales'], ...$production]),
        'salesAssigned' => makeInquiry($otherLead->client, attributes: ['reference' => 'INQ-V5', 'lead' => $otherLead, ...$production]),
        'assigned' => makeInquiry($otherLead->client, attributes: ['reference' => 'INQ-V6', 'lead' => $otherLead, ...$production]),
    ];

    assignInquiry($inquiries['salesAssigned'], $users['sales']);
    assignInquiry($inquiries['assigned'], $users['technical'], $users['accounting'], $users['logistics']);

    return ['users' => $users, 'inquiries' => $inquiries];
}

/** @param  list<string>  $keys */
function referencesOf(array $world, array $keys): array
{
    return array_map(fn (string $key): string => $world['inquiries'][$key]->reference, $keys);
}

/**
 * The bell lines `$user` has been sent, oldest first.
 *
 * @return list<string>
 */
function notificationBodies(User $user): array
{
    return array_values(UserNotification::query()->where('user_id', $user->id)->orderBy('id')->pluck('body')->all());
}

/** A direct (walk-in) client record, with no account and one phone number. */
function walkInClient(string $name = 'Wanda Walk-in'): Client
{
    $client = Client::query()->forceCreate(['kind' => ClientKind::Direct, 'user_id' => null, 'full_name' => $name]);
    $client->contacts()->create(['kind' => 'phone', 'value' => '+63917'.random_int(1000000, 9999999)]);

    return $client;
}

/**
 * A showcase as if the admin's Showcase page had added it: published, with no
 * stones, categories or gallery unless given. The slug is random.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeShowcase(array $attributes = []): Showcase
{
    return Showcase::query()->forceCreate([
        'slug' => 'showcase-'.Str::lower(Str::random(10)),
        'name' => 'Showcase '.Str::random(6),
        'completed_year' => 2024,
        'location' => 'Makati City',
        'overview' => 'A kitchen in stone.',
        'thumbnail_path' => 'projects/cover.jpg',
        'is_published' => true,
        'published_at' => now(),
        ...$attributes,
    ]);
}

/**
 * A showcase category, active unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeShowcaseCategory(string $name, array $attributes = []): ShowcaseCategory
{
    return ShowcaseCategory::query()->create([
        'slug' => Str::slug($name),
        'name' => $name,
        'image_path' => 'categories/'.Str::slug($name).'.jpg',
        'is_active' => true,
        'sort_order' => ShowcaseCategory::query()->count(),
        ...$attributes,
    ]);
}

/**
 * The All services page's Add form, filled in for a new installed service
 * under Natural Stone & Installation.
 *
 * @return array<string, mixed>
 */
function serviceForm(array $overrides = []): array
{
    return [
        'name' => 'Fireplace Surrounds',
        'service_type_id' => ServiceType::query()->where('slug', 'natural-stone-applications')->value('id'),
        'description' => 'Natural stone fireplace surrounds, cut and installed.',
        'features' => ['  Cut to size ', 'Installed on site'],
        'requires_stones' => true,
        'stone_type_ids' => StoneType::query()->whereIn('slug', ['granite', 'marble'])->pluck('id')->all(),
        'fulfillment' => 'installed',
        'questions' => [
            ['id' => DetailField::query()->where('slug', 'area_sqm')->value('id'), 'required' => false],
            ['id' => DetailField::query()->where('slug', 'edge_profile')->value('id'), 'required' => true],
        ],
        'is_active' => true,
        'sort_order' => 10,
        ...$overrides,
    ];
}
