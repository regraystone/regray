<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Reform brand's words described a different company, the Copenhagen
 * kitchen maker. Regray's Reform is re/form, the sintered stone brand (its
 * logo reads "re/form sintered stone"), as regraystone.com describes it.
 *
 * Only the seeded words are replaced, so a description someone has since
 * edited by hand is left alone. The seed data (BrandSeeder) carries the same
 * words for fresh installs.
 */
return new class extends Migration
{
    private const OLD_TAGLINE = 'Copenhagen kitchens and storage since 2014, designed with architects like David Chipperfield.';

    private const OLD_DESCRIPTION = 'Copenhagen kitchens and storage since 2014, designed with architects such as David Chipperfield, Norm Architects and Jean Nouvel.';

    private const TAGLINE = 'Sintered stone surfaces with refined contemporary design and sophisticated finishes.';

    private const DESCRIPTION = 'Re/form redefines materiality through advanced technology, precision engineering and refined contemporary design: sintered stone surfaces with sophisticated finishes, for architectural solutions defined by performance, craftsmanship and understated luxury.';

    public function up(): void
    {
        $this->replace(self::OLD_TAGLINE, self::OLD_DESCRIPTION, self::TAGLINE, self::DESCRIPTION);
    }

    public function down(): void
    {
        $this->replace(self::TAGLINE, self::DESCRIPTION, self::OLD_TAGLINE, self::OLD_DESCRIPTION);
    }

    private function replace(string $fromTagline, string $fromDescription, string $toTagline, string $toDescription): void
    {
        DB::table('brands')->where('slug', 'reform')->where('tagline', $fromTagline)->update(['tagline' => $toTagline]);
        DB::table('brands')->where('slug', 'reform')->where('description', $fromDescription)->update(['description' => $toDescription]);
    }
};
