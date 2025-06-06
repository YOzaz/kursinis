<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ModelResult>
 */
class ModelResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => $this->faker->uuid(),
            'text_id' => $this->faker->uuid(), 
            'model_key' => $this->faker->randomElement(['claude-sonnet-4', 'gpt-4.1', 'gemini-2.5-pro']),
            'provider' => $this->faker->randomElement(['anthropic', 'openai', 'google']),
            'model_name' => $this->faker->randomElement(['Claude', 'GPT-4', 'Gemini']),
            'actual_model_name' => $this->faker->randomElement(['claude-sonnet-4-20250514', 'gpt-4.1', 'gemini-2.5-pro-experimental']),
            'annotations' => [
                'primaryChoice' => [
                    'choices' => [$this->faker->randomElement(['yes', 'no'])]
                ],
                'annotations' => [],
                'desinformationTechnique' => [
                    'choices' => []
                ]
            ],
            'error_message' => null,
            'execution_time_ms' => $this->faker->numberBetween(1000, 10000),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
        ];
    }
}
