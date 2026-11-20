<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskAssignee>
 */
class TaskAssigneeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'status' => TaskAssignee::STATUS_OPEN,
            'due_date' => null,
            'is_important' => false,
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TaskAssignee::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }
}
