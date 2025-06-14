<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserApiKey>
 */
class UserApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'provider' => fake()->randomElement(['anthropic', 'openai', 'google']),
            'api_key' => 'test-api-key-' . fake()->uuid(),
            'is_active' => true,
            'last_used_at' => fake()->optional()->dateTimeThisYear(),
            'usage_stats' => [
                'total_requests' => fake()->numberBetween(0, 100),
                'last_request_at' => fake()->optional()->iso8601(),
            ],
        ];
    }
}
