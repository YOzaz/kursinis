@extends('layout')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Analizės detalės</h1>
                    <p class="text-muted">Analizės ID: {{ $analysis->job_id }}</p>
                </div>
                <a href="{{ route('analyses.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Grįžti į sąrašą
                </a>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Analizės informacija</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Tipas:</dt>
                                        <dd class="col-sm-8">
                                            @if($analysis->reference_analysis_id)
                                                <span class="badge bg-info">Pakartotinė analizė</span>
                                            @elseif($analysis->usesCustomPrompt())
                                                <span class="badge bg-warning">Custom prompt</span>
                                            @else
                                                <span class="badge bg-primary">Standartinė analizė</span>
                                            @endif
                                        </dd>
                                        <dt class="col-sm-4">Statusas:</dt>
                                        <dd class="col-sm-8">
                                            @switch($analysis->status)
                                                @case('completed')
                                                    <span class="badge bg-success">Baigta</span>
                                                    @break
                                                @case('failed')
                                                    <span class="badge bg-danger">Nepavyko</span>
                                                    @break
                                                @case('processing')
                                                    <span class="badge bg-warning">Vykdoma</span>
                                                    @break
                                                @default
                                                    <span class="badge bg-secondary">{{ ucfirst($analysis->status) }}</span>
                                            @endswitch
                                        </dd>
                                        @if($analysis->name)
                                            <dt class="col-sm-4">Pavadinimas:</dt>
                                            <dd class="col-sm-8">{{ $analysis->name }}</dd>
                                        @endif
                                        @if($analysis->reference_analysis_id)
                                            <dt class="col-sm-4">Nuoroda:</dt>
                                            <dd class="col-sm-8">
                                                <a href="{{ route('analyses.show', $analysis->reference_analysis_id) }}">
                                                    {{ $analysis->reference_analysis_id }}
                                                </a>
                                            </dd>
                                        @endif
                                        <dt class="col-sm-4">Sukurta:</dt>
                                        <dd class="col-sm-8">{{ $analysis->created_at->format('Y-m-d H:i:s') }}</dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Tekstų kiekis:</dt>
                                        <dd class="col-sm-8">{{ $analysis->textAnalyses->count() }}</dd>
                                        @if($analysis->description)
                                            <dt class="col-sm-4">Aprašymas:</dt>
                                            <dd class="col-sm-8">{{ $analysis->description }}</dd>
                                        @endif
                                        @if($analysis->usesCustomPrompt())
                                            <dt class="col-sm-4">Custom prompt:</dt>
                                            <dd class="col-sm-8">
                                                <details>
                                                    <summary class="btn btn-sm btn-outline-secondary">Peržiūrėti</summary>
                                                    <div class="mt-2 p-2 bg-light rounded">
                                                        <pre class="small mb-0">{{ $analysis->custom_prompt }}</pre>
                                                    </div>
                                                </details>
                                            </dd>
                                        @endif
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($analysis->status === 'completed' && $analysis->textAnalyses->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Analizės rezultatai</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tekstas</th>
                                                <th>Modelis</th>
                                                <th>Propaganda sprendimas</th>
                                                <th>Patikimumas</th>
                                                <th>Kategorijos</th>
                                                <th>Ekspertų palyginimas</th>
                                                <th>Veiksmai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($analysis->textAnalyses as $textAnalysis)
                                                <tr>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 200px;">
                                                            <span class="text-preview" data-text-id="{{ $textAnalysis->id }}">
                                                                {{ Str::limit($textAnalysis->content, 50) }}
                                                            </span>
                                                            <button class="btn btn-sm btn-link p-0 ms-1" onclick="toggleFullText({{ $textAnalysis->id }})">
                                                                <i class="fas fa-expand-alt"></i>
                                                            </button>
                                                            <div class="full-text d-none" id="full-text-{{ $textAnalysis->id }}">
                                                                {{ $textAnalysis->content }}
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->comparisonMetrics->isNotEmpty())
                                                            @foreach($textAnalysis->comparisonMetrics->unique('model_name') as $metric)
                                                                <span class="badge bg-secondary me-1">{{ $metric->model_name }}</span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            // Check AI propaganda decision
                                                            $aiHasPropaganda = false;
                                                            if($textAnalysis->claude_annotations && isset($textAnalysis->claude_annotations['primaryChoice']['choices'])) {
                                                                $aiHasPropaganda = in_array('yes', $textAnalysis->claude_annotations['primaryChoice']['choices']);
                                                            } elseif($textAnalysis->gemini_annotations && isset($textAnalysis->gemini_annotations['primaryChoice']['choices'])) {
                                                                $aiHasPropaganda = in_array('yes', $textAnalysis->gemini_annotations['primaryChoice']['choices']);
                                                            } elseif($textAnalysis->gpt_annotations && isset($textAnalysis->gpt_annotations['primaryChoice']['choices'])) {
                                                                $aiHasPropaganda = in_array('yes', $textAnalysis->gpt_annotations['primaryChoice']['choices']);
                                                            }
                                                            
                                                            // Check expert decision
                                                            $expertHasPropaganda = false;
                                                            if($textAnalysis->expert_annotations && count($textAnalysis->expert_annotations) > 0) {
                                                                foreach($textAnalysis->expert_annotations as $annotation) {
                                                                    if(isset($annotation['result'])) {
                                                                        foreach($annotation['result'] as $result) {
                                                                            if($result['type'] === 'choices' && 
                                                                               isset($result['value']['choices']) && 
                                                                               in_array('yes', $result['value']['choices'])) {
                                                                                $expertHasPropaganda = true;
                                                                                break 2;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        @endphp
                                                        
                                                        @if($aiHasPropaganda)
                                                            <span class="badge bg-danger">Propaganda (AI)</span>
                                                        @else
                                                            <span class="badge bg-success">Ne propaganda (AI)</span>
                                                        @endif
                                                        
                                                        @if($textAnalysis->expert_annotations && count($textAnalysis->expert_annotations) > 0)
                                                            <br>
                                                            @if($expertHasPropaganda)
                                                                <span class="badge bg-danger">Propaganda (ekspertai)</span>
                                                            @else
                                                                <span class="badge bg-success">Ne propaganda (ekspertai)</span>
                                                            @endif
                                                            
                                                            @if($aiHasPropaganda === $expertHasPropaganda)
                                                                <br><small class="text-success">✓ Sutampa</small>
                                                            @else
                                                                <br><small class="text-danger">✗ Nesutampa</small>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->comparisonMetrics->isNotEmpty())
                                                            @php
                                                                $avgConfidence = $textAnalysis->comparisonMetrics->avg(function($metric) {
                                                                    return (float)$metric->precision;
                                                                });
                                                            @endphp
                                                            <div class="progress" style="width: 100px;">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: {{ $avgConfidence * 100 }}%">
                                                                    {{ round($avgConfidence * 100) }}%
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @php
                                                            // AI techniques
                                                            $aiTechniques = [];
                                                            $annotations = $textAnalysis->claude_annotations ?? $textAnalysis->gemini_annotations ?? $textAnalysis->gpt_annotations ?? null;
                                                            if($annotations && isset($annotations['annotations'])) {
                                                                foreach($annotations['annotations'] as $annotation) {
                                                                    if(isset($annotation['value']['labels'])) {
                                                                        $aiTechniques = array_merge($aiTechniques, $annotation['value']['labels']);
                                                                    }
                                                                }
                                                            }
                                                            $aiTechniques = array_unique($aiTechniques);
                                                            
                                                            // Expert techniques
                                                            $expertTechniques = [];
                                                            if($textAnalysis->expert_annotations && count($textAnalysis->expert_annotations) > 0) {
                                                                foreach($textAnalysis->expert_annotations as $annotation) {
                                                                    if(isset($annotation['result'])) {
                                                                        foreach($annotation['result'] as $result) {
                                                                            if($result['type'] === 'labels' && isset($result['value']['labels'])) {
                                                                                $expertTechniques = array_merge($expertTechniques, $result['value']['labels']);
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            $expertTechniques = array_unique($expertTechniques);
                                                        @endphp
                                                        
                                                        @if(count($aiTechniques) > 0)
                                                            <strong>AI:</strong><br>
                                                            @foreach($aiTechniques as $technique)
                                                                <span class="badge bg-primary me-1 mb-1">{{ $technique }}</span>
                                                            @endforeach
                                                        @endif
                                                        
                                                        @if(count($expertTechniques) > 0)
                                                            @if(count($aiTechniques) > 0)<br>@endif
                                                            <strong>Ekspertai:</strong><br>
                                                            @foreach($expertTechniques as $technique)
                                                                <span class="badge bg-info me-1 mb-1">{{ $technique }}</span>
                                                            @endforeach
                                                        @endif
                                                        
                                                        @if(count($aiTechniques) === 0 && count($expertTechniques) === 0)
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->comparisonMetrics->isNotEmpty())
                                                            @php
                                                                $avgPrecision = $textAnalysis->comparisonMetrics->avg(function($metric) { return (float)$metric->precision; });
                                                                $avgRecall = $textAnalysis->comparisonMetrics->avg(function($metric) { return (float)$metric->recall; });
                                                                $avgF1 = $textAnalysis->comparisonMetrics->avg(function($metric) { return (float)$metric->f1_score; });
                                                            @endphp
                                                            <small class="text-muted">
                                                                P: {{ number_format($avgPrecision, 2) }}<br>
                                                                R: {{ number_format($avgRecall, 2) }}<br>
                                                                F1: {{ number_format($avgF1, 2) }}
                                                            </small>
                                                        @else
                                                            <span class="text-muted">Nėra palyginimo</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#analysisModal{{ $textAnalysis->id }}">
                                                            Detalės
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    @if(isset($statistics))
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Statistikos</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-12 mb-3">
                                        <h6 class="text-muted">Bendras tikslumas</h6>
                                        <h3 class="text-primary">{{ number_format(($statistics['overall_metrics']['accuracy'] ?? 0) * 100, 1) }}%</h3>
                                    </div>
                                </div>
                                <hr>
                                <dl class="row mb-0">
                                    <dt class="col-6">Tikslumas:</dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['precision'] ?? 0) * 100, 1) }}%</dd>
                                    <dt class="col-6">Atsaukimas:</dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['recall'] ?? 0) * 100, 1) }}%</dd>
                                    <dt class="col-6">F1 balas:</dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['f1_score'] ?? 0) * 100, 1) }}%</dd>
                                </dl>
                            </div>
                        </div>
                    @endif

                    @if($analysis->status === 'failed' && $analysis->error_message)
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">Klaidos pranešimas</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">{{ $analysis->error_message }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($analysis->status === 'completed' && $analysis->textAnalyses->isNotEmpty())
    @foreach($analysis->textAnalyses as $textAnalysis)
        <div class="modal fade" id="analysisModal{{ $textAnalysis->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Analizės detalės</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Originalus tekstas:</label>
                            <div class="border p-3 rounded bg-light">
                                {{ $textAnalysis->content }}
                            </div>
                        </div>
                        
                        @php
                            $modelAnnotations = [
                                'Claude' => ['annotations' => $textAnalysis->claude_annotations, 'actual_model' => $textAnalysis->claude_actual_model],
                                'Gemini' => ['annotations' => $textAnalysis->gemini_annotations, 'actual_model' => $textAnalysis->gemini_actual_model],
                                'GPT' => ['annotations' => $textAnalysis->gpt_annotations, 'actual_model' => $textAnalysis->gpt_actual_model],
                            ];
                        @endphp
                        
                        @foreach($modelAnnotations as $modelName => $data)
                            @if($data['annotations'])
                                <div class="mb-3">
                                    <label class="form-label fw-bold">
                                        {{ $modelName }} rezultatas 
                                        @if($data['actual_model'])
                                            <small class="text-muted">({{ $data['actual_model'] }})</small>
                                        @endif
                                    </label>
                                    <div class="border p-3 rounded">
                                        @if(isset($data['annotations']['primaryChoice']))
                                            <div class="mb-2">
                                                <strong>Propaganda sprendimas:</strong>
                                                @if(in_array('yes', $data['annotations']['primaryChoice']['choices'] ?? []))
                                                    <span class="badge bg-danger">Propaganda</span>
                                                @else
                                                    <span class="badge bg-success">Ne propaganda</span>
                                                @endif
                                            </div>
                                        @endif
                                        
                                        @if(isset($data['annotations']['annotations']))
                                            <div class="mb-2">
                                                <strong>Aptikti metodai:</strong>
                                                @foreach($data['annotations']['annotations'] as $annotation)
                                                    @if(isset($annotation['value']['labels']))
                                                        @foreach($annotation['value']['labels'] as $label)
                                                            <span class="badge bg-secondary me-1">{{ $label }}</span>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                        
                                        <details class="mt-2">
                                            <summary class="btn btn-sm btn-outline-secondary">Pilnas atsakymas</summary>
                                            <pre class="mt-2 small">{{ json_encode($data['annotations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if($textAnalysis->comparisonMetrics->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label fw-bold">Palyginimo metrikos:</label>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Metrika</th>
                                                <th>AI sprendimas</th>
                                                <th>Ekspertų sprendimas</th>
                                                <th>Sutampa</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($textAnalysis->comparisonMetrics as $metric)
                                                <tr>
                                                    <td>{{ $metric->metric_name }}</td>
                                                    <td>{{ $metric->ai_value }}</td>
                                                    <td>{{ $metric->expert_value }}</td>
                                                    <td>
                                                        @if($metric->matches)
                                                            <span class="badge bg-success">Taip</span>
                                                        @else
                                                            <span class="badge bg-danger">Ne</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif

<script>
function toggleFullText(textId) {
    const preview = document.querySelector(`[data-text-id="${textId}"]`);
    const fullText = document.getElementById(`full-text-${textId}`);
    const button = preview.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (fullText.classList.contains('d-none')) {
        fullText.classList.remove('d-none');
        preview.classList.add('d-none');
        icon.className = 'fas fa-compress-alt';
        button.title = 'Sutrumpinti';
    } else {
        fullText.classList.add('d-none');
        preview.classList.remove('d-none');
        icon.className = 'fas fa-expand-alt';
        button.title = 'Išplėsti';
    }
}
</script>
@endsection