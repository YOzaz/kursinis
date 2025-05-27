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
                <div class="btn-group">
                    @if($analysis->status === 'completed')
                        <a href="{{ route('api.results.get', ['jobId' => $analysis->job_id]) }}" 
                           class="btn btn-outline-info" target="_blank" title="Atsisiųsti JSON formatų">
                            <i class="fas fa-code me-1"></i>JSON
                        </a>
                        <a href="{{ route('api.results.export', ['jobId' => $analysis->job_id]) }}" 
                           class="btn btn-outline-primary" title="Atsisiųsti CSV formatų">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </a>
                    @endif
                    <a href="{{ route('analyses.index') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Grįžti į sąrašą
                    </a>
                </div>
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
                                                <th>
                                                    Modelis 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="AI modelis, kuris atliko analizę (Claude, Gemini arba GPT)"></i>
                                                </th>
                                                <th>
                                                    Propaganda sprendimas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="AI sprendimas ar tekste dominuoja propaganda (>40% teksto)"></i>
                                                </th>
                                                <th>
                                                    Anotacijų kiekis 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Kiek propagandos fragmentų AI identifikavo tekste"></i>
                                                </th>
                                                <th>
                                                    Pagrindinės kategorijos 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Propagandos technikos pagal ATSPARA metodologiją (pvz., loadedLanguage, blackAndWhite)"></i>
                                                </th>
                                                <th>
                                                    Metrikos (P/R/F1) 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Tikslumas/Atsaukimas/F1 - palyginimas su ekspertų anotacijomis (tik jei yra ekspertų duomenys)"></i>
                                                </th>
                                                <th>Veiksmai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($analysis->textAnalyses as $textAnalysis)
                                                @php
                                                    $models = [];
                                                    if($textAnalysis->claude_annotations) $models['claude-opus-4'] = $textAnalysis->claude_annotations;
                                                    if($textAnalysis->gemini_annotations) $models['gemini-2.5-pro'] = $textAnalysis->gemini_annotations;
                                                    if($textAnalysis->gpt_annotations) $models['gpt-4.1'] = $textAnalysis->gpt_annotations;
                                                @endphp
                                                
                                                @foreach($models as $modelName => $annotations)
                                                    <tr>
                                                        @if($loop->first)
                                                            <td rowspan="{{ count($models) }}">
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
                                                        @endif
                                                        <td>
                                                            <span class="badge bg-primary">{{ $modelName }}</span>
                                                        </td>
                                                        <td>
                                                            @php
                                                                $propagandaDecision = isset($annotations['primaryChoice']['choices']) && 
                                                                                     in_array('yes', $annotations['primaryChoice']['choices']);
                                                            @endphp
                                                            
                                                            @if($propagandaDecision)
                                                                <span class="badge bg-danger">Propaganda</span>
                                                            @else
                                                                <span class="badge bg-success">Ne propaganda</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @php
                                                                $annotationsCount = isset($annotations['annotations']) ? count($annotations['annotations']) : 0;
                                                            @endphp
                                                            <span class="badge bg-info">{{ $annotationsCount }}</span>
                                                        </td>
                                                        <td>
                                                            @php
                                                                $techniques = [];
                                                                if(isset($annotations['annotations'])) {
                                                                    foreach($annotations['annotations'] as $annotation) {
                                                                        if(isset($annotation['value']['labels'])) {
                                                                            $techniques = array_merge($techniques, $annotation['value']['labels']);
                                                                        }
                                                                    }
                                                                }
                                                                $techniques = array_unique($techniques);
                                                                $topTechniques = array_slice($techniques, 0, 3); // Show only top 3
                                                            @endphp
                                                            
                                                            @if(count($topTechniques) > 0)
                                                                @foreach($topTechniques as $technique)
                                                                    <span class="badge bg-secondary me-1 mb-1">{{ $technique }}</span>
                                                                @endforeach
                                                                @if(count($techniques) > 3)
                                                                    <small class="text-muted">+{{ count($techniques) - 3 }} daugiau</small>
                                                                @endif
                                                            @else
                                                                <span class="text-muted">-</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @php
                                                                $modelMetric = $textAnalysis->comparisonMetrics->where('model_name', $modelName)->first();
                                                            @endphp
                                                            
                                                            @if($modelMetric)
                                                                <small>
                                                                    <strong>P:</strong> {{ number_format($modelMetric->precision * 100, 1) }}%<br>
                                                                    <strong>R:</strong> {{ number_format($modelMetric->recall * 100, 1) }}%<br>
                                                                    <strong>F1:</strong> {{ number_format($modelMetric->f1_score * 100, 1) }}%
                                                                </small>
                                                            @else
                                                                <span class="text-muted">Nėra ekspertų anotacijų</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($loop->first)
                                                                <button class="btn btn-sm btn-outline-primary" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#analysisModal{{ $textAnalysis->id }}">
                                                                    Detalės
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
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
                                        <h6 class="text-muted">Bendras F1 balas</h6>
                                        <h3 class="text-primary">{{ number_format(($statistics['overall_metrics']['avg_f1'] ?? 0) * 100, 2) }}%</h3>
                                    </div>
                                </div>
                                <hr>
                                <dl class="row mb-0">
                                    <dt class="col-6">
                                        Tikslumas 
                                        <i class="fas fa-question-circle text-muted ms-1" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Precision - kiek AI rastų propagandos fragmentų iš tikrųjų yra propaganda. Skaičiuojama: Teisingi teigiami / (Teisingi teigiami + Klaidingi teigiami)"></i>
                                    </dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['avg_precision'] ?? 0) * 100, 2) }}%</dd>
                                    <dt class="col-6">
                                        Atsaukimas 
                                        <i class="fas fa-question-circle text-muted ms-1" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="Recall - kokią dalį visų propagandos fragmentų AI surado. Skaičiuojama: Teisingi teigiami / (Teisingi teigiami + Klaidingi neigiami)"></i>
                                    </dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['avg_recall'] ?? 0) * 100, 2) }}%</dd>
                                    <dt class="col-6">
                                        F1 balas 
                                        <i class="fas fa-question-circle text-muted ms-1" 
                                           data-bs-toggle="tooltip" 
                                           data-bs-placement="top" 
                                           title="F1 Score - suvienytas tikslumo ir atsaukimo įvertis. Harmoninė tikslumo ir atsaukimo vidurkis. Idealus F1 = 100%"></i>
                                    </dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['avg_f1'] ?? 0) * 100, 2) }}%</dd>
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
                                <div id="text-preview-{{ $textAnalysis->id }}">
                                    {{ Str::limit($textAnalysis->content, 500) }}
                                    @if(strlen($textAnalysis->content) > 500)
                                        <div class="mt-2">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="loadFullText({{ $textAnalysis->id }}, this)">
                                                <i class="fas fa-expand-alt me-1"></i>
                                                Rodyti pilną tekstą ({{ number_format(strlen($textAnalysis->content)) }} simbolių)
                                            </button>
                                        </div>
                                    @endif
                                </div>
                                <div id="text-full-{{ $textAnalysis->id }}" class="d-none">
                                    {{ $textAnalysis->content }}
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                onclick="hideFullText({{ $textAnalysis->id }}, this)">
                                            <i class="fas fa-compress-alt me-1"></i>
                                            Sutrumpinti tekstą
                                        </button>
                                    </div>
                                </div>
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
                                                <th>Modelis</th>
                                                <th>
                                                    Tikslumas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Kiek AI rastų propagandos fragmentų yra teisingi"></i>
                                                </th>
                                                <th>
                                                    Atsaukimas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Kokią dalį visų propagandos fragmentų AI surado"></i>
                                                </th>
                                                <th>
                                                    F1 balas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Bendras tikslumo ir atsaukimo įvertis"></i>
                                                </th>
                                                <th>
                                                    Pozicijos tikslumas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Kiek tiksliai AI nustatė propagandos fragmentų pozicijas tekste"></i>
                                                </th>
                                                <th>
                                                    TP/FP/FN 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Teisingi teigiami / Klaidingi teigiami / Klaidingi neigiami rezultatai"></i>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($textAnalysis->comparisonMetrics as $metric)
                                                <tr>
                                                    <td>
                                                        <strong>{{ $metric->model_name }}</strong>
                                                        @if($metric->actual_model_name && $metric->actual_model_name !== $metric->model_name)
                                                            <br><small class="text-muted">({{ $metric->actual_model_name }})</small>
                                                        @endif
                                                    </td>
                                                    <td>{{ number_format($metric->precision * 100, 2) }}%</td>
                                                    <td>{{ number_format($metric->recall * 100, 2) }}%</td>
                                                    <td>{{ number_format($metric->f1_score * 100, 2) }}%</td>
                                                    <td>{{ number_format($metric->position_accuracy * 100, 2) }}%</td>
                                                    <td>
                                                        <small>
                                                            <span class="text-success">{{ $metric->true_positives }}</span> / 
                                                            <span class="text-warning">{{ $metric->false_positives }}</span> / 
                                                            <span class="text-danger">{{ $metric->false_negatives }}</span>
                                                        </small>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <small class="text-muted">
                                    TP = True Positives (teigiami teisingi), FP = False Positives (teigiami neteisingi), FN = False Negatives (neigiami neteisingi)
                                </small>
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

// Initialize Bootstrap tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Modal text expansion functions
function loadFullText(textId, button) {
    const preview = document.getElementById('text-preview-' + textId);
    const full = document.getElementById('text-full-' + textId);
    
    // Add loading state
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Įkeliama...';
    button.disabled = true;
    
    // Simulate async loading with a small delay for better UX
    setTimeout(function() {
        preview.classList.add('d-none');
        full.classList.remove('d-none');
    }, 300);
}

function hideFullText(textId, button) {
    const preview = document.getElementById('text-preview-' + textId);
    const full = document.getElementById('text-full-' + textId);
    
    full.classList.add('d-none');
    preview.classList.remove('d-none');
}
</script>
@endsection