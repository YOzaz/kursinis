<?php

namespace Database\Factories;

use App\Models\Experiment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Experiment>
 */
class ExperimentFactory extends Factory
{
    protected $model = Experiment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'custom_prompt' => $this->generateRisenPrompt(),
            'risen_config' => $this->generateRisenConfig(),
            'status' => $this->faker->randomElement(['draft', 'running', 'completed', 'failed']),
            'started_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'completed_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now()->subHours(2),
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subHours(4),
            'completed_at' => now()->subHour(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subHours(2),
            'completed_at' => null,
        ]);
    }

    private function generateRisenConfig(): array
    {
        return [
            'role' => 'Tu esi propagandos ir dezinformacijos analizės ekspertas.',
            'instructions' => 'Išanalizuok pateiktą tekstą ir identifikuok propagandos technikas.',
            'situation' => 'Analizuojamas tekstas gali būti socialinio tinklo įrašas arba straipsnis.',
            'execution' => 'Atlikdamas analizę perskaityk tekstą atidžiai ir įvertink technikas.',
            'needle' => 'Grąžink rezultatus JSON formatu su identifikuotomis technikomis.',
        ];
    }

    private function generateRisenPrompt(): string
    {
        $config = $this->generateRisenConfig();
        return implode("\n\n", $config);
    }
}
