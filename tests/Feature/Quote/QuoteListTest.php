<?php

use App\Enums\InquiryStatus;
use App\Enums\Role;
use App\Models\Stone;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    seedInquiryCatalog();
    $this->client = inquiryClient();
    $this->slugs = Stone::query()->orderBy('id')->limit(4)->pluck('slug')->all();
});

/** The references on the page, in the order shown. */
function listedReferences(mixed $response): array
{
    return collect($response->viewData('page')['props']['inquiries']['data'])->pluck('reference')->all();
}

describe('whose inquiries', function (): void {
    it('lists only the signed-in client\'s own', function (): void {
        $other = inquiryClient();
        makeInquiry($this->client, attributes: ['reference' => 'INQ-A1']);
        makeInquiry($other, attributes: ['reference' => 'INQ-B1']);

        $response = $this->actingAs($this->client)->get(route('quote.index'))->assertOk();

        expect(listedReferences($response))->toBe(['INQ-A1']);
    });

    it('shows a client with none an empty list', function (): void {
        makeInquiry(inquiryClient());

        $this->actingAs($this->client)->get(route('quote.index'))
            ->assertInertia(fn ($page) => $page->component('quote')->has('inquiries.data', 0));
    });

    it('turns away a role without quotations.view', function (): void {
        $this->actingAs(inquiryClient(Role::Sales))->get(route('quote.index'))->assertForbidden();
    });

    it('sends a guest to log in', function (): void {
        $this->get(route('quote.index'))->assertRedirect(route('login'));
    });
});

describe('a card', function (): void {
    it('lists unique stones and services in the order first picked', function (): void {
        [$a, $b, $c] = $this->slugs;

        makeInquiry($this->client, [
            ['name' => 'Kitchen', 'stones' => [$b, $a], 'services' => ['natural-stone-supply', 'custom-stone-fabrication']],
            ['name' => 'Bath', 'stones' => [$a, $c], 'services' => ['custom-stone-fabrication', 'natural-stone-installation']],
        ]);

        $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

        expect(collect($card['stones'])->pluck('slug')->all())->toBe([$b, $a, $c])
            ->and(collect($card['services'])->pluck('id')->all())->toBe(['natural-stone-supply', 'custom-stone-fabrication', 'natural-stone-installation'])
            ->and($card['title'])->toBe('Kitchen')
            ->and($card['projectCount'])->toBe(2)
            ->and($card['actions']['cancel'])->toBe(['visible' => true, 'enabled' => true]);
    });

    it('costs the same number of queries for ten inquiries as for two', function (): void {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->client)->get(route('quote.index'))->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        foreach (range(1, 2) as $_) {
            makeInquiry($this->client, [['stones' => array_slice($this->slugs, 0, 2)], ['stones' => array_slice($this->slugs, 2, 2)]]);
        }
        $count(); // Warm anything cached on the first request.
        $two = $count();

        foreach (range(1, 8) as $_) {
            makeInquiry($this->client, [['stones' => array_slice($this->slugs, 0, 2)], ['stones' => array_slice($this->slugs, 2, 2)]]);
        }

        expect($count())->toBe($two);
    });

    it('carries nothing a client should not see', function (): void {
        makeInquiry($this->client);

        $response = $this->actingAs($this->client)->get(route('quote.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('inquiries.data.0', fn ($card) => $card
                ->hasAll(['reference', 'status', 'createdAt', 'title', 'projectCount', 'actions'])
                ->has('stones.0', fn ($stone) => $stone->hasAll(['slug', 'name', 'thumbnail']))
                ->has('services.0', fn ($service) => $service->hasAll(['id', 'name'])))
            ->etc());

        $json = json_encode($response->viewData('page')['props']['inquiries']);

        foreach (['client_id', 'changed_by', 'handler', 'thumbnail_path', 'inquiries/', 'uploaded_by', 'sha256'] as $forbidden) {
            expect($json)->not->toContain($forbidden);
        }
    });
});

describe('search', function (): void {
    it('matches the reference', function (): void {
        makeInquiry($this->client, attributes: ['reference' => 'INQ-7001']);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-8002']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => '7001']))))->toBe(['INQ-7001']);
    });

    it('matches any project name', function (): void {
        makeInquiry($this->client, [['name' => 'Kitchen'], ['name' => 'Rooftop lounge']], ['reference' => 'INQ-P1']);
        makeInquiry($this->client, [['name' => 'Bath']], ['reference' => 'INQ-P2']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'rooftop']))))->toBe(['INQ-P1']);
    });

    it('matches a stone name', function (): void {
        [$a, $b] = $this->slugs;
        makeInquiry($this->client, [['stones' => [$a]]], ['reference' => 'INQ-S1']);
        makeInquiry($this->client, [['stones' => [$b]]], ['reference' => 'INQ-S2']);
        $name = Stone::query()->where('slug', $b)->value('name');

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => $name]))))->toBe(['INQ-S2']);
    });

    it('treats % and _ as the characters they are', function (): void {
        makeInquiry($this->client, [['name' => '100% pure']], ['reference' => 'INQ-L1']);
        makeInquiry($this->client, [['name' => '100 pure']], ['reference' => 'INQ-L2']);
        makeInquiry($this->client, [['name' => 'a_b']], ['reference' => 'INQ-L3']);
        makeInquiry($this->client, [['name' => 'axb']], ['reference' => 'INQ-L4']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => '100%']))))->toBe(['INQ-L1'])
            ->and(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'a_b']))))->toBe(['INQ-L3']);
    });

    it('never reaches another client\'s inquiry', function (): void {
        makeInquiry(inquiryClient(), [['name' => 'Secret villa']], ['reference' => 'INQ-X1']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'villa']))))->toBe([]);
    });
});

describe('status and sort', function (): void {
    it('filters by status', function (): void {
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F1', 'status' => InquiryStatus::Pending]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F2', 'status' => InquiryStatus::Ongoing]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F3', 'status' => InquiryStatus::Ongoing]);

        $response = $this->actingAs($this->client)->get(route('quote.index', ['status' => 'ongoing']));

        expect(listedReferences($response))->toEqualCanonicalizing(['INQ-F2', 'INQ-F3']);
    });

    it('sorts newest first by default and oldest first on request, id breaking ties', function (): void {
        makeInquiry($this->client, attributes: ['reference' => 'INQ-O1', 'created_at' => now()->subDays(3)]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-O2', 'created_at' => now()->subDay()]);
        $tieA = makeInquiry($this->client, attributes: ['reference' => 'INQ-O3', 'created_at' => now()->subDay()]);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index'))))->toBe(['INQ-O3', 'INQ-O2', 'INQ-O1'])
            ->and(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['sort' => 'oldest']))))->toBe(['INQ-O1', 'INQ-O2', 'INQ-O3'])
            ->and($tieA->id)->toBeGreaterThan(0);
    });
});

describe('pages', function (): void {
    it('gives ten a page and says whether there are more', function (): void {
        foreach (range(1, 25) as $_) {
            makeInquiry($this->client);
        }

        $first = $this->actingAs($this->client)->get(route('quote.index'));
        $last = $this->actingAs($this->client)->get(route('quote.index', ['page' => 3]));

        expect($first->viewData('page')['props']['inquiries']['data'])->toHaveCount(10)
            ->and($first->viewData('page')['props']['hasMore'])->toBeTrue()
            ->and($last->viewData('page')['props']['inquiries']['data'])->toHaveCount(5)
            ->and($last->viewData('page')['props']['hasMore'])->toBeFalse();
    });

    it('says there is no more when the list fits one page', function (): void {
        makeInquiry($this->client);

        expect($this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['hasMore'])->toBeFalse();
    });
});

describe('bad query values', function (): void {
    it('fall back to the defaults instead of failing', function (array $query): void {
        makeInquiry($this->client, attributes: ['reference' => 'INQ-D1', 'status' => InquiryStatus::Ongoing]);

        $response = $this->actingAs($this->client)->get(route('quote.index', $query))->assertOk();

        expect($response->viewData('page')['props']['filters'])->toBe(['search' => '', 'status' => 'all', 'sort' => 'newest'])
            ->and(listedReferences($response))->toBe(['INQ-D1']);
    })->with([
        'unknown status' => [['status' => 'bogus']],
        'draft status' => [['status' => 'draft']],
        'unknown sort' => [['sort' => 'sideways']],
        'page zero' => [['page' => 0]],
        'negative page' => [['page' => -4]],
        'page that is not a number' => [['page' => 'abc']],
        'search over 100 characters' => [['search' => str_repeat('a', 101)]],
        'array where a string belongs' => [['search' => ['x'], 'status' => ['ongoing']]],
    ]);

    it('trims a search', function (): void {
        $response = $this->actingAs($this->client)->get(route('quote.index', ['search' => '  INQ  ']));

        expect($response->viewData('page')['props']['filters']['search'])->toBe('INQ');
    });
});
