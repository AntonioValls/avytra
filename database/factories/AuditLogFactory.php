<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'on_behalf_of_user_id' => null,
            'action' => 'user.created_by_admin',
            'subject_type' => User::class,
            'subject_id' => User::factory(),
            'changes' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
