<?php

namespace Database\Seeders;

use App\Models\Showcase;
use App\Models\Stone;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The stones each seeded project used, exactly as data/showcases.php lists
 * them: a stone listed before and not now comes off. ShowcaseSeeder runs it
 * for a new database; on one in use it corrects the projects alone, without
 * touching their photos or categories:
 * `php artisan db:seed --class=ShowcaseStonesSeeder --force`.
 *
 * Warrior and Green Crystal come from BrazilStonesSeeder, run first. A project
 * whose overview is still as first seeded gets the corrected one, which names
 * the stone types it now has; one an admin rewrote stays.
 */
class ShowcaseStonesSeeder extends Seeder
{
    /**
     * Overviews as first seeded that named stone types the project did not
     * use, by project slug.
     */
    private const OLD_OVERVIEWS = [
        'recidencia-del-hamor' => 'Recidencia Del Hamor is a commercial spaces showcase in Casiguran, Sorsogon, completed in 2025. The showcase features marble and quartzite, with wall accents that bring out the natural veining and character of each stone. Explore the stones used in this showcase below.',
        'belo-medical-group' => "Belo Medical Group is one of the Philippines' most established names in medical aesthetics, known for its expertise in advanced cosmetic dermatology and plastic surgery. With more than three decades of practice and a growing network of clinics nationwide, the brand combines clinical precision, innovation, and refined patient care to deliver a highly sophisticated approach to modern aesthetics. Completed in 2026 at Proscenium Rockwell, Makati City, this commercial space features marble wall accents.",
        'ginza-one' => 'Ginza One is a commercial spaces showcase on Chino Roces Ext., Makati City, completed in 2024. The showcase spans wall accents as well as countertops & backsplash, and features patagonia quartzite and onyx. Explore the stones used in this showcase below.',
    ];

    public function run(): void
    {
        $this->call(BrazilStonesSeeder::class);

        $data = require database_path('seeders/data/showcases.php');

        foreach ($data['showcases'] as $showcaseData) {
            $showcase = Showcase::query()->where('slug', $showcaseData['slug'])->first();

            if ($showcase === null) {
                continue;
            }

            $stones = Stone::query()->withTrashed()->whereIn('slug', $showcaseData['stone_slugs'])->pluck('id', 'slug');
            $missing = array_diff($showcaseData['stone_slugs'], $stones->keys()->all());

            if ($missing !== []) {
                throw new RuntimeException("{$showcaseData['slug']} names stones that do not exist: ".implode(', ', $missing).'.');
            }

            $showcase->stones()->sync($stones->values()->all());

            if (isset(self::OLD_OVERVIEWS[$showcase->slug]) && $showcase->overview === self::OLD_OVERVIEWS[$showcase->slug]) {
                $showcase->update(['overview' => $showcaseData['overview']]);
            }
        }
    }
}
