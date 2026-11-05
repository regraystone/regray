<?php

use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\Inquiry;
use App\Models\ServiceOption;
use App\Models\Stone;
use Illuminate\Http\UploadedFile;
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
    'an unknown service' => [fn (array $p): array => [...$p, 'serviceIds' => ['no-such-service']], 'projects.0.serviceIds.0'],
    'an unknown edge profile' => [fn (array $p): array => [...$p, 'stones' => [[...$p['stones'][0], 'edge' => 'Wavy']]], 'projects.0.stones.0.edge'],
    'an unknown application' => [fn (array $p): array => [...$p, 'applicationIds' => [999999]], 'projects.0.applicationIds.0'],
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
     * @return list<array{stoneId: string, quantity: int, edge: string, finish: string}>
     */
    function distinctStoneRows(int $count): array
    {
        $rows = [];

        foreach (Stone::query()->active()->limit(3)->pluck('slug') as $slug) {
            foreach (EdgeProfile::query()->pluck('name') as $edge) {
                $rows[] = ['stoneId' => $slug, 'quantity' => 1, 'edge' => $edge, 'finish' => Finish::query()->value('name')];
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

    it('refuses the same stone with the same edge and finish', function (): void {
        $row = inquiryProjectInput()['stones'][0];

        postInquiry($this, [inquiryProjectInput(['stones' => [$row, $row]])])->assertSessionHasErrors('projects.0.stones.1.finish');
    });
});

describe('standalone and regular services', function (): void {
    it('accepts a standalone service with no stones and no applications', function (): void {
        postInquiry($this, [inquiryVisitInput()])->assertSessionHasNoErrors();
    });

    it('accepts extras on a standalone service', function (): void {
        postInquiry($this, [inquiryVisitInput([
            'stones' => inquiryProjectInput()['stones'],
            'applicationIds' => inquiryProjectInput()['applicationIds'],
        ])])->assertSessionHasNoErrors();
    });

    it('refuses a standalone service with no date or time', function (): void {
        postInquiry($this, [inquiryVisitInput(['neededBy' => '', 'neededByTime' => ''])])
            ->assertSessionHasErrors(['projects.0.neededBy', 'projects.0.neededByTime']);
    });

    it('refuses a standalone visit at a time that has already passed', function (): void {
        postInquiry($this, [inquiryVisitInput(['neededBy' => now()->toDateString(), 'neededByTime' => now()->subMinutes(5)->format('H:i')])])
            ->assertSessionHasErrors('projects.0.neededByTime');
    });

    it('refuses a regular service with no stones', function (): void {
        postInquiry($this, [inquiryProjectInput(['stones' => []])])->assertSessionHasErrors('projects.0.stones');
    });

    it('refuses a regular service with no applications', function (): void {
        postInquiry($this, [inquiryProjectInput(['applicationIds' => []])])->assertSessionHasErrors('projects.0.applicationIds');
    });

    it('refuses services from different selection groups in one project', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceIds' => ['natural-stone-supply', 'stone-relocation-dismantling-reinstallation']])])
            ->assertSessionHasErrors(['projects.0.serviceIds' => 'These services cannot be in the same project.']);
    });

    it('accepts services from the same selection group in one project', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceIds' => ['natural-stone-supply', 'custom-stone-fabrication']])])
            ->assertSessionDoesntHaveErrors();
    });
});

describe('kinds of work', function (): void {
    it('saves the kind of work picked under each service', function (): void {
        postInquiry($this, [inquiryProjectInput([
            'serviceIds' => ['natural-stone-supply', 'custom-stone-fabrication'],
            'serviceOptions' => ['natural-stone-supply' => 'supply-installation', 'custom-stone-fabrication' => 'fabrication-supply'],
        ])])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->with('serviceOptions')->sole();

        expect($inquiry->serviceOptions->pluck('slug')->sort()->values()->all())
            ->toBe(['fabrication-supply', 'supply-installation']);
    });

    it('refuses a service with no kind of work, naming the service', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceOptions' => ['custom-stone-fabrication' => 'fabrication-only']])])
            ->assertSessionHasErrors(['projects.0.serviceOptions.natural-stone-supply' => 'Choose what you need for Natural Stone Supply.']);

        expect(Inquiry::query()->count())->toBe(0);
    });

    it('refuses a project sent without any kinds of work', function (): void {
        $project = inquiryProjectInput();
        unset($project['serviceOptions']);

        postInquiry($this, [$project])
            ->assertSessionHasErrors(['projects.0.serviceOptions' => 'Choose what you need for each service.']);
    });

    it('refuses a kind of work that belongs to another service', function (): void {
        postInquiry($this, [inquiryProjectInput(['serviceOptions' => ['natural-stone-supply' => 'fabrication-only']])])
            ->assertSessionHasErrors(['projects.0.serviceOptions.natural-stone-supply' => 'That choice is no longer available for Natural Stone Supply.']);
    });

    it('refuses a kind of work that is no longer offered', function (): void {
        ServiceOption::query()->where('slug', 'supply-only')->update(['is_active' => false]);

        postInquiry($this, [inquiryProjectInput(['serviceOptions' => ['natural-stone-supply' => 'supply-only']])])
            ->assertSessionHasErrors(['projects.0.serviceOptions.natural-stone-supply' => 'That choice is no longer available for Natural Stone Supply.']);
    });
});
