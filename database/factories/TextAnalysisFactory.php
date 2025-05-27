<?php

namespace Database\Factories;

use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TextAnalysis>
 */
class TextAnalysisFactory extends Factory
{
    protected $model = TextAnalysis::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => AnalysisJob::factory(),
            'text_id' => $this->faker->unique()->numberBetween(1, 10000),
            'content' => $this->faker->paragraph(3),
            'expert_annotations' => $this->generateExpertAnnotations(),
            'claude_annotations' => $this->generateLLMAnnotations(),
            'gemini_annotations' => $this->generateLLMAnnotations(),
            'gpt_annotations' => $this->generateLLMAnnotations(),
        ];
    }

    public function forJob(AnalysisJob $job): static
    {
        return $this->state(fn (array $attributes) => [
            'job_id' => $job->job_id,
        ]);
    }

    public function withTextId(string $textId): static
    {
        return $this->state(fn (array $attributes) => [
            'text_id' => $textId,
        ]);
    }

    public function withPropaganda(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'This is propaganda text with emotional manipulation and false claims.',
            'expert_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 8,
                        'end' => 22,
                        'text' => 'propaganda text',
                        'labels' => ['emotionalExpression', 'simplification']
                    ]
                ]
            ],
        ]);
    }

    public function withoutPropaganda(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'This is a neutral informational text without propaganda techniques.',
            'expert_annotations' => [],
        ]);
    }

    public function withComplexAnnotations(): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => 'Complex propaganda text with multiple techniques and overlapping annotations.',
            'expert_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 7,
                        'text' => 'Complex',
                        'labels' => ['simplification']
                    ]
                ],
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 8,
                        'end' => 22,
                        'text' => 'propaganda text',
                        'labels' => ['emotionalExpression', 'doubt']
                    ]
                ],
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 28,
                        'end' => 46,
                        'text' => 'multiple techniques',
                        'labels' => ['repetition']
                    ]
                ]
            ],
        ]);
    }

    private function generateExpertAnnotations(): array
    {
        $techniques = ['simplification', 'emotionalExpression', 'uncertainty', 'doubt', 'wavingTheFlag', 'reductioAdHitlerum', 'repetition'];
        $annotations = [];
        
        $numAnnotations = $this->faker->numberBetween(0, 3);
        
        for ($i = 0; $i < $numAnnotations; $i++) {
            $start = $this->faker->numberBetween(0, 50);
            $end = $start + $this->faker->numberBetween(5, 20);
            
            $annotations[] = [
                'type' => 'labels',
                'value' => [
                    'start' => $start,
                    'end' => $end,
                    'text' => $this->faker->words(3, true),
                    'labels' => $this->faker->randomElements($techniques, $this->faker->numberBetween(1, 2))
                ]
            ];
        }
        
        return $annotations;
    }

    private function generateLLMAnnotations(): array
    {
        $techniques = ['simplification', 'emotionalExpression', 'uncertainty', 'doubt', 'wavingTheFlag', 'reductioAdHitlerum', 'repetition'];
        $annotations = [];
        
        $numAnnotations = $this->faker->numberBetween(0, 3);
        
        for ($i = 0; $i < $numAnnotations; $i++) {
            $start = $this->faker->numberBetween(0, 50);
            $end = $start + $this->faker->numberBetween(5, 20);
            
            $annotations[] = [
                'type' => 'labels',
                'value' => [
                    'start' => $start,
                    'end' => $end,
                    'text' => $this->faker->words(3, true),
                    'labels' => $this->faker->randomElements($techniques, $this->faker->numberBetween(1, 2))
                ]
            ];
        }
        
        return $annotations;
    }
}