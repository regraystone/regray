<?php

use App\Models\Brand;
use Database\Seeders\BrandSeeder;

/*
 * Reform's words described the Copenhagen kitchen maker; the migration gives
 * it re/form's, the sintered stone brand's, but only where the old seeded
 * words are still there.
 */
beforeEach(function (): void {
    $this->seed(BrandSeeder::class);
    $this->migration = require database_path('migrations/2026_11_19_110233_correct_reform_brand_copy.php');
});

test('the old seeded words become re/form\'s, and going back restores them', function (): void {
    $reform = Brand::query()->where('slug', 'reform')->firstOrFail();
    $reform->update([
        'tagline' => 'Copenhagen kitchens and storage since 2014, designed with architects like David Chipperfield.',
        'description' => 'Copenhagen kitchens and storage since 2014, designed with architects such as David Chipperfield, Norm Architects and Jean Nouvel.',
    ]);

    $this->migration->up();

    expect($reform->fresh()->description)->toStartWith('Re/form redefines materiality')
        ->and($reform->fresh()->tagline)->toStartWith('Sintered stone surfaces');

    $this->migration->down();

    expect($reform->fresh()->description)->toStartWith('Copenhagen kitchens');
});

test('a description edited by hand is left alone', function (): void {
    $reform = Brand::query()->where('slug', 'reform')->firstOrFail();
    $reform->update(['description' => 'Written by our team.']);

    $this->migration->up();

    expect($reform->fresh()->description)->toBe('Written by our team.');
});

test('a fresh seed already carries re/form\'s words', function (): void {
    expect(Brand::query()->where('slug', 'reform')->value('description'))->toStartWith('Re/form redefines materiality');
});
