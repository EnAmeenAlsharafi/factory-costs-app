<?php

namespace Database\Factories;

use App\Models\TaskAssignee;
use App\Models\TaskRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskRequest>
 */
class TaskRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_assignee_id' => TaskAssignee::factory(),
            'type' => TaskRequest::TYPE_DECLINE,
            'reason' => fake()->sentence(),
            'status' => TaskRequest::STATUS_PENDING,
        ];
    }

    public function extension(?string $requestedDueDate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TaskRequest::TYPE_EXTEND,
            'requested_due_date' => $requestedDueDate ?? now()->addWeek()->toDateString(),
        ]);
    }
}
