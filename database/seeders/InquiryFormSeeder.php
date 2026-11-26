<?php

namespace Database\Seeders;

use App\Models\Choice;
use App\Models\DetailField;
use App\Models\DetailFieldOption;
use App\Models\Service;
use App\Models\StoneType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Brings a database already in use up to the Inquire page's notes and
 * seaters without touching what its admins have changed, which
 * DetailFieldSeeder, ChoiceSeeder and ServiceSeeder would overwrite. Run once
 * after the migration that added them (2026_11_27_000001).
 *
 * - A free entry's Other gets its label and line ("Custom size", "Enter your
 *   desired size.") where it has none, and Number of seaters' suggestions
 *   their table sizes ("approx. 0.6 sqm") where they have none.
 * - A service gets its delivery and date lines and its fewest days ahead
 *   where it has none.
 * - Number of seaters suggests 6, 8 and 12 only (others are switched off,
 *   so a sent answer still reads); any other number is the client's own.
 * - Center and side tables are asked Number of seaters, required, after
 *   their size; dining tables and other customized furniture no longer ask
 *   Project area.
 * - Table services no longer ask Table base (the base's material): it is
 *   taken off them and archived, with its Wood and Steel choices, so a sent
 *   inquiry that answered it still reads it. Their base design is a plain
 *   list of every base.
 * - The Monumental stone type is no longer offered for the furniture
 *   services.
 * - Each table shape still on its first seeded picture gets the new one
 *   (data/choices.php); one an admin uploaded on the Choices page stays.
 *
 * A new database gets all of this from DatabaseSeeder.
 */
class InquiryFormSeeder extends Seeder
{
    /** The services that now ask how many seaters. */
    private const SEATER_SERVICES = ['center-tables', 'side-tables'];

    /** The furniture services that no longer ask for the project area. */
    private const NO_AREA_SERVICES = ['dining-tables', 'other-customized-furniture'];

    /** The table services, which no longer ask the base's material. */
    private const TABLE_SERVICES = ['center-tables', 'side-tables', 'dining-tables'];

    /** Each table shape's picture before the new photos, by choice slug. */
    private const OLD_SHAPE_PICTURES = [
        'rectangular' => 'question-choices/1437d2234a87aa3364c4d7cb1755daea.jpg',
        'round' => 'question-choices/f3e00a58c58d9eb406b11a2bc7f246b4.jpg',
        'oval' => 'question-choices/16e4fd55abacace8625b14e1d2e6de63.jpg',
        'square' => 'question-choices/5325ee9876d3ab8ec7cf48f73cb1acf0.jpg',
        'rounded-rectangle' => 'question-choices/23cfce4c037725514a8dfffd5c6ccc4d.jpg',
        'organic' => 'question-choices/e65ad613372a39cc775d115363a3954f.jpg',
    ];

    /** The furniture services the Monumental stone type is no longer offered for. */
    private const FURNITURE_SERVICES = ['center-tables', 'side-tables', 'dining-tables', 'other-customized-furniture'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->describeQuestions();
            $this->noteServices();
            $this->askSeaters();
            $this->seaterSuggestions();
            $this->dropArea();
            $this->dropMaterial();
            $this->newShapePictures();

            StoneType::query()->where('slug', 'monumental')->first()?->services()
                ->detach(Service::query()->whereIn('slug', self::FURNITURE_SERVICES)->pluck('id')->all());
        });
    }

    /** The Other labels and lines, and the suggestions' notes, that are missing. */
    private function describeQuestions(): void
    {
        foreach (DetailFieldSeeder::data() as $data) {
            $field = DetailField::query()->where('slug', $data['slug'])->first();

            if ($field === null) {
                continue;
            }

            foreach (['custom_label', 'custom_hint'] as $column) {
                if ($field->{$column} === null && isset($data[$column])) {
                    $field->{$column} = $data[$column];
                }
            }

            $field->save();

            foreach ($data['suggestions'] ?? [] as $suggestion) {
                if (isset($suggestion[2])) {
                    DetailFieldOption::query()
                        ->where('detail_field_id', $field->id)
                        ->where('is_suggestion', true)
                        ->where('slug', $suggestion[0])
                        ->whereNull('description')
                        ->update(['description' => $suggestion[2]]);
                }
            }
        }
    }

    /** Each service's delivery and date lines and fewest days ahead, where it has none. */
    private function noteServices(): void
    {
        /** @var list<array{services: list<array{slug: string, delivery_note?: string, schedule_note?: string, lead_days?: int}>}> $types */
        $types = require database_path('seeders/data/services.php');

        foreach ($types as $type) {
            foreach ($type['services'] as $data) {
                $service = Service::query()->where('slug', $data['slug'])->first();

                if ($service === null) {
                    continue;
                }

                $service->fill(array_filter([
                    'delivery_note' => $service->delivery_note === null ? ($data['delivery_note'] ?? null) : null,
                    'schedule_note' => $service->schedule_note === null ? ($data['schedule_note'] ?? null) : null,
                    'lead_days' => $service->lead_days === null ? ($data['lead_days'] ?? null) : null,
                ], fn (mixed $value): bool => $value !== null))->save();
            }
        }
    }

    /** Center and side tables ask how many seaters, required, right after their size. */
    private function askSeaters(): void
    {
        $seaters = DetailField::query()->where('slug', 'seaters')->value('id');
        $size = DetailField::query()->where('slug', DetailField::SIZE)->value('id');

        if ($seaters === null) {
            return;
        }

        foreach (Service::query()->whereIn('slug', self::SEATER_SERVICES)->get() as $service) {
            $order = $this->order($service);

            if (in_array($seaters, $order, true)) {
                continue;
            }

            $at = array_search($size, $order, true);
            array_splice($order, $at === false ? count($order) : $at + 1, 0, [$seaters]);
            $service->detailFields()->attach($seaters, ['sort_order' => 0, 'is_required' => true]);
            $this->reorder($service, $order);
        }
    }

    /** Number of seaters suggests the seeded numbers alone (6, 8, 12), each switched on. */
    private function seaterSuggestions(): void
    {
        $seaters = DetailField::query()->where('slug', 'seaters')->value('id');
        $listed = collect(DetailFieldSeeder::data())->firstWhere('slug', 'seaters')['suggestions'] ?? [];
        $keep = array_map(fn (array $suggestion): string => $suggestion[0], $listed);

        if ($seaters === null || $keep === []) {
            return;
        }

        $suggestions = DetailFieldOption::query()->where('detail_field_id', $seaters)->where('is_suggestion', true);
        (clone $suggestions)->whereNotIn('slug', $keep)->update(['is_active' => false]);
        (clone $suggestions)->whereIn('slug', $keep)->update(['is_active' => true]);
    }

    /** Dining tables and other customized furniture no longer ask for the project area. */
    private function dropArea(): void
    {
        $area = DetailField::query()->where('slug', 'area_sqm')->value('id');

        if ($area === null) {
            return;
        }

        foreach (Service::query()->whereIn('slug', self::NO_AREA_SERVICES)->get() as $service) {
            $service->detailFields()->detach($area);
            $this->reorder($service, $this->order($service));
        }
    }

    /**
     * Table services no longer ask Table base. It is taken off them, then
     * archived once no service asks it, as the Questions page archives one;
     * its choices (Wood, Steel) are archived once no live question lists
     * them. Rows stay, so a sent inquiry's answer still reads.
     */
    private function dropMaterial(): void
    {
        $material = DetailField::query()->where('slug', 'table_base')->first();

        if ($material === null) {
            return;
        }

        foreach (Service::query()->whereIn('slug', self::TABLE_SERVICES)->get() as $service) {
            $service->detailFields()->detach($material->id);
            $this->reorder($service, $this->order($service));
        }

        if (DB::table('service_detail_fields')->where('detail_field_id', $material->id)->exists()) {
            return;
        }

        $material->forceFill(['archived_at' => $material->archived_at ?? now()])->save();

        foreach (['wood', 'steel'] as $slug) {
            $choice = Choice::query()->where('slug', $slug)->whereNull('archived_at')->first();
            $listed = $choice !== null && DetailFieldOption::query()
                ->where('choice_id', $choice->id)
                ->whereNull('archived_at')
                ->whereHas('detailField', fn ($question) => $question->whereNull('archived_at'))
                ->exists();

            if ($choice !== null && ! $listed) {
                $choice->forceFill(['archived_at' => now(), 'is_active' => false])->save();
            }
        }
    }

    /**
     * A table shape still on its old seeded picture takes the new one; saved
     * through the model, so every question listing it follows
     * (Choice::mirror()).
     */
    private function newShapePictures(): void
    {
        $pictures = collect(ChoiceSeeder::data())->pluck('image', 'slug');

        foreach (self::OLD_SHAPE_PICTURES as $slug => $old) {
            $choice = Choice::query()->where('slug', $slug)->where('thumbnail_path', $old)->first();

            if ($choice !== null && filled($pictures[$slug] ?? null)) {
                $choice->update(['thumbnail_path' => $pictures[$slug]]);
            }
        }
    }

    /**
     * The questions a service asks, by id, in its order.
     *
     * @return list<int>
     */
    private function order(Service $service): array
    {
        return array_values(array_map(intval(...), DB::table('service_detail_fields')
            ->where('service_id', $service->id)
            ->orderBy('sort_order')
            ->orderBy('detail_field_id')
            ->pluck('detail_field_id')
            ->all()));
    }

    /**
     * Number a service's questions in this order, from 0.
     *
     * @param  list<int>  $order
     */
    private function reorder(Service $service, array $order): void
    {
        foreach ($order as $sort => $fieldId) {
            DB::table('service_detail_fields')
                ->where('service_id', $service->id)
                ->where('detail_field_id', $fieldId)
                ->update(['sort_order' => $sort]);
        }
    }
}
