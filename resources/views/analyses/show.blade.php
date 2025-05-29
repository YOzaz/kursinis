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
                    @if($analysis->status === 'completed')
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#repeatAnalysisModal">
                            <i class="fas fa-redo me-2"></i>Pakartoti analizę
                        </button>
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

                    @if($analysis->status === 'completed' && $textAnalyses->isNotEmpty())
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Analizės rezultatai</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="analysisResultsTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 35%;">Tekstas</th>
                                                <th style="width: 15%;">
                                                    Modeliai
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="AI modelių skaičius, analizavęs tekstą"></i>
                                                </th>
                                                <th style="width: 20%;">
                                                    Bendrieji rezultatai
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Suvestinė visų modelių sprendimų ir rastų technikų"></i>
                                                </th>
                                                <th style="width: 15%;">
                                                    Vidutinės metrikos
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="P/R/F1 vidurkis visų modelių, jei yra ekspertų duomenys"></i>
                                                </th>
                                                <th style="width: 15%;">Veiksmai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($textAnalyses as $textAnalysis)
                                                @php
                                                    $models = $textAnalysis->getAllModelAnnotations();
                                                    $totalModels = count($models);
                                                    
                                                    // Apskaičiuoti suvestines metrikas
                                                    $textMetrics = $textAnalysis->comparisonMetrics;
                                                    $avgPrecision = $textMetrics->avg('precision') ?? 0;
                                                    $avgRecall = $textMetrics->avg('recall') ?? 0;
                                                    $avgF1 = $textMetrics->avg('f1_score') ?? 0;
                                                    
                                                    // Check if we have expert annotations
                                                    $hasExpertAnnotations = !empty($textAnalysis->expert_annotations);
                                                    
                                                    // Propagandos sprendimai
                                                    $propagandaCount = 0;
                                                    $allTechniques = [];
                                                    
                                                    foreach($models as $annotations) {
                                                        if(isset($annotations['primaryChoice']['choices']) && 
                                                           in_array('yes', $annotations['primaryChoice']['choices'])) {
                                                            $propagandaCount++;
                                                        }
                                                        
                                                        if(isset($annotations['annotations'])) {
                                                            foreach($annotations['annotations'] as $annotation) {
                                                                if(isset($annotation['value']['labels'])) {
                                                                    $allTechniques = array_merge($allTechniques, $annotation['value']['labels']);
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    $topTechniques = array_slice(array_unique($allTechniques), 0, 5);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 300px;">
                                                            <span class="text-preview" data-text-id="{{ $textAnalysis->id }}">
                                                                {{ Str::limit($textAnalysis->content, 100) }}
                                                            </span>
                                                            <button class="btn btn-sm btn-link p-0 ms-1" onclick="toggleFullText({{ $textAnalysis->id }})">
                                                                <i class="fas fa-expand-alt"></i>
                                                            </button>
                                                            <div class="full-text d-none" id="full-text-{{ $textAnalysis->id }}">
                                                                {{ $textAnalysis->content }}
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mt-1">ID: {{ $textAnalysis->text_id }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                                            @foreach($models as $modelName => $annotations)
                                                                <span class="badge bg-primary">{{ $modelName }}</span>
                                                            @endforeach
                                                        </div>
                                                        <small class="text-muted">{{ $totalModels }} modeliai</small>
                                                    </td>
                                                    <td>
                                                        <div class="mb-2">
                                                            <strong>Propaganda:</strong> 
                                                            @if($propagandaCount > 0)
                                                                <span class="badge bg-danger">{{ $propagandaCount }}/{{ $totalModels }}</span>
                                                            @else
                                                                <span class="badge bg-success">0/{{ $totalModels }}</span>
                                                            @endif
                                                        </div>
                                                        @if(count($topTechniques) > 0)
                                                            <div>
                                                                <strong>Technikos:</strong>
                                                                <div class="mt-1">
                                                                    @foreach($topTechniques as $technique)
                                                                        <span class="badge bg-secondary me-1 mb-1">{{ $technique }}</span>
                                                                    @endforeach
                                                                    @if(count($allTechniques) > 5)
                                                                        <small class="text-muted">+{{ count($allTechniques) - 5 }}</small>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @else
                                                            <small class="text-muted">Technikų nerasta</small>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($textMetrics->isNotEmpty())
                                                            @if($hasExpertAnnotations)
                                                                <small>
                                                                    <strong>P:</strong> {{ number_format($avgPrecision * 100, 1) }}%<br>
                                                                    <strong>R:</strong> {{ number_format($avgRecall * 100, 1) }}%<br>
                                                                    <strong>F1:</strong> {{ number_format($avgF1 * 100, 1) }}%
                                                                </small>
                                                            @else
                                                                <small class="text-muted">
                                                                    <i class="fas fa-info-circle"></i> Nėra ekspertų anotacijų
                                                                </small>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">Nėra metrikų</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#analysisModal{{ $textAnalysis->id }}">
                                                            <i class="fas fa-eye me-1"></i>Detalės
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div>
                                        <small class="text-muted">
                                            Rodoma {{ $textAnalyses->firstItem() }}-{{ $textAnalyses->lastItem() }} 
                                            iš {{ $textAnalyses->total() }} tekstų
                                        </small>
                                    </div>
                                    <div>
                                        {{ $textAnalyses->links() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Text Highlighting Section -->
            @if($analysis->status === 'completed' && $textAnalyses->count() > 0)
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-highlighter me-2"></i>Tekstų analizė
                                </h5>
                                <div class="btn-group view-toggle" role="group">
                                    <input type="radio" class="btn-check" name="viewType" id="ai-view" value="ai" checked>
                                    <label class="btn btn-outline-primary" for="ai-view">AI anotacijos</label>
                                    
                                    <input type="radio" class="btn-check" name="viewType" id="expert-view" value="expert">
                                    <label class="btn btn-outline-secondary" for="expert-view">Ekspertų anotacijos</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Legend -->
                                <div class="legend mb-3" id="annotation-legend" style="display: none;">
                                    <h6>Propagandos technikų legenda:</h6>
                                    <div class="row" id="legend-items">
                                        <!-- Legend items will be populated by JavaScript -->
                                    </div>
                                </div>

                                <!-- Text Display Area -->
                                <div class="text-analysis-container">
                                    <div class="form-group mb-3">
                                        <label for="text-selector">Pasirinkite tekstą analizei:</label>
                                        <select class="form-select" id="text-selector">
                                            @foreach($textAnalyses as $textAnalysis)
                                                <option value="{{ $textAnalysis->id }}" data-text-id="{{ $textAnalysis->text_id }}">
                                                    Tekstas {{ $textAnalysis->text_id }}
                                                    @if(strlen($textAnalysis->content) > 50)
                                                        - {{ Str::limit($textAnalysis->content, 50) }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="highlighted-text-container">
                                        <div id="highlighted-text" class="border p-3 rounded bg-light">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-arrow-up me-2"></i>Pasirinkite tekstą viršuje
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="loading-spinner text-center" id="loading-spinner" style="display: none;">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Kraunama...</span>
                                            </div>
                                            <p class="mt-2">Kraunamos anotacijos...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- This empty column balances the layout -->
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

@if($analysis->status === 'completed' && $textAnalyses->isNotEmpty())
    @foreach($textAnalyses as $textAnalysis)
        <div class="modal fade" id="analysisModal{{ $textAnalysis->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Analizės detalės - Tekstas ID: {{ $textAnalysis->text_id }}</h5>
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
                            $allModelAnnotations = $textAnalysis->getAllModelAnnotations();
                            
                            // Taip pat pridėti modelius iš metrikų, jei jų anotacijos buvo perrašytos
                            $metricsModels = [];
                            foreach($textAnalysis->comparisonMetrics as $metric) {
                                if (!isset($allModelAnnotations[$metric->model_name])) {
                                    $metricsModels[$metric->model_name] = null; // Anotacijos nebeegzistuoja
                                }
                            }
                            
                            $allModelsToShow = array_merge($allModelAnnotations, $metricsModels);
                        @endphp
                        
                        @foreach($allModelsToShow as $modelName => $annotations)
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    {{ $modelName }} rezultatas
                                    @php
                                        // Get actual model name from metrics if annotations don't exist
                                        $actualModelName = null;
                                        $metricForModel = $textAnalysis->comparisonMetrics->firstWhere('model_name', $modelName);
                                        if ($metricForModel && $metricForModel->actual_model_name) {
                                            $actualModelName = $metricForModel->actual_model_name;
                                        } else {
                                            // Fallback to direct fields
                                            if (str_contains(strtolower($modelName), 'claude')) {
                                                $actualModelName = $textAnalysis->claude_actual_model;
                                            } elseif (str_contains(strtolower($modelName), 'gemini')) {
                                                $actualModelName = $textAnalysis->gemini_actual_model;
                                            } elseif (str_contains(strtolower($modelName), 'gpt')) {
                                                $actualModelName = $textAnalysis->gpt_actual_model;
                                            }
                                        }
                                    @endphp
                                    @if($actualModelName)
                                        <small class="text-muted">({{ $actualModelName }})</small>
                                    @endif
                                </label>
                                <div class="border p-3 rounded">
                                    @if($annotations === null)
                                        <div class="alert alert-warning mb-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Anotacijos nebesaugomas:</strong> Šio modelio anotacijos buvo perrašytos kito to paties tiekėjo modelio. 
                                            Metrikos išlieka žemiau esančioje lentelėje.
                                        </div>
                                    @else
                                        @if(isset($annotations['primaryChoice']))
                                            <div class="mb-2">
                                                <strong>Propaganda sprendimas:</strong>
                                                @if(in_array('yes', $annotations['primaryChoice']['choices'] ?? []))
                                                    <span class="badge bg-danger">Propaganda</span>
                                                @else
                                                    <span class="badge bg-success">Ne propaganda</span>
                                                @endif
                                            </div>
                                        @endif
                                        
                                        @if(isset($annotations['annotations']))
                                            <div class="mb-2">
                                                <strong>Aptikti metodai:</strong>
                                                @foreach($annotations['annotations'] as $annotation)
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
                                            <pre class="mt-2 small">{{ json_encode($annotations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        @if($textAnalysis->comparisonMetrics->isNotEmpty())
                            <div class="mb-3">
                                <label class="form-label fw-bold">Palyginimo metrikos:</label>
                                @if(empty($textAnalysis->expert_annotations))
                                    <div class="alert alert-info mt-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Pastaba:</strong> Šiam tekstui nėra ekspertų anotacijų, todėl metrikų tikslumas negali būti apskaičiuotas. 
                                        Rodomi 0% rezultatai reiškia, kad nėra palyginimo duomenų.
                                    </div>
                                @endif
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
                                                <th>
                                                    Vykdymo laikas
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Kiek laiko užtruko šio modelio analizė"></i>
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
                                                    <td>
                                                        @if($metric->analysis_execution_time_ms)
                                                            @if($metric->analysis_execution_time_ms < 1000)
                                                                {{ $metric->analysis_execution_time_ms }}ms
                                                            @else
                                                                {{ number_format($metric->analysis_execution_time_ms / 1000, 1) }}s
                                                            @endif
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
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

// Initialize Bootstrap tooltips and DataTables
$(document).ready(function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize DataTable for analysis results
    if ($('#analysisResultsTable').length) {
        $('#analysisResultsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[0, 'asc']], // Sort by text content
            columnDefs: [
                { 
                    targets: [0], // Text column
                    type: 'string'
                },
                {
                    targets: [1, 2, 3, 4, 5], // Other columns
                    orderable: true
                },
                {
                    targets: [-1], // Last column (actions)
                    orderable: false
                }
            ],
            language: {
                "search": "Ieškoti:",
                "lengthMenu": "Rodyti _MENU_ eilučių puslapyje",
                "info": "Rodoma _START_ - _END_ iš _TOTAL_ eilučių",
                "infoEmpty": "Nėra duomenų",
                "infoFiltered": "(išfiltruota iš _MAX_ eilučių)",
                "paginate": {
                    "first": "Pirmas",
                    "last": "Paskutinis",
                    "next": "Kitas",
                    "previous": "Ankstesnis"
                },
                "emptyTable": "Nėra duomenų lentelėje"
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        });
    }
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
        
        // Reset button
        button.innerHTML = 'Sutrumpinti tekstą';
        button.disabled = false;
        button.onclick = function() { hideFullText(textId, button); };
    }, 300);
}

function hideFullText(textId, button) {
    const preview = document.getElementById('text-preview-' + textId);
    const full = document.getElementById('text-full-' + textId);
    
    full.classList.add('d-none');
    preview.classList.remove('d-none');
    
    // Reset button
    button.innerHTML = 'Išplėsti tekstą';
    button.onclick = function() { loadFullText(textId, button); };
}
</script>

<!-- Repeat Analysis Modal -->
<div class="modal fade" id="repeatAnalysisModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pakartoti analizę</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('analysis.repeat') }}" method="POST" id="repeatAnalysisForm">
                @csrf
                <input type="hidden" name="reference_job_id" value="{{ $analysis->job_id }}">
                
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Pakartotinė analizė</strong> naudos tuos pačius tekstus ir pasirinktus modelius, bet galėsite modifikuoti prompt'ą.
                    </div>

                    <!-- Current analysis info -->
                    <div class="card bg-light mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">Dabartinės analizės informacija</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Faktinis tekstų skaičius:</strong> {{ $actualTextCount }}<br>
                                    <strong>Analizės darbų skaičius:</strong> {{ $analysis->total_texts }}<br>
                                    <small class="text-muted">({{ $actualTextCount }} tekstas × {{ $modelCount }} modeliai = {{ $analysis->total_texts }} darbai)</small><br><br>
                                    <strong>Modeliai:</strong> 
                                    @foreach($usedModels as $model)
                                        <span class="badge bg-primary me-1">{{ $model }}</span>
                                    @endforeach
                                </div>
                                <div class="col-md-6">
                                    <strong>Prompt tipas:</strong>
                                    @if($analysis->custom_prompt)
                                        <span class="badge bg-warning">Custom prompt</span>
                                    @else
                                        <span class="badge bg-primary">Standartinis</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- New analysis configuration -->
                    <h6>Naujos analizės konfigūracija</h6>

                    <!-- Prompt configuration -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Prompt konfigūracija</label>
                        
                        <div class="mb-3">
                            @if($analysis->custom_prompt)
                                <!-- When original analysis used custom prompt, show all options -->
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="prompt_type" id="keep_prompt" value="keep" checked>
                                    <label class="form-check-label" for="keep_prompt">
                                        <strong>Naudoti tą patį custom prompt'ą</strong>
                                        <small class="d-block text-muted">Pakartoti su dabartinio analizės custom prompt'u</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="prompt_type" id="standard_repeat" value="standard">
                                    <label class="form-check-label" for="standard_repeat">
                                        <strong>Pereiti į standartinį ATSPARA prompt'ą</strong>
                                        <small class="d-block text-muted">Naudoti sistemos numatytąjį prompt'ą</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="prompt_type" id="modify_repeat" value="custom">
                                    <label class="form-check-label" for="modify_repeat">
                                        <strong>Modifikuoti prompt'ą</strong>
                                        <small class="d-block text-muted">Keisti esamą arba sukurti naują custom prompt'ą</small>
                                    </label>
                                </div>
                            @else
                                <!-- When original analysis used standard prompt, only show relevant options -->
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="prompt_type" id="standard_repeat" value="standard" checked>
                                    <label class="form-check-label" for="standard_repeat">
                                        <strong>Naudoti standartinį ATSPARA prompt'ą</strong>
                                        <small class="d-block text-muted">Pakartoti su tuo pačiu standartiniu prompt'u</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="prompt_type" id="modify_repeat" value="custom">
                                    <label class="form-check-label" for="modify_repeat">
                                        <strong>Sukurti custom prompt'ą</strong>
                                        <small class="d-block text-muted">Modifikuoti standartinį prompt'ą arba sukurti naują</small>
                                    </label>
                                </div>
                            @endif
                        </div>

                        <!-- Current prompt display -->
                        @if($analysis->custom_prompt)
                            <div id="current_prompt_section">
                                <label class="form-label">Dabartinis custom prompt:</label>
                                <div class="bg-light p-3 mb-3" style="max-height: 150px; overflow-y: auto;">
                                    <pre style="white-space: pre-wrap; margin: 0;">{{ $analysis->custom_prompt }}</pre>
                                </div>
                            </div>
                        @endif

                        <!-- Custom prompt editor -->
                        <div id="custom_prompt_repeat_section" style="display: none;">
                            <div class="mb-3">
                                @if($analysis->custom_prompt)
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="loadCurrentPrompt()">
                                        <i class="fas fa-copy me-1"></i>Kopijuoti dabartinį prompt'ą
                                    </button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="loadDefaultPromptRepeat()">
                                    <i class="fas fa-copy me-1"></i>Kopijuoti standartinį prompt'ą
                                </button>
                            </div>
                            <textarea class="form-control" id="new_custom_prompt" name="custom_prompt" rows="8" 
                                      placeholder="Įveskite modifikuotą prompt'ą..."></textarea>
                        </div>
                    </div>

                    <!-- Model selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Modelių pasirinkimas</label>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Galite keisti modelius, kuriuos naudos pakartotinė analizė. Pažymėkite bent vieną modelį.
                        </div>
                        
                        <div class="row">
                            @php
                                $llmConfig = config('llm');
                                $allModels = $llmConfig['models'];
                                $providers = $llmConfig['providers'];
                                
                                // Convert model names to keys (Claude -> claude-opus-4, etc)
                                $currentModelKeys = [];
                                foreach($usedModels as $modelName) {
                                    foreach($allModels as $key => $config) {
                                        if (stripos($modelName, $config['provider']) !== false || 
                                            stripos($key, strtolower($modelName)) !== false) {
                                            $currentModelKeys[] = $key;
                                            break;
                                        }
                                    }
                                }
                            @endphp
                            
                            @foreach($allModels as $key => $model)
                                @php
                                    $provider = $providers[$model['provider']];
                                    $isCurrentlyUsed = in_array($key, $currentModelKeys);
                                    $tier = $model['tier'] === 'premium' ? 
                                        '<span class="badge bg-warning text-dark ms-1">Premium</span>' : '';
                                @endphp
                                
                                <div class="col-md-6 mb-2">
                                    <div class="model-checkbox">
                                        <input type="checkbox" class="form-check-input" 
                                               id="repeat_{{ $key }}" name="models[]" value="{{ $key }}" 
                                               @if($isCurrentlyUsed) checked @endif>
                                        <label class="form-check-label w-100" for="repeat_{{ $key }}">
                                            <div class="d-flex align-items-center">
                                                <i class="{{ $provider['icon'] }} text-{{ $provider['color'] }} me-2"></i>
                                                <div class="flex-grow-1">
                                                    <strong>{{ $model['model'] }}</strong>
                                                    @if($model['tier'] === 'premium')
                                                        <span class="badge bg-warning text-dark ms-1">Premium</span>
                                                    @endif
                                                    @if($isCurrentlyUsed)
                                                        <span class="badge bg-info ms-1">Naudotas</span>
                                                    @endif
                                                    <small class="d-block text-muted">{{ $model['description'] ?? '' }}</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Modeliai pažymėti "Naudotas" buvo naudojami originalios analizės metu.
                            </small>
                        </div>
                    </div>

                    <!-- Analysis naming -->
                    <div class="row">
                        <div class="col-md-6">
                            <label for="repeat_name" class="form-label">Naujos analizės pavadinimas</label>
                            <input type="text" class="form-control" id="repeat_name" name="name" 
                                   value="{{ $analysis->name ? $analysis->name . ' (pakartotinė)' : 'Pakartotinė analizė' }}">
                        </div>
                        <div class="col-md-6">
                            <label for="repeat_description" class="form-label">Aprašymas</label>
                            <input type="text" class="form-control" id="repeat_description" name="description" 
                                   placeholder="Pakartotinės analizės aprašymas">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
                    <button type="submit" class="btn btn-primary" id="repeatSubmitBtn">
                        <i class="fas fa-redo me-2"></i>Pradėti pakartotinę analizę
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Repeat analysis modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const keepRadio = document.getElementById('keep_prompt'); // Only exists for custom prompts
    const standardRadio = document.getElementById('standard_repeat');
    const modifyRadio = document.getElementById('modify_repeat');
    const customSection = document.getElementById('custom_prompt_repeat_section');
    const currentSection = document.getElementById('current_prompt_section');

    // Only set up keep_prompt listener if it exists (custom prompt scenarios)
    if (keepRadio) {
        keepRadio.addEventListener('change', function() {
            if (this.checked) {
                customSection.style.display = 'none';
                if (currentSection) currentSection.style.display = 'block';
            }
        });
    }

    if (standardRadio) {
        standardRadio.addEventListener('change', function() {
            if (this.checked) {
                customSection.style.display = 'none';
                if (currentSection) currentSection.style.display = 'none';
            }
        });
    }

    if (modifyRadio) {
        modifyRadio.addEventListener('change', function() {
            if (this.checked) {
                customSection.style.display = 'block';
                if (currentSection) currentSection.style.display = 'none';
            }
        });
    }

    // Form submission with validation
    const repeatForm = document.getElementById('repeatAnalysisForm');
    const repeatBtn = document.getElementById('repeatSubmitBtn');
    
    if (repeatForm) {
        repeatForm.addEventListener('submit', function(e) {
            // Validate that at least one model is selected
            const modelCheckboxes = repeatForm.querySelectorAll('input[name="models[]"]:checked');
            
            if (modelCheckboxes.length === 0) {
                e.preventDefault();
                alert('Prašome pasirinkti bent vieną modelį analizei.');
                return false;
            }
            
            // If validation passes, proceed with submission
            repeatBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Paleidžiama...';
            repeatBtn.disabled = true;
        });
    }
});

function loadCurrentPrompt() {
    const currentPrompt = @json($analysis->custom_prompt ?? '');
    document.getElementById('new_custom_prompt').value = currentPrompt;
}

function loadDefaultPromptRepeat() {
    fetch('/api/default-prompt')
        .then(response => response.json())
        .then(data => {
            document.getElementById('new_custom_prompt').value = data.prompt;
        })
        .catch(error => {
            console.error('Error loading default prompt:', error);
            alert('Nepavyko įkelti standartinio prompt\'o');
        });
}

// Text highlighting functionality
let currentTextId = null;
let currentViewType = 'ai';

// Color mapping for propaganda techniques
const techniqueColors = {
    'emotionalAppeal': '#ff6b6b',
    'appealToFear': '#ff8e53', 
    'loadedLanguage': '#4ecdc4',
    'nameCalling': '#45b7d1',
    'exaggeration': '#96ceb4',
    'glitteringGeneralities': '#ffeaa7',
    'whataboutism': '#dda0dd',
    'redHerring': '#98d8c8',
    'strawMan': '#f7dc6f',
    'causalOversimplification': '#bb8fce',
    'blackAndWhite': '#85c1e9',
    'thoughtTerminatingCliche': '#f8c471',
    'slogans': '#82e0aa',
    'obfuscation': '#f1948a',
    'appealToAuthority': '#85c1e9',
    'flagWaving': '#f9e79f',
    'bandwagon': '#d7bde2',
    'doubt': '#aed6f1',
    'smears': '#f5b7b1',
    'reductioAdHitlerum': '#d5a6bd',
    'repetition': '#a9dfbf'
};

function initializeTextHighlighting() {
    const textSelector = document.getElementById('text-selector');
    const viewToggle = document.getElementsByName('viewType');
    
    if (textSelector) {
        textSelector.addEventListener('change', loadTextAnnotations);
        
        // Load the first text by default
        if (textSelector.options.length > 0) {
            loadTextAnnotations();
        }
    }
    
    // Add event listeners for view toggle
    viewToggle.forEach(radio => {
        radio.addEventListener('change', function() {
            currentViewType = this.value;
            loadTextAnnotations();
        });
    });
}

function loadTextAnnotations() {
    const textSelector = document.getElementById('text-selector');
    const loadingSpinner = document.getElementById('loading-spinner');
    const highlightedText = document.getElementById('highlighted-text');
    const legend = document.getElementById('annotation-legend');
    
    if (!textSelector.value) {
        return;
    }
    
    currentTextId = textSelector.value;
    
    // Show loading
    loadingSpinner.style.display = 'block';
    highlightedText.innerHTML = '<div class="text-center text-muted">Kraunama...</div>';
    legend.style.display = 'none';
    
    // Fetch annotations from API
    fetch(`/api/text-annotations/${currentTextId}?view=${currentViewType}`)
        .then(response => response.json())
        .then(data => {
            loadingSpinner.style.display = 'none';
            
            if (data.success) {
                displayHighlightedText(data.content, data.annotations, data.legend);
                showLegend(data.legend);
            } else {
                highlightedText.innerHTML = `<div class="alert alert-warning">${data.message || 'Nepavyko įkelti anotacijų'}</div>`;
            }
        })
        .catch(error => {
            loadingSpinner.style.display = 'none';
            console.error('Error loading annotations:', error);
            highlightedText.innerHTML = '<div class="alert alert-danger">Klaida kraunant anotacijas</div>';
        });
}

function displayHighlightedText(content, annotations, legend) {
    const highlightedText = document.getElementById('highlighted-text');
    
    if (!annotations || annotations.length === 0) {
        highlightedText.innerHTML = `<div class="p-3">${escapeHtml(content)}</div>`;
        return;
    }
    
    // Sort annotations by start position
    const sortedAnnotations = [...annotations].sort((a, b) => a.start - b.start);
    
    let highlightedContent = '';
    let lastIndex = 0;
    
    sortedAnnotations.forEach(annotation => {
        // Add text before annotation
        highlightedContent += escapeHtml(content.substring(lastIndex, annotation.start));
        
        // Add highlighted annotation
        const color = techniqueColors[annotation.technique] || '#cccccc';
        const labels = Array.isArray(annotation.labels) ? annotation.labels : [annotation.technique];
        
        highlightedContent += `<span class="highlighted-annotation" 
                                     style="background-color: ${color}; padding: 2px 4px; border-radius: 3px; margin: 1px;"
                                     data-labels="${labels.join(', ')}"
                                     title="${labels.join(', ')}">${escapeHtml(annotation.text)}</span>`;
        
        lastIndex = annotation.end;
    });
    
    // Add remaining text
    highlightedContent += escapeHtml(content.substring(lastIndex));
    
    highlightedText.innerHTML = `<div class="p-3" style="line-height: 1.8;">${highlightedContent}</div>`;
}

function showLegend(legendItems) {
    const legend = document.getElementById('annotation-legend');
    const legendContainer = document.getElementById('legend-items');
    
    if (!legendItems || legendItems.length === 0) {
        legend.style.display = 'none';
        return;
    }
    
    legendContainer.innerHTML = '';
    
    legendItems.forEach(item => {
        const color = techniqueColors[item.technique] || '#cccccc';
        const description = getTechniqueDescription(item.technique);
        
        const legendItem = document.createElement('div');
        legendItem.className = 'col-md-6 col-lg-4 mb-2';
        legendItem.innerHTML = `
            <div class="d-flex align-items-center">
                <div style="width: 20px; height: 20px; background-color: ${color}; border-radius: 3px; margin-right: 8px;"></div>
                <div>
                    <strong>${item.technique}</strong>
                    <small class="d-block text-muted">${description}</small>
                </div>
            </div>
        `;
        
        legendContainer.appendChild(legendItem);
    });
    
    legend.style.display = 'block';
}

function getTechniqueDescription(technique) {
    const descriptions = {
        'emotionalAppeal': 'Apeliavimas į jausmus',
        'appealToFear': 'Apeliavimas į baimę',
        'loadedLanguage': 'Vertinamoji leksika',
        'nameCalling': 'Etiketių klijavimas',
        'exaggeration': 'Perdėtas vertinimas',
        'glitteringGeneralities': 'Blizgantys apibendrinimai',
        'whataboutism': 'Whataboutism',
        'redHerring': 'Red Herring',
        'strawMan': 'Straw Man',
        'causalOversimplification': 'Supaprastinimas',
        'blackAndWhite': 'Juoda-balta',
        'thoughtTerminatingCliche': 'Kliše',
        'slogans': 'Šūkiai',
        'obfuscation': 'Neapibrėžtumas',
        'appealToAuthority': 'Apeliavimas į autoritetą',
        'flagWaving': 'Mojavimas vėliava',
        'bandwagon': 'Bandwagon',
        'doubt': 'Abejojimas',
        'smears': 'Šmeižtas',
        'reductioAdHitlerum': 'Reductio ad hitlerum',
        'repetition': 'Pakartojimas'
    };
    
    return descriptions[technique] || technique;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeTextHighlighting();
});
</script>

@endsection