<?php

use App\Data\PhAddress;
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
    return $test->actingAs($test->client)->post(route('quote.inquire.store'), clientInquiryInput($projects));
}

/**
 * Each rule has a value that must be accepted and a value that must be refused,
 * both given as a change to a valid project plus the field the error lands on.
 */
dataset('accepted values', [
    'name at 100 characters' => [fn (array $p): array => [...$p, 'name' => str_repeat('a', 100)]],
    'notes at 1000 characters' => [fn (array $p): array => [...$p, 'notes' => str_repeat('a', 1000)]],
    'area above zero' => [fn (array $p): array => inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => ['area_sqm' => '0.01', 'fascia' => '0']])],
    'deadline today' => [fn (array $p): array => [...$p, 'neededBy' => now('Asia/Manila')->toDateString()]],
    'quantity 1' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 1]]]],
    'quantity 9999' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 9999]]]],
    'a brand instead of No preference' => [fn (array $p): array => [...$p, 'brandNoPreference' => null, 'brands' => ['regray-stone']]],
    'a pickup' => [fn (array $p): array => [...$p, 'deliveryMethod' => 'pickup', 'address' => null]],
    'ten files' => [fn (array $p): array => [...$p, 'attachments' => array_map(
        fn (int $n): UploadedFile => UploadedFile::fake()->create("plan-{$n}.pdf", 10, 'application/pdf'),
        range(1, 10),
    )]],
    'a file of exactly 10 MB' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->create('big.pdf', 10240, 'application/pdf')]]],
    'a png' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.png')]]],
    'a jpeg' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.jpeg')]]],
    'a jpg' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.jpg')]]],
]);

dataset('refused values', [
    'name over 100 characters' => [fn (array $p): array => [...$p, 'name' => str_repeat('a', 101)], 'projects.0.name'],
    'no name' => [fn (array $p): array => [...$p, 'name' => ''], 'projects.0.name'],
    'notes over 1000 characters' => [fn (array $p): array => [...$p, 'notes' => str_repeat('a', 1001)], 'projects.0.notes'],
    'area of zero' => [fn (array $p): array => inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => ['area_sqm' => '0']]), 'projects.0.answers.area_sqm'],
    'negative area' => [fn (array $p): array => inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => ['area_sqm' => '-3']]), 'projects.0.answers.area_sqm'],
    'no area where the service asks it' => [fn (array $p): array => inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => []]), 'projects.0.answers.area_sqm'],
    'deadline in the past' => [fn (array $p): array => [...$p, 'neededBy' => now()->subDay()->toDateString()], 'projects.0.neededBy'],
    'no deadline date' => [fn (array $p): array => [...$p, 'neededBy' => ''], 'projects.0.neededBy'],
    'quantity of 0' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 0]]], 'projects.0.stones.0.quantity'],
    'quantity of 10000' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 10000]]], 'projects.0.stones.0.quantity'],
    'a fractional quantity' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'quantity' => 1.5]]], 'projects.0.stones.0.quantity'],
    'a saved address not in the book' => [fn (array $p): array => [...$p, 'addressId' => '999999'], 'projects.0.addressId'],
    'brands and No preference' => [fn (array $p): array => [...$p, 'brands' => ['regray-stone']], 'projects.0.brands'],
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
    // Previewable if one is ever stored, but not taken on upload.
    'a webp' => [fn (array $p): array => [...$p, 'attachments' => [UploadedFile::fake()->image('site.webp')]], 'projects.0.attachments.0'],
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
     * @return list<array{stoneId: string, quantity: int, slabSize: string, thickness: string, edge: string, finish: string}>
     */
    function distinctStoneRows(int $count): array
    {
        $rows = [];

        foreach (Stone::query()->offeredFor(inquiryService('slab-supply-and-installation'))->orderBy('id')->limit(3)->pluck('slug') as $slug) {
            foreach (EdgeProfile::query()->pluck('name') as $edge) {
                $rows[] = ['stoneId' => $slug, 'quantity' => 1, 'slabSize' => '160 x 320 cm', 'thickness' => '20 mm', 'edge' => $edge, 'finish' => Finish::query()->value('name')];
            }
        }

        return array_slice($rows, 0, $count);
    }

    it('accepts 20 rows', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'slab-supply-and-installation', 'stones' => distinctStoneRows(20)])])->assertSessionHasNoErrors();
    });

    it('refuses 21 rows', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'slab-supply-and-installation', 'stones' => distinctStoneRows(21)])])->assertSessionHasErrors('projects.0.stones');
    });

    it('takes a Slab Supply Only row without an edge profile, as slabs supplied alone may come uncut', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        unset($row['edge']);

        postInquiry($this, [inquiryProjectInput(['stones' => [$row]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->sole()->edge_profile_id)->toBeNull();

        $installed = inquiryProjectInput(['serviceId' => 'slab-supply-and-installation'])['stones'][0];
        unset($installed['edge']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'slab-supply-and-installation', 'stones' => [$installed]])])
            ->assertSessionHasErrors('projects.0.stones.0.edge');
    });

    it('accepts the same stone with another finish', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $other = Finish::query()->where('name', '!=', $row['finish'])->value('name');

        postInquiry($this, [inquiryProjectInput(['stones' => [$row, [...$row, 'finish' => $other]]])])->assertSessionHasNoErrors();
    });

    it('refuses an archived finish or edge profile, still switched on, as no longer offered', function (string $answer, string $model): void {
        // A service that asks for both.
        $row = inquiryProjectInput(['serviceId' => 'slab-supply-and-installation'])['stones'][0];
        // Archived from its Products page while a client had the Inquire page open.
        $model::query()->where('name', $row[$answer])->firstOrFail()->delete();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'slab-supply-and-installation', 'stones' => [$row]])])
            ->assertSessionHasErrors("projects.0.stones.0.{$answer}");
        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        'finish' => ['finish', Finish::class],
        'edge profile' => ['edge', EdgeProfile::class],
    ]);

    it('saves a slab size and thickness as numbers and a unit, read whatever their case and spacing', function (string $size, string $thickness, array $stored, array $labels): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => $size, 'thickness' => $thickness]]])])->assertSessionHasNoErrors();

        $stone = Inquiry::query()->sole()->stones()->sole();

        expect([$stone->slab_width, $stone->slab_length, $stone->slab_unit?->value, $stone->thickness, $stone->thickness_unit?->value])->toBe($stored)
            ->and([$stone->slabSizeLabel(), $stone->thicknessLabel()])->toBe($labels);
    })->with([
        'centimetres and millimetres' => [' 90 x 45 cm ', '20 mm', ['90.00', '45.00', 'cm', '20.00', 'mm'], ['90 x 45 cm', '20 mm']],
        'metres and centimetres' => ['1.6 x 3.2 m', '2 cm', ['1.60', '3.20', 'm', '2.00', 'cm'], ['1.6 x 3.2 m', '2 cm']],
        'inches' => ['63X126IN', '0.75in', ['63.00', '126.00', 'in', '0.75', 'in'], ['63 x 126 in', '0.75 in']],
        'feet' => ['5 x 10 ft', '1.5 IN', ['5.00', '10.00', 'ft', '1.50', 'in'], ['5 x 10 ft', '1.5 in']],
    ]);

    it('takes a slab size or thickness the stone is not listed in, since Other takes any', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => '151 x 299 cm', 'thickness' => '27 mm']]])])->assertSessionHasNoErrors();
    });

    it('refuses a stone row with no slab size or thickness', function (string $blank): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => $blank, 'thickness' => $blank]]])])
            ->assertSessionHasErrors([
                'projects.0.stones.0.slabSize' => 'Enter the slab size.',
                'projects.0.stones.0.thickness' => 'Enter the thickness.',
            ]);

        $withoutEither = $row;
        unset($withoutEither['slabSize'], $withoutEither['thickness']);

        postInquiry($this, [inquiryProjectInput(['stones' => [$withoutEither]])])
            ->assertSessionHasErrors(['projects.0.stones.0.slabSize', 'projects.0.stones.0.thickness']);
    })->with(['empty' => [''], 'blank' => ['   ']]);

    it('refuses a thickness that is not a measurement', function (string $thickness, string $message): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'thickness' => $thickness]]])])
            ->assertSessionHasErrors(['projects.0.stones.0.thickness' => $message]);

        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        '0' => ['0 mm', 'Enter a thickness greater than 0.'],
        'no unit' => ['20', 'Enter the thickness as a number and a unit, like 20 mm.'],
        'an unknown unit' => ['20 ft', 'Enter the thickness as a number and a unit, like 20 mm.'],
        'not a number' => ['abc', 'Enter the thickness as a number and a unit, like 20 mm.'],
        'too many decimals' => ['20.555 mm', 'Enter the thickness as a number and a unit, like 20 mm.'],
        'too large' => ['10000 mm', 'Enter a smaller thickness.'],
    ]);

    it('refuses a slab size that is not a measurement', function (string $size, string $message): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => $size]]])])
            ->assertSessionHasErrors(['projects.0.stones.0.slabSize' => $message]);

        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        'no unit' => ['160x320', 'Enter the slab size as width x length and a unit, like 160 x 320 cm.'],
        'an unknown unit' => ['160 x 320 yd', 'Enter the slab size as width x length and a unit, like 160 x 320 cm.'],
        'not a number' => ['abc', 'Enter the slab size as width x length and a unit, like 160 x 320 cm.'],
        'too many decimals' => ['160.555 x 320 cm', 'Enter the slab size as width x length and a unit, like 160 x 320 cm.'],
        'a side of 0' => ['0 x 320 cm', 'Enter a slab size greater than 0.'],
        'too large' => ['1000000 x 320 cm', 'Enter a smaller slab size.'],
    ]);

    it('asks a thickness of both slab services only', function (string $service, bool $asks): void {
        $row = inquiryStoneRow($service);

        expect(array_key_exists('thickness', $row))->toBe($asks);

        if ($asks) {
            unset($row['thickness']);

            postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'stones' => [$row]])])
                ->assertSessionHasErrors(['projects.0.stones.0.thickness' => 'Enter the thickness.']);

            return;
        }

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'stones' => [[...$row, 'thickness' => '20 mm']]])])
            ->assertSessionHasErrors(['projects.0.stones.0.thickness' => 'This service does not ask for the thickness.']);
    })->with([
        ['slab-supply-only', true],
        ['slab-supply-and-installation', true],
        ['countertop-backsplash', false],
        ['dining-tables', false],
    ]);

    it('keeps the same stone, edge and finish in two sizes or thicknesses as two rows, and adds up the same one however it is written', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        $small = [...$row, 'quantity' => 2, 'slabSize' => '60 x 60 cm'];
        $large = [...$row, 'quantity' => 3, 'slabSize' => '160 x 320 cm'];
        $thicker = [...$large, 'quantity' => 1, 'thickness' => '30 mm'];

        postInquiry($this, [inquiryProjectInput(['stones' => [$small, $large, [...$small, 'slabSize' => '60X60 CM'], $thicker]])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->stones()->orderBy('sort_order')->get()->map(fn ($stone): array => [$stone->slabSizeLabel(), $stone->thicknessLabel(), $stone->quantity])->all())->toBe([
            ['60 x 60 cm', '20 mm', 4],
            ['160 x 320 cm', '20 mm', 3],
            ['160 x 320 cm', '30 mm', 1],
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
        postInquiry($this, [inquiryProjectInput([
            'stones' => [
                inquiryStoneRow('wall-cladding', ['stoneId' => offeredStoneSlug('wall-cladding')]),
                inquiryStoneRow('wall-cladding', ['stoneId' => offeredStoneSlug('wall-cladding', 1)]),
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

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables'])])
            ->assertSessionHasNoErrors();
    });

    it('refuses a stone whose type is not linked to the service, on that stone\'s row', function (): void {
        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'countertop-backsplash',
            'stones' => [
                inquiryStoneRow('countertop-backsplash', ['stoneId' => offeredStoneSlug('countertop-backsplash')]),
                inquiryStoneRow('countertop-backsplash', ['stoneId' => unofferedStoneSlug('countertop-backsplash')]),
            ],
        ])])
            ->assertSessionHasErrors(['projects.0.stones.1.stoneId' => 'That stone is not offered for this service.'])
            ->assertSessionDoesntHaveErrors('projects.0.stones.0.stoneId');

        expect(Inquiry::query()->count())->toBe(0);
    });

    it('accepts a stone whose type is linked to the service', function (): void {
        $stone = Stone::query()->where('slug', offeredStoneSlug('countertop-backsplash'))->firstOrFail();

        expect(Service::query()->where('slug', 'countertop-backsplash')->firstOrFail()->stoneTypes->modelKeys())->toContain($stone->stone_type_id);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'stones' => [inquiryStoneRow('countertop-backsplash', ['stoneId' => $stone->slug])]])])
            ->assertSessionHasNoErrors();
    });

    it('decides the fit by the primary type alone, not the collection filter types', function (): void {
        $stone = Stone::query()->where('slug', unofferedStoneSlug('countertop-backsplash'))->firstOrFail();
        $stone->types()->syncWithoutDetaching(Service::query()->where('slug', 'countertop-backsplash')->firstOrFail()->stoneTypes->modelKeys());

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'stones' => [inquiryStoneRow('countertop-backsplash', ['stoneId' => $stone->slug])]])])
            ->assertSessionHasErrors('projects.0.stones.0.stoneId');
    });

    it('refuses a service made from no stone type, which is not offered', function (): void {
        Service::query()->where('slug', 'pedestal')->firstOrFail()->stoneTypes()->detach();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'pedestal', 'stoneAdvice' => true, 'stones' => []])])
            ->assertSessionHasErrors(['projects.0.serviceId' => 'That choice is no longer available.']);
    });
});

describe('the delivery method', function (): void {
    it('stores a delivery with a copy of its saved address', function (): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->delivery_method)->toBe(DeliveryMethod::Delivery)
            ->and($inquiry->address_city_name)->toBe('City of Makati')
            ->and($inquiry->address_postal_code)->toBe('1209');
    });

    it('needs an address for a delivery', function (): void {
        postInquiry($this, [inquiryProjectInput(['address' => null])])
            ->assertSessionHasErrors(['projects.0.addressId' => 'Choose the delivery address.']);
    });

    it('needs no address for a pickup, and refuses one sent with it', function (): void {
        postInquiry($this, [inquiryProjectInput(['deliveryMethod' => 'pickup', 'address' => null])])->assertSessionHasNoErrors();

        expect(Inquiry::query()->sole()->address_city_code)->toBeNull();

        postInquiry($this, [inquiryProjectInput(['deliveryMethod' => 'pickup', 'addressId' => (string) defaultTestAddress($this->client)->id])])
            ->assertSessionHasErrors(['projects.0.address' => 'A pickup needs no address.']);
    });

    it('does not let the database hold a delivery with no address or a pickup with one', function (string $method, bool $keepAddress): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        $change = ['delivery_method' => $method, ...($keepAddress ? [] : PhAddress::emptyColumns(Inquiry::ADDRESS_PREFIX))];

        expect(fn () => DB::table('inquiries')->update($change))->toThrow(QueryException::class);
    })->with([
        'delivery with no address' => ['delivery', false],
        'pickup with an address' => ['pickup', true],
    ]);

    it('does not let the database hold half an address', function (): void {
        postInquiry($this, [inquiryProjectInput()])->assertSessionHasNoErrors();

        expect(fn () => DB::table('inquiries')->update(['address_barangay_code' => null]))->toThrow(QueryException::class);
    });
});

describe('the questions a service asks', function (): void {
    it('asks wall cladding for an area and a finish on each stone, and no pieces, edge or slab size', function (): void {
        $row = inquiryStoneRow('wall-cladding');

        expect($row)->toHaveKey('finish')->not->toHaveKeys(['quantity', 'edge', 'slabSize', 'thickness']);

        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'wall-cladding',
            'stones' => [[...$row, 'quantity' => 3, 'edge' => EdgeProfile::query()->value('name'), 'finish' => '']],
            'answers' => [],
        ])])->assertSessionHasErrors([
            'projects.0.stones.0.quantity' => 'This service does not ask how many pieces.',
            'projects.0.stones.0.edge' => 'This service does not ask for the edge profile.',
            'projects.0.stones.0.finish' => 'Choose the finish.',
            'projects.0.answers.area_sqm' => 'Enter the project area (sqm).',
        ]);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'wall-cladding', 'answers' => ['area_sqm' => '18.5']])])
            ->assertSessionHasNoErrors();

        $stone = Inquiry::query()->sole()->stones()->sole();

        expect($stone->finish_id)->not->toBeNull()
            ->and($stone->quantity)->toBeNull()
            ->and($stone->edge_profile_id)->toBeNull()
            ->and($stone->slab_width)->toBeNull()
            ->and($stone->thickness)->toBeNull();
    });

    it('stores the project answers one row per question, in the column of its answer type', function (): void {
        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'kitchen-islands',
            'answers' => ['area_sqm' => '6.25', 'waterfall_effect' => '1'],
        ])])->assertSessionHasNoErrors();

        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'dining-tables',
            'answers' => ['area_sqm' => '3.75', 'seaters' => '7', 'table_base' => 'steel'],
        ])])->assertSessionHasNoErrors();

        $answers = DB::table('inquiry_detail_answers')
            ->join('detail_fields', 'detail_fields.id', '=', 'inquiry_detail_answers.detail_field_id')
            ->leftJoin('detail_field_options', 'detail_field_options.id', '=', 'inquiry_detail_answers.option_id')
            ->orderBy('inquiry_detail_answers.inquiry_id')
            ->orderBy('detail_fields.sort_order')
            ->get(['detail_fields.slug', 'text_value', 'number_value', 'detail_field_options.slug as option', 'boolean_value'])
            ->map(fn (object $row): array => (array) $row)
            ->all();

        expect($answers)->toBe([
            ['slug' => 'area_sqm', 'text_value' => null, 'number_value' => '6.25', 'option' => null, 'boolean_value' => null],
            ['slug' => 'waterfall_effect', 'text_value' => null, 'number_value' => null, 'option' => null, 'boolean_value' => true],
            ['slug' => 'area_sqm', 'text_value' => null, 'number_value' => '3.75', 'option' => null, 'boolean_value' => null],
            ['slug' => 'seaters', 'text_value' => null, 'number_value' => '7.00', 'option' => null, 'boolean_value' => null],
            ['slug' => 'table_base', 'text_value' => null, 'number_value' => null, 'option' => 'steel', 'boolean_value' => null],
        ]);
    });

    it('takes a free entry where the question allows one, checked only by its type', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'pedestal', 'answers' => ['size' => '75 x 75 cm']])])
            ->assertSessionHasNoErrors();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'answers' => ['seaters' => 'many', 'table_base' => 'wood']])])
            ->assertSessionHasErrors(['projects.0.answers.seaters' => 'Enter a number greater than 0.']);

        expect(Inquiry::query()->sole()->detailAnswers()->whereNotNull('text_value')->sole()->text_value)->toBe('75 x 75 cm');
    });

    it('refuses an answer that is not one of a closed list', function (array $answers, string $field, string $message): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => $answers['service'], 'answers' => $answers['answers']])])
            ->assertSessionHasErrors(["projects.0.answers.{$field}" => $message]);

        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        'a table base not listed' => [['service' => 'dining-tables', 'answers' => ['area_sqm' => '3', 'seaters' => '6', 'table_base' => 'marble']], 'table_base', 'Choose the table base from the list.'],
        'a fascia that is not yes or no' => [['service' => 'countertop-backsplash', 'answers' => ['area_sqm' => '4', 'fascia' => 'maybe']], 'fascia', 'Choose yes or no.'],
        'a waterfall effect that is not yes or no' => [['service' => 'kitchen-islands', 'answers' => ['area_sqm' => '4', 'waterfall_effect' => 'maybe']], 'waterfall_effect', 'Choose yes or no.'],
    ]);

    it('asks Countertop and Backsplash for a fascia, yes or no, and stores it as a boolean', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => ['area_sqm' => '4']])])
            ->assertSessionHasErrors(['projects.0.answers.fascia' => 'Choose the fascia.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'countertop-backsplash', 'answers' => ['area_sqm' => '4', 'fascia' => '1']])])
            ->assertSessionHasNoErrors();

        $fascia = Inquiry::query()->sole()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'fascia'))->sole();

        expect($fascia->boolean_value)->toBeTrue()
            ->and($fascia->displayValue())->toBe('Yes');
    });

    it('asks the larger furniture for the project area, and stores it as a number', function (string $service): void {
        $answers = inquiryAnswers($service);
        unset($answers['area_sqm']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => $answers])])
            ->assertSessionHasErrors(['projects.0.answers.area_sqm' => 'Enter the project area (sqm).']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => [...$answers, 'area_sqm' => '3.75']])])
            ->assertSessionHasNoErrors();

        $area = Inquiry::query()->sole()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'area_sqm'))->sole();

        expect($area->number_value)->toBe('3.75')
            ->and($area->displayValue())->toBe('3.75');
    })->with(['dining-tables', 'other-customized-furniture']);

    it('asks a small piece for its size alone, not its area in square metres', function (string $service): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => [...inquiryAnswers($service), 'area_sqm' => '0.5']])])
            ->assertSessionHasErrors(['projects.0.answers' => 'This service does not ask that.']);

        expect(Inquiry::query()->count())->toBe(0);
    })->with(['center-tables', 'side-tables', 'pedestal']);

    it('refuses an answer to a question the service does not ask', function (): void {
        postInquiry($this, [inquiryProjectInput(['answers' => ['size' => '60 x 60 cm']])])
            ->assertSessionHasErrors(['projects.0.answers' => 'This service does not ask that.']);
    });

    it('takes "Same as stone" only for a project with a stone row', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'stones' => [], 'stoneAdvice' => true, 'answers' => ['area_sqm' => '3', 'seaters' => '6', 'table_base' => 'same-as-stone']])])
            ->assertSessionHasErrors(['projects.0.answers.table_base' => 'Same as stone needs a stone. Choose another table base.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'answers' => ['area_sqm' => '3', 'seaters' => '6', 'table_base' => 'same-as-stone']])])
            ->assertSessionHasNoErrors();
    });

    it('sends Installation Only with no stones, and refuses stones or Help me choose for it', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'installation-only', 'stones' => [inquiryStoneRow()]])])
            ->assertSessionHasErrors(['projects.0.stones' => 'This service needs no stones.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'installation-only', 'stoneAdvice' => true])])
            ->assertSessionHasErrors(['projects.0.stoneAdvice' => 'This service needs no stones.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'installation-only'])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();

        expect($inquiry->service->slug)->toBe('installation-only')
            ->and($inquiry->stones()->count())->toBe(0)
            ->and($inquiry->stone_advice_requested)->toBeFalse()
            ->and($inquiry->detailAnswers()->count())->toBe(0);
    });
});
