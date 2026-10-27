<?php

use App\Enums\InquiryStatus;
use App\Enums\MarketingStatus;
use App\Enums\PaymentStatus;
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
    it('lists unique stones in the order picked, and the services', function (): void {
        [$a, $b] = $this->slugs;

        makeInquiry($this->client, ['name' => 'Kitchen', 'stones' => [$b, $a], 'services' => ['natural-stone-supply', 'custom-stone-fabrication']]);

        $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

        expect(collect($card['stones'])->pluck('slug')->all())->toBe([$b, $a])
            ->and(collect($card['services'])->pluck('id')->all())->toEqualCanonicalizing(['natural-stone-supply', 'custom-stone-fabrication'])
            ->and($card['title'])->toBe('Kitchen')
            ->and($card)->not->toHaveKey('projectCount')
            ->and($card['actions']['cancel'])->toBe(['visible' => true, 'enabled' => true]);
    });

    it('carries the payment status only once the client has approved the quotation', function (InquiryStatus $status, ?string $payment): void {
        makeInquiry($this->client, attributes: ['status' => $status]);

        $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

        expect($card['status'])->toBe($status->value)
            ->and($card['paymentStatus'])->toBe($payment);
    })->with([
        'pending' => [InquiryStatus::Pending, null],
        'negotiation' => [InquiryStatus::Negotiation, null],
        'sales on hold' => [InquiryStatus::OnHold, null],
        'cancelled' => [InquiryStatus::Cancelled, null],
        'quoted' => [InquiryStatus::Quoted, 'awaiting'],
        'closed' => [InquiryStatus::Closed, 'awaiting'],
    ]);

    it('carries two statuses once approved: the display status and the payment status', function (): void {
        makeInquiry($this->client, attributes: ['status' => InquiryStatus::Production, 'payment_status' => PaymentStatus::Partial]);

        $card = $this->actingAs($this->client)->get(route('quote.index'))->viewData('page')['props']['inquiries']['data'][0];

        expect($card['status'])->toBe('production')
            ->and($card['paymentStatus'])->toBe('partial')
            ->and($card)->not->toHaveKeys(['marketingStatus', 'salesStatus', 'processStatus']);
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
            makeInquiry($this->client, ['stones' => array_slice($this->slugs, 0, 2)], ['status' => InquiryStatus::Production]);
        }
        $count(); // Warm anything cached on the first request.
        $two = $count();

        foreach (range(1, 8) as $_) {
            makeInquiry($this->client, ['stones' => array_slice($this->slugs, 2, 2)], ['status' => InquiryStatus::Production]);
        }

        expect($count())->toBe($two);
    });

    it('carries nothing a client should not see', function (): void {
        makeInquiry($this->client);

        $response = $this->actingAs($this->client)->get(route('quote.index'));

        $response->assertInertia(fn ($page) => $page
            ->has('inquiries.data.0', fn ($card) => $card
                ->hasAll(['reference', 'status', 'paymentStatus', 'createdAt', 'title', 'actions'])
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

    it('matches the project name', function (): void {
        makeInquiry($this->client, ['name' => 'Rooftop lounge'], ['reference' => 'INQ-P1']);
        makeInquiry($this->client, ['name' => 'Bath'], ['reference' => 'INQ-P2']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'rooftop']))))->toBe(['INQ-P1']);
    });

    it('matches a stone name', function (): void {
        [$a, $b] = $this->slugs;
        makeInquiry($this->client, ['stones' => [$a]], ['reference' => 'INQ-S1']);
        makeInquiry($this->client, ['stones' => [$b]], ['reference' => 'INQ-S2']);
        $name = Stone::query()->where('slug', $b)->value('name');

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => $name]))))->toBe(['INQ-S2']);
    });

    it('treats % and _ as the characters they are', function (): void {
        makeInquiry($this->client, ['name' => '100% pure'], ['reference' => 'INQ-L1']);
        makeInquiry($this->client, ['name' => '100 pure'], ['reference' => 'INQ-L2']);
        makeInquiry($this->client, ['name' => 'a_b'], ['reference' => 'INQ-L3']);
        makeInquiry($this->client, ['name' => 'axb'], ['reference' => 'INQ-L4']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => '100%']))))->toBe(['INQ-L1'])
            ->and(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'a_b']))))->toBe(['INQ-L3']);
    });

    it('never reaches another client\'s inquiry', function (): void {
        makeInquiry(inquiryClient(), ['name' => 'Secret villa'], ['reference' => 'INQ-X1']);

        expect(listedReferences($this->actingAs($this->client)->get(route('quote.index', ['search' => 'villa']))))->toBe([]);
    });
});

describe('status and sort', function (): void {
    it('filters by status tab', function (string $tab, array $expected): void {
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F1', 'status' => InquiryStatus::Pending]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F2', 'status' => InquiryStatus::SiteVisited]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F3', 'status' => InquiryStatus::Quoted]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F4', 'status' => InquiryStatus::Delayed]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F5', 'status' => InquiryStatus::Closed]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F6', 'status' => InquiryStatus::Cancelled]);
        makeInquiry($this->client, attributes: ['reference' => 'INQ-F7', 'marketing_status' => MarketingStatus::Lost]);

        $response = $this->actingAs($this->client)->get(route('quote.index', ['status' => $tab]));

        expect(listedReferences($response))->toEqualCanonicalizing($expected)
            ->and($response->viewData('page')['props']['filters']['status'])->toBe($tab);
    })->with([
        'pending' => ['pending', ['INQ-F1']],
        'in progress' => ['in_progress', ['INQ-F2', 'INQ-F3', 'INQ-F4']],
        'closed' => ['closed', ['INQ-F5']],
        'cancelled' => ['cancelled', ['INQ-F6', 'INQ-F7']],
    ]);

    it('offers the tabs all, pending, in progress, closed and cancelled', function (): void {
        $this->actingAs($this->client)->get(route('quote.index'))
            ->assertInertia(fn ($page) => $page->where('statusOptions', ['all', 'pending', 'in_progress', 'closed', 'cancelled']));
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
        makeInquiry($this->client, attributes: ['reference' => 'INQ-D1', 'status' => InquiryStatus::Negotiation]);

        $response = $this->actingAs($this->client)->get(route('quote.index', $query))->assertOk();

        expect($response->viewData('page')['props']['filters'])->toBe(['search' => '', 'status' => 'all', 'sort' => 'newest'])
            ->and(listedReferences($response))->toBe(['INQ-D1']);
    })->with([
        'unknown status' => [['status' => 'bogus']],
        'a display status rather than a tab' => [['status' => 'negotiation']],
        'unknown sort' => [['sort' => 'sideways']],
        'page zero' => [['page' => 0]],
        'negative page' => [['page' => -4]],
        'page that is not a number' => [['page' => 'abc']],
        'search over 100 characters' => [['search' => str_repeat('a', 101)]],
        'array where a string belongs' => [['search' => ['x'], 'status' => ['pending']]],
    ]);

    it('trims a search', function (): void {
        $response = $this->actingAs($this->client)->get(route('quote.index', ['search' => '  INQ  ']));

        expect($response->viewData('page')['props']['filters']['search'])->toBe('INQ');
    });
});
