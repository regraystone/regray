<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A lead written straight to the database, without its history row. Its
 * reference comes from the same sequence the app uses.
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
            'status' => LeadStatus::Idle,
            'status_changed_at' => now(),
        ];
    }

    public function won(): static
    {
        return $this->state(['status' => LeadStatus::Won]);
    }

    public function lost(): static
    {
        return $this->state(['status' => LeadStatus::Lost]);
    }
}
