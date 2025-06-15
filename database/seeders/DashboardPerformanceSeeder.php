<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\ModelResult;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardPerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds to create dummy data for dashboard performance testing.
     */
    public function run(): void
    {
        $this->command->info('Creating dummy data for dashboard performance testing...');
        
        $models = [
            'claude-opus-4',
            'claude-sonnet-4',
            'gpt-4.1',
            'gpt-4o-latest',
            'gemini-2.5-pro',
            'gemini-2.5-flash'
        ];
        
        $propagandaTechniques = [
            'Loaded Language',
            'Name Calling/Labeling',
            'Repetition',
            'Exaggeration/Minimisation',
            'Doubt',
            'Appeal to fear/prejudice',
            'Flag-Waving',
            'Causal Oversimplification',
            'Slogans',
            'Appeal to authority',
            'Black-and-White Fallacy',
            'Thought-terminating cliché',
            'Whataboutism',
            'Reductio ad hitlerum',
            'Red Herring',
            'Bandwagon',
            'Obfuscation, Intentional vagueness, Confusion',
            'Straw Man',
            'Presenting Irrelevant Data',
            'Smears'
        ];
        
        // Create 100 analysis jobs over the last 30 days
        for ($i = 0; $i < 100; $i++) {
            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            $job = AnalysisJob::create([
                'job_id' => (string) Str::uuid(),
                'name' => 'Performance Test Batch ' . ($i + 1),
                'status' => 'completed',
                'total_texts' => rand(10, 50),
                'processed_texts' => rand(10, 50),
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addMinutes(rand(5, 60)),
            ]);
            
            $this->command->info('Created job ' . $job->job_id . ' (' . ($i + 1) . '/100)');
            
            // Create text analyses for this job
            $textCount = $job->total_texts;
            for ($j = 0; $j < $textCount; $j++) {
                $textId = (string) Str::uuid();
                $isPropagandaText = rand(0, 100) < 70; // 70% chance of propaganda
                
                // Create expert annotations
                $expertAnnotations = [];
                if ($isPropagandaText) {
                    $numTechniques = rand(1, 3);
                    $selectedTechniques = array_rand(array_flip($propagandaTechniques), $numTechniques);
                    if (!is_array($selectedTechniques)) {
                        $selectedTechniques = [$selectedTechniques];
                    }
                    
                    $expertAnnotations = [
                        [
                            'result' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => rand(0, 100),
                                        'end' => rand(101, 500),
                                        'text' => 'Sample propaganda text fragment',
                                        'labels' => $selectedTechniques
                                    ]
                                ],
                                [
                                    'type' => 'choices',
                                    'value' => ['choices' => ['yes']]
                                ]
                            ]
                        ]
                    ];
                } else {
                    $expertAnnotations = [
                        [
                            'result' => [
                                [
                                    'type' => 'choices',
                                    'value' => ['choices' => ['no']]
                                ]
                            ]
                        ]
                    ];
                }
                
                $textAnalysis = TextAnalysis::create([
                    'job_id' => $job->job_id,
                    'text_id' => $textId,
                    'content' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' . str_repeat('Sample text content. ', rand(50, 200)),
                    'expert_annotations' => $expertAnnotations,
                    'created_at' => $job->created_at,
                    'updated_at' => $job->created_at,
                ]);
                
                // Create model results and comparison metrics for each model
                foreach ($models as $model) {
                    $modelFoundPropaganda = $isPropagandaText ? (rand(0, 100) < 85) : (rand(0, 100) < 15);
                    
                    // Create model result
                    $executionTime = match(true) {
                        str_starts_with($model, 'claude') => rand(1000, 5000),
                        str_starts_with($model, 'gpt') => rand(800, 4000),
                        str_starts_with($model, 'gemini') => rand(500, 3000),
                        default => rand(1000, 3000)
                    };
                    
                    // Create model annotations
                    $modelAnnotations = [];
                    if ($modelFoundPropaganda && $isPropagandaText) {
                        // Model correctly found propaganda
                        $numTechniques = rand(1, 2);
                        $selectedTechniques = array_rand(array_flip($propagandaTechniques), $numTechniques);
                        if (!is_array($selectedTechniques)) {
                            $selectedTechniques = [$selectedTechniques];
                        }
                        
                        $modelAnnotations = [
                            'primaryChoice' => ['choices' => ['yes']],
                            'annotations' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => rand(0, 100),
                                        'end' => rand(101, 400),
                                        'text' => 'Model detected propaganda fragment',
                                        'labels' => $selectedTechniques
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $modelAnnotations = [
                            'primaryChoice' => ['choices' => ['no']],
                            'annotations' => []
                        ];
                    }
                    
                    ModelResult::create([
                        'job_id' => $job->job_id,
                        'text_id' => $textId,
                        'model_key' => $model,
                        'provider' => match(true) {
                            str_starts_with($model, 'claude') => 'anthropic',
                            str_starts_with($model, 'gpt') => 'openai',
                            str_starts_with($model, 'gemini') => 'google',
                            default => 'unknown'
                        },
                        'status' => 'completed',
                        'annotations' => $modelAnnotations,
                        'execution_time_ms' => $executionTime,
                        'created_at' => $job->created_at,
                        'updated_at' => $job->created_at,
                    ]);
                    
                    // Create comparison metric
                    if ($isPropagandaText) {
                        // Only create metrics for propaganda texts
                        $precision = rand(60, 95) / 100;
                        $recall = rand(55, 90) / 100;
                        $f1Score = 2 * ($precision * $recall) / ($precision + $recall);
                        
                        ComparisonMetric::create([
                            'job_id' => $job->job_id,
                            'text_id' => $textId,
                            'model_name' => $model,
                            'precision' => $precision,
                            'recall' => $recall,
                            'f1_score' => $f1Score,
                            'position_accuracy' => rand(70, 95) / 100,
                            'analysis_execution_time_ms' => $executionTime,
                            'created_at' => $job->created_at,
                            'updated_at' => $job->created_at,
                        ]);
                    }
                }
            }
        }
        
        $this->command->info('Dummy data creation completed!');
        $this->command->info('Total jobs created: 100');
        $this->command->info('Average texts per job: ' . ($job->total_texts ?? 30));
        $this->command->info('Total comparison metrics: ' . ComparisonMetric::count());
    }
}