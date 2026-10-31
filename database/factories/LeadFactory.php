<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A lead written straight to the database, without its history row. Its
 * reference comes from the same sequence the app uses. A lead has no status of
 * its own: its inquiries carry marketing's decisions.
 *
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => fn (): string => 'LEAD-'.str_pad((string) fake()->unique()->numberBetween(9000, 99999), 4, '0', STR_PAD_LEFT),
            'client_id' => User::factory()->role(Role::Client),
            'marketing_user_id' => User::factory()->role(Role::Marketing),
        ];
    }
}
