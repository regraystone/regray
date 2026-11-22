<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A brand's Overview is now its facts alone (plus the live catalogue count),
 * so the admin can change every line of it on the Brands page. The brand page
 * used to add two rows of its own, which now become facts:
 *
 * - "Category": the brand's groups on Our brands, e.g. "Kitchens";
 * - "See it at": "Regray showroom, Makati City".
 *
 * Regray Stone's "Based in" and "Installed" facts go.
 *
 * Only these rows are touched: a fact already there under the same label is
 * kept as it is and not added again, one the admin has reworded is left
 * alone, and a brand with no room left (6 facts) gets no more. A brand not in
 * the database (a fresh one, seeded afterwards from data/brands.php, which
 * lists the same facts) is skipped.
 */
return new class extends Migration
{
    private const SEE_IT_AT = ['label' => 'See it at', 'value' => 'Regray showroom, Makati City'];

    /** @var array<string, string> Each brand's Category, by slug. */
    private const CATEGORIES = [
        'regray-stone' => 'Stone & Surfaces, Furniture & Interiors',
        'cucine' => 'Kitchens',
        'ascale' => 'Stone & Surfaces',
        'faer' => 'Wardrobes & Doors, Furniture & Interiors',
        'reform' => 'Stone & Surfaces',
        'res-italia' => 'Wardrobes & Doors',
    ];

    /** @var list<array{label: string, value: string}> Regray Stone's facts that go. */
    private const REGRAY_REMOVED = [
        ['label' => 'Based in', 'value' => 'Makati City, Philippines'],
        ['label' => 'Installed', 'value' => 'Over 1,000 spaces'],
    ];

    private const FACTS_MAX = 6;

    public function up(): void
    {
        foreach (self::CATEGORIES as $slug => $category) {
            $this->rewrite($slug, function (array $facts) use ($slug, $category): array {
                if ($slug === 'regray-stone') {
                    $facts = array_values(array_filter($facts, fn (array $fact): bool => ! in_array($fact, self::REGRAY_REMOVED, true)));
                }

                foreach ([['label' => 'Category', 'value' => $category], self::SEE_IT_AT] as $added) {
                    $labels = array_map(fn (array $fact): string => mb_strtolower($fact['label']), $facts);

                    if (! in_array(mb_strtolower($added['label']), $labels, true) && count($facts) < self::FACTS_MAX) {
                        $facts[] = $added;
                    }
                }

                return $facts;
            });
        }
    }

    public function down(): void
    {
        foreach (self::CATEGORIES as $slug => $category) {
            $this->rewrite($slug, function (array $facts) use ($slug, $category): array {
                $facts = array_values(array_filter(
                    $facts,
                    fn (array $fact): bool => ! in_array($fact, [['label' => 'Category', 'value' => $category], self::SEE_IT_AT], true),
                ));

                if ($slug === 'regray-stone') {
                    [$basedIn, $installed] = self::REGRAY_REMOVED;
                    $facts = array_slice([$basedIn, ...$facts, $installed], 0, self::FACTS_MAX);
                }

                return $facts;
            });
        }
    }

    /**
     * Replace a brand's facts with what `$change` makes of them, in order.
     * They are deleted and inserted again, since (brand, order) is unique.
     *
     * @param  callable(list<array{label: string, value: string}>): list<array{label: string, value: string}>  $change
     */
    private function rewrite(string $slug, callable $change): void
    {
        $brandId = DB::table('brands')->where('slug', $slug)->value('id');

        if ($brandId === null) {
            return;
        }

        $facts = array_values(DB::table('brand_facts')->where('brand_id', $brandId)->orderBy('sort_order')->get(['label', 'value'])
            ->map(fn (object $fact): array => ['label' => (string) $fact->label, 'value' => (string) $fact->value])
            ->all());
        $changed = $change($facts);

        if ($changed === $facts) {
            return;
        }

        $now = now();
        DB::table('brand_facts')->where('brand_id', $brandId)->delete();
        DB::table('brand_facts')->insert(array_map(
            fn (int $order, array $fact): array => ['brand_id' => $brandId, ...$fact, 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now],
            array_keys($changed),
            $changed,
        ));
    }
};
