<?php

use App\Enums\Role;
use App\Models\EdgeProfile;
use App\Models\Finish;
use App\Models\ProjectCategory;
use App\Models\Stone;
use App\Models\User;
use Database\Seeders\EdgeProfileSeeder;
use Database\Seeders\ProjectCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit/Inquiry');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The rows an inquiry is made from: roles, services, edge profiles, and the
 * stones and finishes the stone seeder, and the project categories the Application step offers brings with them.
 */
function seedInquiryCatalog(): void
{
    test()->seed([
        RolePermissionSeeder::class,
        ServiceSeeder::class,
        ProjectCategorySeeder::class,
        EdgeProfileSeeder::class,
        StoneSeeder::class,
    ]);
}

function inquiryClient(Role $role = Role::Client): User
{
    return User::factory()->role($role)->create();
}

/**
 * One valid project in the shape the Inquire page posts, with `$overrides`
 * laid over it. A regular service, so it carries a stone and an application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryProjectInput(array $overrides = []): array
{
    return [
        'name' => 'Kitchen counters',
        'serviceIds' => ['natural-stone-supply'],
        'applicationIds' => [ProjectCategory::query()->orderBy('id')->value('slug')],
        'stones' => [[
            'stoneId' => Stone::query()->active()->orderBy('id')->value('slug'),
            'quantity' => 4,
            'edge' => EdgeProfile::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
            'finish' => Finish::query()->where('is_active', true)->orderBy('sort_order')->value('name'),
        ]],
        'location' => 'Makati',
        'landmark' => 'Near the church',
        'areaSqm' => '12.5',
        'neededBy' => now()->addDays(10)->toDateString(),
        'neededByTime' => '',
        'notes' => 'Ground floor.',
        ...$overrides,
    ];
}

/**
 * A visit: a service that stands on its own, with no stone and no application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inquiryVisitInput(array $overrides = []): array
{
    return [
        'name' => 'Site Ocular & Technical Assessment',
        'serviceIds' => ['site-ocular-technical-assessment'],
        'location' => 'Taguig',
        'landmark' => '',
        'areaSqm' => '',
        'neededBy' => now()->addDays(5)->toDateString(),
        'neededByTime' => '12:00',
        'notes' => '',
        ...$overrides,
    ];
}
