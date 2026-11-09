<?php

use App\Enums\DeliveryMethod;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Stone;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake(config('inquiry.disk'));
    seedInquiryCatalog();
    $this->client = inquiryClient();
});

/**
 * Post one inquiry to the quote route and hand back the response.
 *
 * @param  list<array<string, mixed>>  $projects
 */
function postInquiry(mixed $test, array $projects): TestResponse
{
    return $test->actingAs($test->client)->post(route('quote.inquire.store'), ['projects' => $projects]);
}

/**
 * Each rule has a value that must be accepted and a value that must be refused,
 * both given as a change to a valid project plus the field the error lands on.
 */
dataset('accepted values', [
    'name at 100 characters' => [fn (array $p): array => [...$p, 'name' => str_repeat('a', 100)]],
    'landmark at 150 characters' => [fn (array $p): array => [...$p, 'landmark' => str_repeat('a', 150)]],
    'notes at 1000 characters' => [fn (array $p): array => [...$p, 'notes' => str_repeat('a', 1000)]],
    'area above zero' => [fn (array $p): array => [...$p, 'areaSqm' => '0.01']],
    'no area' => [fn (array $p): array => [...$p, 'areaSqm' => '']],
    'deadline today' => [fn (array $p): array => [...$p, 'neededBy' => now('Asia/Manila')->toDateString()]],
    'quantity 1' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 1]]]],
    'quantity 9999' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 9999]]]],
    'a location from the list' => [fn (array $p): array => [...$p, 'location' => 'Las Piñas']],
    'ten files' => [fn (array $p): array => [...$p, 'attachments' => array_map(
        fn (int $n): UploadedFile => UploadedFile::fake()->create("plan-{$n}.pdf", 10, 'application/pdf'),
        range(1, 10),
    )]],
    'a file of exactly 10 MB' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->create('big.pdf', 10240, 'application/pdf')]]],
    'a png' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.png')]]],
    'a jpeg' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.jpeg')]]],
]);

dataset('refused values', [
    'name over 100 characters' => [fn (array $p): array => [...$p, 'name' => str_repeat('a', 101)], 'projects.0.name'],
    'no name' => [fn (array $p): array => [...$p, 'name' => ''], 'projects.0.name'],
    'landmark over 150 characters' => [fn (array $p): array => [...$p, 'landmark' => str_repeat('a', 151)], 'projects.0.landmark'],
    'notes over 1000 characters' => [fn (array $p): array => [...$p, 'notes' => str_repeat('a', 1001)], 'projects.0.notes'],
    'area of zero' => [fn (array $p): array => [...$p, 'areaSqm' => '0'], 'projects.0.areaSqm'],
    'negative area' => [fn (array $p): array => [...$p, 'areaSqm' => '-3'], 'projects.0.areaSqm'],
    'deadline in the past' => [fn (array $p): array => [...$p, 'neededBy' => now()->subDay()->toDateString()], 'projects.0.neededBy'],
    'no deadline date' => [fn (array $p): array => [...$p, 'neededBy' => ''], 'projects.0.neededBy'],
    'quantity of 0' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 0]]], 'projects.0.stones.0.quantity'],
    'quantity of 10000' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 10000]]], 'projects.0.stones.0.quantity'],
    'a fractional quantity' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 1.5]]], 'projects.0.stones.0.quantity'],
    'a location not in the list' => [fn (array $p): array => [...$p, 'location' => 'Atlantis'], 'projects.0.location'],
    'an unknown stone' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'stoneId' => 'no-such-stone']]], 'projects.0.stones.0.stoneId'],
    'no service' => [fn (array $p): array => [...$p, 'serviceId' => ''], 'projects.0.serviceId'],
    'an unknown service' => [fn (array $p): array => [...$p, 'serviceId' => 'no-such-option'], 'projects.0.serviceId'],
    'an unknown delivery method' => [fn (array $p): array => [...$p, 'deliveryMethod' => 'drone'], 'projects.0.deliveryMethod'],
    'an unknown edge profile' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'edge' => 'Wavy']]], 'projects.0.stones.0.edge'],
    'eleven files' => [fn (array $p): array => [...$p, 'attachments' => array_map(
        fn (int $n): UploadedFile => UploadedFile::fake()->create("plan-{$n}.pdf", 10, 'application/pdf'),
        range(1, 11),
    )], 'projects.0.attachments'],
    'a file over 10 MB' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf')]], 'projects.0.attachments.0'],
    'an executable' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->create('run.exe', 10, 'application/octet-stream')]], 'projects.0.attachments.0'],
]);

it('accepts', function (Closure $change): void {
    postInquiry($this, [$change(inquiryProjectInput())])
        ->assertSessionHasNoErrors();

    expect(Inquiry::query()->count())->toBe(1);
})->with('accepted values');

it('refuses', function (Closure $change, string $errorKey): void {
    postInquiry($this, [$change(inquiryProjectInput())])
        ->assertSessionHasErrors($errorKey);

    expect(Inquiry::query()->count())->toBe(0);
})->with('refused values');

describe('projects', function (): void {
    it('accepts one project, as one inquiry', function (): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        expect(Inquiry::query()->count())->toBe(1);
    });

    it('refuses a second project', function (): void {
        postInquiry($this, array_fill(0, 2, inquiryProjectInput()))
            ->assertSessionHasErrors(['projects' => 'An inquiry can only have one project.']);

        expect(Inquiry::query()->count())->toBe(0);
    });

    it('refuses an inquiry with no projects', function (): void {
        postInquiry($this, [])->assertSessionHasErrors('projects');
    });
});

describe('stone rows', function (): void {
    /**
     * @return list<array{stoneId: string, quantity: int, slabSize: string, edge: string, finish: string}>
     */
    function distinctStoneRows(int $count): array
    {
        $rows = [];

        foreach (Stone::query()->active()->limit(3)->pluck('slug') as $slug) {
            foreach (EdgeProfile::query()->pluck('name') as $edge) {
                $rows[] = ['stoneId' => $slug, 'quantity' => 1, 'slabSize' => '120 x 60 cm', 'edge' => $edge, 'finish' => Finish::query()->value('name')];
            }
        }

        return array_slice($rows, 0, $count);
    }

    it('accepts 20 rows', function (): void {
        postInquiry($this, [inquiryProjectInput(['stones' => distinctStoneRows(20)])])->assertSessionHasNoErrors();
    });

    it('refuses 21 rows', function (): void {
        postInquiry($this, [inquiryProjectInput(['stones' => distinctStoneRows(21)])])->assertSessionHasErrors('projects.0.stones');
    });

    it('accepts the same stone with another finish', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $other = Finish::query()->where('name', '!=', $row['finish'])->value('name');

        postInquiry($this, [inquiryProjectInput(['stones' => [$row, [...$row, 'finish' => $other]]])])->assertSessionHasNoErrors();
    });

    it('saves the size of a slab', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => ' 90 x 45 cm ']]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->sole()->slab_size)->toBe('90 x 45 cm');
    });

    it('refuses a stone row with no slab size', function (string $slabSize): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => $slabSize]]])])
            ->assertSessionHasErrors(['projects.0.stones.0.slabSize' => 'Enter the slab size.']);

        $withoutSize = $row;
        unset($withoutSize['slabSize']);

        postInquiry($this, [inquiryProjectInput(['stones' => [$withoutSize]])])
            ->assertSessionHasErrors('projects.0.stones.0.slabSize');
    })->with(['empty' => [''], 'blank' => ['   ']]);

    it('refuses a slab size that is too long', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => str_repeat('x', 51)]]])])
            ->assertSessionHasErrors('projects.0.stones.0.slabSize');
    });

    it('keeps the same stone, edge and finish in two sizes as two rows, and adds up the same size', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $small = [...$row, 'quantity' => 2, 'slabSize' => '60 x 60 cm'];
        $large = [...$row, 'quantity' => 3, 'slabSize' => '120 x 60 cm'];

        postInquiry($this, [inquiryProjectInput(['stones' => [$small, $large, $small]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->orderBy('sort_order')->get(['slab_size', 'quantity'])->toArray())->toBe([
            ['slab_size' => '60 x 60 cm', 'quantity' => 4],
            ['slab_size' => '120 x 60 cm', 'quantity' => 3],
        ]);
    });

    it('adds a repeated stone, edge and finish to one row, up to the quantity limit', function (): void {
        $row = [...inquiryProjectInput()['stones'][0], 'quantity' => 3];

        postInquiry($this, [inquiryProjectInput(['stones' => [$row, $row]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->sole()->quantity)->toBe(6);

        $large = [...$row, 'quantity' => config('inquiry.limits.quantity_max')];

        postInquiry($this, [inquiryProjectInput(['stones' => [$large, $large]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->orderByDesc('id')->first()->stones()->sole()->quantity)->toBe(config('inquiry.limits.quantity_max'));
    });
});

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function twoStoneRows(): array
{
    $row = inquiryProjectInput()['stones'][0];
    $other = Stone::query()->active()->where('slug', '!=', $row['stoneId'])->orderBy('id')->value('slug');

    return [$row, [...$row, 'stoneId' => $other]];
}

describe('stones and services', function (): void {
    it('refuses a project with no stones', function (): void {
        postInquiry($this, [inquiryProjectInput(['stones' => []])])->assertSessionHasErrors('projects.0.stones');
    });

    it('does not ask where the stone will be used', function (): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();
    });

    it('saves the one service of the request, however many stones it has', function (): void {
        [$row] = twoStoneRows();

        postInquiry($this, [inquiryProjectInput([
            'stones' => [
                [...$row, 'stoneId' => offeredStoneSlug('wall-cladding')],
                [...$row, 'stoneId' => offeredStoneSlug('wall-cladding', 1)],
            ],
            'serviceId' => 'wall-cladding',
        ])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->service->slug)->toBe('wall-cladding')
            ->and($inquiry->service->serviceType->slug)->toBe('natural-stone-applications')
            ->and($inquiry->stones()->count())->toBe(2);
    });

    it('keeps a stone added again with another finish as two rows under the one service', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $other = Finish::query()->where('name', '!=', $row['finish'])->value('name');

        postInquiry($this, [inquiryProjectInput(['stones' => [$row, [...$row, 'finish' => $other]]])])
            ->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->count())->toBe(2);
    });

    it('refuses a service that is no longer offered', function (): void {
        Service::query()->where('slug', 'slab-supply-only')->update(['is_active' => false]);

        postInquiry($this, [inquiryProjectInput()])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);
    });

    it('refuses a service whose service type is no longer offered', function (): void {
        ServiceType::query()->where('slug', 'natural-stone-applications')->update(['is_active' => false]);

        postInquiry($this, [inquiryProjectInput()])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);

        expect(Inquiry::query()->count())->toBe(0);

        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'tables', 'stones' => [[...$row, 'stoneId' => offeredStoneSlug('tables')]]])])
            ->assertSessionHasNoErrors();
    });

    it('refuses a stone whose type is not linked to the service, on that stone\'s row', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'countertop',
            'stones' => [
                [...$row, 'stoneId' => offeredStoneSlug('countertop')],
                [...$row, 'stoneId' => unofferedStoneSlug('countertop')],
            ],
        ])])
            ->assertSessionHasErrors(['projects.0.stones.1.stoneId' => 'That stone is not offered for this service.'])
            ->assertSessionDoesntHaveErrors('projects.0.stones.0.stoneId');

        expect(Inquiry::query()->count())->toBe(0);
    });

    it('accepts a stone whose type is linked to the service', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $stone = Stone::query()->where('slug', offeredStoneSlug('countertop'))->firstOrFail();

        expect(Service::query()->where('slug', 'countertop')->firstOrFail()->stoneTypes->modelKeys())->toContain($stone->stone_type_id);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop', 'stones' => [[...$row, 'stoneId' => $stone->slug]]])])
            ->assertSessionHasNoErrors();
    });

    it('decides the fit by the primary type alone, not the collection filter types', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $stone = Stone::query()->where('slug', unofferedStoneSlug('countertop'))->firstOrFail();
        $stone->types()->syncWithoutDetaching(Service::query()->where('slug', 'countertop')->firstOrFail()->stoneTypes->modelKeys());

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop', 'stones' => [[...$row, 'stoneId' => $stone->slug]]])])
            ->assertSessionHasErrors('projects.0.stones.0.stoneId');
    });

    it('refuses a service made from no stone type, which is not offered', function (): void {
        Service::query()->where('slug', 'storage')->firstOrFail()->stoneTypes()->detach();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'storage', 'stoneAdvice' => true, 'stones' => []])])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);
    });
});

describe('the delivery method', function (): void {
    it('stores a delivery with its location', function (): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method)->toBe(DeliveryMethod::Delivery)
            ->and($inquiry->location)->toBe('Makati');
    });

    it('needs a location for a delivery', function (): void {
        postInquiry($this, [inquiryProjectInput(['location' => ''])])
            ->assertSessionHasErrors(['projects.0.location' => 'Choose where the site is.']);
    });

    it('needs no location for a pickup, and keeps none it was sent', function (array $site): void {
        postInquiry($this, [inquiryProjectInput(['deliveryMethod' => 'pickup', ...$site])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method)->toBe(DeliveryMethod::Pickup)
            ->and($inquiry->location)->toBeNull();
    })->with([
        'none sent' => [['location' => '']],
        'one left over' => [['location' => 'Makati']],
    ]);

    it('keeps the landmark of a pickup', function (): void {
        postInquiry($this, [inquiryProjectInput([
            'deliveryMethod' => 'pickup',
            'location' => '',
            'landmark' => 'Near the church',
        ])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->landmark)->toBe('Near the church');
    });

    it('does not let the database hold a delivery with no location or a pickup with one', function (string $method, ?string $location): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        expect(fn () => DB::table('inquiries')->update(['delivery_method' => $method, 'location' => $location, 'landmark' => null]))
            ->toThrow(QueryException::class);
    })->with([
        'delivery with no location' => ['delivery', null],
        'pickup with a location' => ['pickup', 'Makati'],
    ]);
});
