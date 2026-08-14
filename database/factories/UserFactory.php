<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'telegram_id' => fake()->unique()->numberBetween(1, 9_000_000_000),
            'telegram_username' => fake()->optional()->userName(),
            'first_name' => fake()->optional()->firstName(),
            'preferred_name' => fake()->optional()->firstName(),
            'language_code' => fake()->optional()->languageCode(),
            'timezone' => fake()->optional()->timezone(),
            'status' => 'active',
            'is_adult_confirmed' => false,
            'last_seen_at' => fake()->optional()->dateTimeBetween('-1 month'),
        ];
    }
}
