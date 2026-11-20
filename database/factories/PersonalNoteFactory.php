<?php

namespace Database\Factories;

use App\Models\PersonalNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PersonalNote>
 */
class PersonalNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->optional()->words(3, true),
            'body' => fake()->paragraph(),
            'is_pinned' => false,
        ];
    }
}
