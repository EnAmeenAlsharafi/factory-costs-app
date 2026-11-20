<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'details' => fake()->optional()->paragraph(),
            'due_date' => null,
            'created_by_user_id' => User::factory(),
            'is_assigned' => false,
        ];
    }

    /**
     * Written for other people rather than as a personal to-do.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => ['is_assigned' => true]);
    }
}
