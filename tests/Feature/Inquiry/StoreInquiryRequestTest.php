<?php

use App\Data\PhAddress;
use App\Enums\DeliveryMethod;
use App\Models\Choice;
use App\Models\DetailField;
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

    it('takes up to five projects, each its own inquiry', function (): void {
        postInquiry($this, [
            inquiryProjectInput(['name' => 'Supply']),
            inquiryProjectInput(['name' => 'Floors', 'serviceId' => 'flooring']),
            inquiryProjectInput(['name' => 'Fit-out', 'serviceId' => 'flooring', 'underService' => 'installation-only']),
            inquiryProjectInput(['name' => 'Counters', 'serviceId' => 'countertop-backsplash']),
            inquiryProjectInput(['name' => 'Stairs', 'serviceId' => 'staircase']),
        ])->assertSessionHasNoErrors();

        expect(Inquiry::query()->orderBy('id')->pluck('name')->all())->toBe(['Supply', 'Floors', 'Fit-out', 'Counters', 'Stairs']);
    });

    it('takes the same brand on two products, and refuses it twice in one', function (): void {
        $regray = ['brandNoPreference' => null, 'brands' => ['regray-stone']];

        postInquiry($this, [
            inquiryProjectInput(['name' => 'Supply', ...$regray]),
            inquiryProjectInput(['name' => 'Floors', 'serviceId' => 'flooring', ...$regray]),
        ])->assertSessionHasNoErrors();

        expect(Inquiry::query()->count())->toBe(2);

        postInquiry($this, [inquiryProjectInput(['brandNoPreference' => null, 'brands' => ['regray-stone', 'regray-stone']])])
            ->assertSessionHasErrors(['projects.0.brands' => 'That brand is already chosen.']);

        expect(Inquiry::query()->count())->toBe(2);
    });

    it('refuses a sixth project', function (): void {
        postInquiry($this, array_fill(0, 6, inquiryProjectInput()))
            ->assertSessionHasErrors(['projects' => 'Send up to 5 projects at a time.']);

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

        // Offered for Countertop and Backsplash, the application the project gets.
        foreach (Stone::query()->offeredFor(inquiryService('countertop-backsplash'))->orderBy('id')->limit(3)->pluck('slug') as $slug) {
            foreach (EdgeProfile::query()->pluck('name') as $edge) {
                $rows[] = ['stoneId' => $slug, 'quantity' => 1, 'slabSize' => '160 x 320 cm', 'thickness' => inquiryThickness($slug), 'edge' => $edge, 'finish' => Finish::query()->value('name')];
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
        stoneComesIn($row['stoneId'], [$labels[1]]);

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

    it('takes a slab size the stone is not listed in, since Other takes any, but only a listed thickness', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'slabSize' => '151 x 299 cm']]])])->assertSessionHasNoErrors();
        postInquiry($this, [inquiryProjectInput(['stones' => [[...$row, 'thickness' => '27 mm']]])])
            ->assertSessionHasErrors(['projects.0.stones.0.thickness' => 'Choose the thickness from the list.']);
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
        // Asked under Slab Supply and Installation, which asks it.
        ['countertop-backsplash', true],
        ['dining-tables', false],
    ]);

    it('keeps the same stone, edge and finish in two sizes or thicknesses as two rows, and adds up the same one however it is written', function (): void {
        $row = inquiryProjectInput()['stones'][0];
        stoneComesIn($row['stoneId'], ['20 mm', '30 mm']);
        $row = [...$row, 'thickness' => '20 mm'];
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

        expect($inquiry->service->slug)->toBe('slab-supply-and-installation')
            ->and($inquiry->application->slug)->toBe('wall-cladding')
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
        Service::query()->where('slug', 'other-customized-furniture')->firstOrFail()->stoneTypes()->detach();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'other-customized-furniture', 'stoneAdvice' => true, 'stones' => []])])
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
    it("asks wall cladding's area once for the project, and each stone what Slab Supply and Installation asks", function (): void {
        $row = inquiryStoneRow('wall-cladding');

        expect($row)->toHaveKeys(['quantity', 'slabSize', 'thickness', 'edge', 'finish']);

        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'wall-cladding',
            'stones' => [[...$row, 'finish' => '']],
            'answers' => [],
        ])])->assertSessionHasErrors([
            'projects.0.stones.0.finish' => 'Choose the finish.',
            'projects.0.answers.area_sqm' => 'Enter the project area (sqm).',
        ]);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'wall-cladding', 'answers' => ['area_sqm' => '18.5']])])
            ->assertSessionHasNoErrors();

        $stone = Inquiry::query()->sole()->stones()->sole();

        expect($stone->finish_id)->not->toBeNull()
            ->and($stone->quantity)->toBe(4)
            ->and($stone->edge_profile_id)->not->toBeNull()
            ->and($stone->slab_width)->not->toBeNull();
    });

    it('stores the project answers one row per question, in the column of its answer type', function (): void {
        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'kitchen-islands',
            'answers' => ['area_sqm' => '6.25', 'waterfall_effect' => '1'],
        ])])->assertSessionHasNoErrors();

        postInquiry($this, [inquiryProjectInput([
            'serviceId' => 'dining-tables',
            'answers' => ['table_shape' => 'round', 'seaters' => '7', 'table_base_design_dining' => 'clover-v1'],
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
            ['slug' => 'seaters', 'text_value' => null, 'number_value' => '7.00', 'option' => null, 'boolean_value' => null],
            ['slug' => 'table_shape', 'text_value' => null, 'number_value' => null, 'option' => 'round', 'boolean_value' => null],
            ['slug' => 'table_base_design_dining', 'text_value' => null, 'number_value' => null, 'option' => 'clover-v1', 'boolean_value' => null],
        ]);
    });

    it('takes a free entry where the question allows one, checked only by its type', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'other-customized-furniture', 'answers' => ['size' => '75 x 75 cm']])])
            ->assertSessionHasNoErrors();

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'answers' => ['seaters' => 'many']])])
            ->assertSessionHasErrors(['projects.0.answers.seaters' => 'Enter a number greater than 0.']);

        expect(Inquiry::query()->sole()->detailAnswers()->whereNotNull('text_value')->sole()->text_value)->toBe('75 x 75 cm');
    });

    it('refuses an answer that is not one of a closed list', function (array $answers, string $field, string $message): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => $answers['service'], 'answers' => $answers['answers']])])
            ->assertSessionHasErrors(["projects.0.answers.{$field}" => $message]);

        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        'a table shape not listed' => [['service' => 'dining-tables', 'answers' => ['table_shape' => 'hexagon', 'seaters' => '6']], 'table_shape', 'Choose the table shape from the list.'],
        // C-legs are a side table's base, not a dining table's.
        'a base design of another table' => [['service' => 'dining-tables', 'answers' => ['table_shape' => 'round', 'seaters' => '6', 'table_base_design_dining' => 'c-legs-v1']], 'table_base_design_dining', 'Choose the dining table base design from the list.'],
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

    it('asks a surface for the project area, and stores it as a number', function (string $service): void {
        $answers = inquiryAnswers($service);
        unset($answers['area_sqm']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => $answers])])
            ->assertSessionHasErrors(['projects.0.answers.area_sqm' => 'Enter the project area (sqm).']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => [...$answers, 'area_sqm' => '3.75']])])
            ->assertSessionHasNoErrors();

        $area = Inquiry::query()->sole()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'area_sqm'))->sole();

        expect($area->number_value)->toBe('3.75')
            ->and($area->displayValue())->toBe('3.75');
    })->with(['wall-cladding', 'kitchen-islands']);

    it('asks no furniture its area in square metres', function (string $service): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => [...inquiryAnswers($service), 'area_sqm' => '0.5']])])
            ->assertSessionHasErrors(['projects.0.answers' => 'This service does not ask that.']);

        expect(Inquiry::query()->count())->toBe(0);
    })->with(['center-tables', 'side-tables', 'dining-tables', 'other-customized-furniture']);

    it('refuses an answer to a question the service does not ask', function (): void {
        postInquiry($this, [inquiryProjectInput(['answers' => ['size' => '60 x 60 cm']])])
            ->assertSessionHasErrors(['projects.0.answers' => 'This service does not ask that.']);
    });

    it('takes "Same as stone" only for a project with a stone row', function (): void {
        // No seeded question lists it; one the admin gives it, as Table base once did.
        $design = DetailField::query()->where('slug', 'table_base_design_dining')->sole();
        $design->writeChoices([...$design->options()->whereNull('archived_at')->whereNotNull('choice_id')->pluck('choice_id')->all(), Choice::query()->where('slug', 'same-as-stone')->value('id')]);
        $answers = [...inquiryAnswers('dining-tables'), 'table_base_design_dining' => 'same-as-stone'];

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'stones' => [], 'stoneAdvice' => true, 'answers' => $answers])])
            ->assertSessionHasErrors(['projects.0.answers.table_base_design_dining' => 'Same as stone needs a stone. Choose another dining table base design.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'answers' => $answers])])
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
            ->and($inquiry->application->slug)->toBe('countertop-backsplash')
            ->and($inquiry->stones()->count())->toBe(0)
            ->and($inquiry->stone_advice_requested)->toBeFalse()
            // Its application's questions: the area and the fascia.
            ->and($inquiry->detailAnswers()->count())->toBe(2);
    });
});

describe('the table questions asked with pictures', function (): void {
    it('asks a center, side or dining table its shape and its own table\'s base design', function (string $service, string $base, string $name): void {
        $answers = inquiryAnswers($service);
        unset($answers['table_shape'], $answers[$base]);

        postInquiry($this, [inquiryProjectInput(['serviceId' => $service, 'answers' => $answers])])
            ->assertSessionHasErrors([
                'projects.0.answers.table_shape' => 'Choose the table shape.',
                "projects.0.answers.{$base}" => "Choose the {$name}.",
            ]);
    })->with([
        ['center-tables', 'table_base_design_center', 'center table base design'],
        ['side-tables', 'table_base_design_side', 'side table base design'],
        ['dining-tables', 'table_base_design_dining', 'dining table base design'],
    ]);

    it('wants a custom choice described, and keeps the description with it', function (): void {
        $answers = [...inquiryAnswers('center-tables'), 'table_shape' => 'custom-shape', 'size' => 'About two metres across'];

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'center-tables', 'answers' => $answers])])
            ->assertSessionHasErrors(['projects.0.answers.table_shape' => 'Describe your custom shape.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'center-tables', 'answers' => $answers, 'customAnswers' => ['table_shape' => str_repeat('a', 256)]])])
            ->assertSessionHasErrors(['projects.0.answers.table_shape' => 'Keep the description to 255 characters or fewer.']);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'center-tables', 'answers' => $answers, 'customAnswers' => ['table_shape' => ' An L-shaped top ']])])
            ->assertSessionHasNoErrors();

        $shape = Inquiry::query()->sole()->detailAnswers()->with(['detailField', 'option'])
            ->whereHas('detailField', fn ($field) => $field->where('slug', 'table_shape'))->sole();

        expect($shape->option?->slug)->toBe('custom-shape')
            ->and($shape->text_value)->toBe('An L-shaped top')
            ->and($shape->displayValue())->toBe('Custom Shape: An L-shaped top');
    });

    it('asks wall cladding whether it is bookmatched, and wants a custom layout described', function (): void {
        $wall = fn (array $answers, array $custom = []): array => inquiryProjectInput([
            'serviceId' => 'wall-cladding',
            'answers' => ['area_sqm' => '12', ...$answers],
            'customAnswers' => $custom,
        ]);

        postInquiry($this, [$wall(['bookmatch' => 'diagonal'])])
            ->assertSessionHasErrors(['projects.0.answers.bookmatch' => 'Choose the bookmatch from the list.']);

        postInquiry($this, [$wall(['bookmatch' => 'bookmatch-custom'])])
            ->assertSessionHasErrors(['projects.0.answers.bookmatch' => 'Describe your custom layout.']);

        // Not required: none picked is none wanted.
        postInquiry($this, [$wall([])])->assertSessionHasNoErrors();
        postInquiry($this, [$wall(['bookmatch' => 'bookmatch-custom'], ['bookmatch' => 'Veins meeting at the corner'])])
            ->assertSessionHasNoErrors();

        $bookmatch = Inquiry::query()->latest('id')->first()->detailAnswers()->with(['detailField', 'option'])
            ->whereHas('detailField', fn ($field) => $field->where('slug', 'bookmatch'))->sole();

        expect($bookmatch->displayValue())->toBe('Custom layout: Veins meeting at the corner')
            ->and(Inquiry::query()->oldest('id')->first()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'bookmatch'))->exists())->toBeFalse();
    });

    it('keeps no description for a choice that is not custom', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'center-tables', 'answers' => inquiryAnswers('center-tables'), 'customAnswers' => ['table_shape' => 'Ignored']])])
            ->assertSessionHasNoErrors();

        $shape = Inquiry::query()->sole()->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'table_shape'))->sole();

        expect($shape->text_value)->toBeNull();
    });

    it('asks a round table its diameter, a rectangular one width x length, and an organic one in the client\'s words', function (string $shape, string $size, ?string $problem): void {
        $response = postInquiry($this, [inquiryProjectInput(['serviceId' => 'side-tables', 'answers' => [...inquiryAnswers('side-tables'), 'table_shape' => $shape, 'size' => $size]])]);

        $problem === null
            ? $response->assertSessionHasNoErrors()
            : $response->assertSessionHasErrors(['projects.0.answers.size' => $problem]);
    })->with([
        'a round table by its diameter' => ['round', 'Ø 60 cm', null],
        'a round table by width x length' => ['round', '60 x 60 cm', 'Enter the diameter and a unit, like Ø 120 cm.'],
        'a round table of no size' => ['round', 'Ø 0 cm', 'Enter a diameter greater than 0.'],
        'a rectangular table by width x length' => ['rectangular', '120 x 60 cm', null],
        'a rectangular table in words' => ['rectangular', 'Quite long', 'Enter the size as width x length and a unit, like 120 x 80 cm.'],
        'an organic table in words' => ['organic', 'About a metre, kidney-shaped', null],
        'not sure yet, in words' => ['not-sure', 'Small', null],
    ]);

    it('takes "Not sure yet, advise me" for the shape and the base', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'dining-tables', 'answers' => [...inquiryAnswers('dining-tables'), 'table_shape' => 'not-sure', 'table_base_design_dining' => 'not-sure']])])
            ->assertSessionHasNoErrors();
    });
});

describe('applications', function (): void {
    it('saves the application a service with an installation asks', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'kitchen-islands'])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with(['service', 'application'])->sole();

        expect($inquiry->service->slug)->toBe('slab-supply-and-installation')
            ->and($inquiry->application->slug)->toBe('kitchen-islands')
            ->and($inquiry->serviceLabel())->toBe('Slab Supply and Installation · Kitchen Islands')
            // The application's own question, answered once for the project.
            ->and($inquiry->detailAnswers()->whereHas('detailField', fn ($field) => $field->where('slug', 'waterfall_effect'))->exists())->toBeTrue();
    });

    it('takes Installation Only with an application, and no stones', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceId' => 'flooring', 'underService' => 'installation-only'])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with('application')->sole();

        expect($inquiry->application->slug)->toBe('flooring')
            ->and($inquiry->stones()->count())->toBe(0);
    });

    it('refuses', function (Closure $project, string $message): void {
        postInquiry($this, [$project()])->assertSessionHasErrors(['projects.0.applicationId' => $message]);

        expect(Inquiry::query()->count())->toBe(0);
    })->with([
        'none, where the service asks one' => [fn (): array => [...inquiryProjectInput(['serviceId' => 'flooring']), 'applicationId' => '', 'answers' => []], 'Choose the application.'],
        'one, where the service asks none' => [fn (): array => [...inquiryProjectInput(), 'applicationId' => 'flooring'], 'This service has no applications to choose.'],
        'a service that is not an application' => [fn (): array => [...inquiryProjectInput(['serviceId' => 'flooring']), 'applicationId' => 'slab-supply-only'], 'That application is no longer available.'],
    ]);

    it('refuses an application sent as the service', function (): void {
        postInquiry($this, [[...inquiryProjectInput(), 'serviceId' => 'flooring']])->assertSessionHasErrors('projects.0.serviceId');
    });

    it('refuses one no longer offered', function (): void {
        Service::query()->where('slug', 'flooring')->update(['is_active' => false]);

        postInquiry($this, [inquiryProjectInput(['serviceId' => 'flooring'])])
            ->assertSessionHasErrors(['projects.0.applicationId' => 'That application is no longer available.']);
    });

    it("asks the application's project questions", function (): void {
        $project = inquiryProjectInput(['serviceId' => 'kitchen-islands']);
        unset($project['answers']['waterfall_effect']);

        postInquiry($this, [$project])->assertSessionHasErrors('projects.0.answers.waterfall_effect');
    });

});

describe('thickness', function (): void {
    it('refuses a thickness the stone does not come in', function (): void {
        postInquiry($this, [inquiryProjectInput(['stones' => [inquiryStoneRow('slab-supply-only', ['thickness' => '37 mm'])]])])
            ->assertSessionHasErrors(['projects.0.stones.0.thickness' => 'Choose the thickness from the list.']);
    });

    it("takes Regray's advice only for a stone with no thickness listed", function (): void {
        $row = inquiryStoneRow('slab-supply-only', ['thickness' => DetailField::THICKNESS_ADVICE]);
        $stone = Stone::query()->where('slug', $row['stoneId'])->firstOrFail();

        postInquiry($this, [inquiryProjectInput(['stones' => [$row]])])
            ->assertSessionHasErrors('projects.0.stones.0.thickness');

        $stone->variants()->delete();

        postInquiry($this, [inquiryProjectInput(['stones' => [$row]])])->assertSessionHasNoErrors();
    });
});
