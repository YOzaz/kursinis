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

            <!-- Mission Control Links -->
            <div class="text-center mb-4">
                <div class="btn-group">
                    <a href="{{ route('mission-control') }}?job_id={{ $analysis->job_id }}" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-satellite-dish me-2"></i>
                        🤖 Mission Control (Filtered)
                        <i class="fas fa-external-link-alt ms-2"></i>
                    </a>
                    <a href="{{ route('mission-control') }}" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-chart-line me-2"></i>
                        System-Wide View
                    </a>
                </div>
                <div class="text-muted mt-2">
                    <small>Advanced real-time monitoring with technical details and system-wide status</small>
                </div>
            </div>

            <!-- Analysis Information & Statistics Row -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
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
                </div>
                <div class="col-lg-4">
                    @if(isset($statistics))
                        <div class="card">
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
                                           title="Recall - kiek ekspertų rastų propagandos fragmentų AI sugebėjo rasti. Skaičiuojama: Teisingi teigiami / (Teisingi teigiami + Klaidingi neigiami)"></i>
                                    </dt>
                                    <dd class="col-6">{{ number_format(($statistics['overall_metrics']['avg_recall'] ?? 0) * 100, 2) }}%</dd>
                                    <dt class="col-6">Tekstų</dt>
                                    <dd class="col-6">{{ $statistics['total_texts'] ?? 0 }}</dd>
                                    <dt class="col-6">Su propagandą</dt>
                                    <dd class="col-6">{{ $statistics['propaganda_texts'] ?? 0 }}</dd>
                                    <dt class="col-6">Su anotacijomis</dt>
                                    <dd class="col-6">{{ $statistics['with_expert_annotations'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Error Message if Analysis Failed -->
            @if($analysis->status === 'failed' && $analysis->error_message)
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">Klaidos pranešimas</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-0">{{ $analysis->error_message }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Analysis Results Table - Full Width -->
            @if($analysis->status === 'completed' && $textAnalyses->isNotEmpty())
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Analizės rezultatai</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="analysisResultsTable">
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">Tekstas</th>
                                                <th style="width: 12%;">
                                                    Modeliai
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="AI modelių skaičius, bandęs analizuoti tekstą (žaliai - sėkmingai, geltonai - nepavyko)"></i>
                                                </th>
                                                <th style="width: 25%;">
                                                    Bendrieji rezultatai
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Suvestinė visų modelių sprendimų ir rastų technikų"></i>
                                                </th>
                                                <th style="width: 18%;">
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
                                                    $allAttemptedModels = $textAnalysis->getAllAttemptedModels();
                                                    $totalModels = count($allAttemptedModels);
                                                    
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
                                                        <div class="text-truncate" style="max-width: 250px;">
                                                            <span class="text-preview" data-text-id="{{ $textAnalysis->id }}">
                                                                {{ Str::limit($textAnalysis->content, 100) }}
                                                            </span>
                                                            <button class="btn btn-sm btn-link p-0 ms-1" onclick="toggleFullText({{ $textAnalysis->id }})">
                                                                <i class="fas fa-expand-alt"></i> Daugiau
                                                            </button>
                                                            <div class="full-text d-none" id="full-text-{{ $textAnalysis->id }}">
                                                                <div class="expanded-text-controls mb-3">
                                                                    <div class="d-flex justify-content-between align-items-center">
                                                                        <h6 class="mb-0">Tekstas {{ $textAnalysis->text_id }}</h6>
                                                                        <div class="annotation-controls-expanded">
                                                                            <div class="btn-group me-2" role="group">
                                                                                <input type="radio" class="btn-check" name="expandedViewType-{{ $textAnalysis->id }}" id="expanded-ai-view-{{ $textAnalysis->id }}" value="ai" checked>
                                                                                <label class="btn btn-outline-primary btn-sm" for="expanded-ai-view-{{ $textAnalysis->id }}">AI anotacijos</label>
                                                                                
                                                                                <input type="radio" class="btn-check" name="expandedViewType-{{ $textAnalysis->id }}" id="expanded-expert-view-{{ $textAnalysis->id }}" value="expert">
                                                                                <label class="btn btn-outline-secondary btn-sm" for="expanded-expert-view-{{ $textAnalysis->id }}">Ekspertų anotacijos</label>
                                                                            </div>
                                                                            
                                                                            <div class="model-selector-expanded me-2" id="model-selector-expanded-{{ $textAnalysis->id }}">
                                                                                <select class="form-select form-select-sm" id="ai-model-select-{{ $textAnalysis->id }}">
                                                                                    <option value="all">Visi modeliai</option>
                                                                                    @foreach($textAnalysis->getAllAttemptedModels() as $modelName => $modelData)
                                                                                        <option value="{{ $modelName }}">{{ $modelName }}</option>
                                                                                    @endforeach
                                                                                </select>
                                                                            </div>
                                                                            
                                                                            <div class="form-check form-switch">
                                                                                <input class="form-check-input" type="checkbox" id="annotation-toggle-{{ $textAnalysis->id }}">
                                                                                <label class="form-check-label" for="annotation-toggle-{{ $textAnalysis->id }}">
                                                                                    Spalvoti
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="highlighted-text-expanded" id="highlighted-text-{{ $textAnalysis->id }}">
                                                                    {{ $textAnalysis->content }}
                                                                </div>
                                                                
                                                                <div class="legend-expanded" id="legend-{{ $textAnalysis->id }}" style="display: none;">
                                                                    <h6 class="mt-3">Propagandos technikų legenda:</h6>
                                                                    <div class="row" id="legend-items-{{ $textAnalysis->id }}">
                                                                        <!-- Legend items will be populated by JavaScript -->
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted d-block mt-1">ID: {{ $textAnalysis->text_id }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                                            @foreach($allAttemptedModels as $modelName => $modelData)
                                                                @if($modelData['status'] === 'success')
                                                                    <span class="badge bg-success">{{ $modelName }}</span>
                                                                @else
                                                                    <span class="badge bg-warning" title="Analizė nepavyko">{{ $modelName }}</span>
                                                                @endif
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
                                                        <div class="btn-group" role="group">
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#analysisModal{{ $textAnalysis->id }}">
                                                                <i class="fas fa-eye me-1"></i>Detalės
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-info" 
                                                                    onclick="showDebugInfo({{ $textAnalysis->id }})"
                                                                    title="Peržiūrėti raw RISEN užklausas ir atsakymus">
                                                                <i class="fas fa-bug me-1"></i>Debug
                                                            </button>
                                                        </div>
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
                </div>
            @endif
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
                        
                        @php
                            // Get all attempted models (successful and failed)
                            $allAttemptedModels = $textAnalysis->getAllAttemptedModels();
                        @endphp
                        
                        @foreach($allAttemptedModels as $modelName => $modelData)
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    {{ $modelName }} rezultatas
                                    @if($modelData['actual_model'] && $modelData['actual_model'] !== $modelName)
                                        <small class="text-muted">({{ $modelData['actual_model'] }})</small>
                                    @endif
                                </label>
                                <div class="border p-3 rounded">
                                    @if($modelData['status'] === 'failed')
                                        <div class="alert alert-warning mb-2">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            <strong>Analizė nepavyko:</strong> 
                                            @if(isset($modelData['error']))
                                                {{ $modelData['error'] }}
                                            @else
                                                Šis modelis nepateikė tinkamo atsako arba analizė buvo nutraukta dėl klaidos.
                                            @endif
                                            @if(isset($modelData['has_metrics']))
                                                <br><small class="text-muted">Metrikos rodo žemų rezultatų duomenis žemiau esančioje lentelėje.</small>
                                            @endif
                                        </div>
                                    @else
                                        @php $annotations = $modelData['annotations']; @endphp
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
                                                       title="Precision - kiek AI rastų propagandos fragmentų yra tikrai teisingi. Pvz., jei AI rado 10 fragmentų, bet tik 8 iš jų tikrai yra propaganda, tikslumas = 80%"></i>
                                                </th>
                                                <th>
                                                    Atsaukimas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Recall - kokią dalį visų propagandos fragmentų AI surado. Pvz., jei tekste yra 12 propagandos fragmentų, bet AI surado tik 8, atsaukimas = 67%"></i>
                                                </th>
                                                <th>
                                                    F1 balas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="F1-score - bendras tikslumo ir atsaukimo įvertis (harmoninis vidurkis). 100% reiškia tobulą rezultatą - AI rado visus propagandos fragmentus ir nepadarė klaidų"></i>
                                                </th>
                                                <th>
                                                    Pozicijos tikslumas 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Position accuracy - kiek tiksliai AI nustatė propagandos fragmentų pozicijas tekste. Pvz., jei ekspertas pažymėjo žodžius 10-20, o AI rado 12-18, pozicijos tikslumas bus maždaug 60%"></i>
                                                </th>
                                                <th>
                                                    TP/FP/FN 
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="True Positives (žalia) / False Positives (geltona) / False Negatives (raudona). Pvz., 8/2/4 reiškia: AI teisingai rado 8 fragmentus, klaidingai pažymėjo 2 kaip propagandą, o 4 propagandos fragmentus praleido"></i>
                                                </th>
                                                <th>
                                                    Vykdymo laikas
                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                       data-bs-toggle="tooltip" 
                                                       data-bs-placement="top" 
                                                       title="Analizės trukmė - kiek laiko užtruko šio modelio analizė. Greitesni modeliai gali būti efektyvesni dideliems duomenų kiekiams"></i>
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

                        <!-- Text Highlighting Section -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-highlighter me-2"></i>Tekstų analizė
                            </label>
                            <div class="card">
                                <div class="card-header">
                                    <div class="annotation-controls-modal d-flex flex-wrap align-items-center gap-3">
                                        <div class="btn-group view-toggle" role="group">
                                            <input type="radio" class="btn-check" name="modalViewType-{{ $textAnalysis->id }}" id="modal-ai-view-{{ $textAnalysis->id }}" value="ai" checked>
                                            <label class="btn btn-outline-primary btn-sm" for="modal-ai-view-{{ $textAnalysis->id }}">AI anotacijos</label>
                                            
                                            <input type="radio" class="btn-check" name="modalViewType-{{ $textAnalysis->id }}" id="modal-expert-view-{{ $textAnalysis->id }}" value="expert">
                                            <label class="btn btn-outline-secondary btn-sm" for="modal-expert-view-{{ $textAnalysis->id }}">Ekspertų anotacijos</label>
                                        </div>
                                        
                                        <div class="model-selector-modal" id="modal-model-selector-{{ $textAnalysis->id }}" style="display: none;">
                                            <select class="form-select form-select-sm" id="modal-ai-model-select-{{ $textAnalysis->id }}" style="min-width: 120px;">
                                                <option value="all">Visi modeliai</option>
                                                @foreach($allAttemptedModels as $modelName => $modelData)
                                                    <option value="{{ $modelName }}">{{ $modelName }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="modal-annotation-toggle-{{ $textAnalysis->id }}">
                                            <label class="form-check-label" for="modal-annotation-toggle-{{ $textAnalysis->id }}">
                                                Rodyti anotacijas
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Legend -->
                                    <div class="legend mb-3" id="modal-annotation-legend-{{ $textAnalysis->id }}" style="display: none;">
                                        <h6>Propagandos technikų legenda:</h6>
                                        <div class="row" id="modal-legend-items-{{ $textAnalysis->id }}">
                                            <!-- Legend items will be populated by JavaScript -->
                                        </div>
                                    </div>

                                    <!-- Highlighted Text Display -->
                                    <div class="modal-highlighted-text-container">
                                        <div id="modal-highlighted-text-{{ $textAnalysis->id }}" class="border p-3 rounded bg-light" style="line-height: 1.8;">
                                            <div class="text-center text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Kraunamos anotacijos...
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <div class="loading-spinner text-center" id="modal-loading-spinner-{{ $textAnalysis->id }}" style="display: none;">
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
        button.innerHTML = '<i class="fas fa-compress-alt"></i> Mažiau';
        button.title = 'Sutrumpinti';
        
        // Initialize expanded text view
        initializeExpandedTextView(textId);
    } else {
        fullText.classList.add('d-none');
        preview.classList.remove('d-none');
        icon.className = 'fas fa-expand-alt';
        button.innerHTML = '<i class="fas fa-expand-alt"></i> Daugiau';
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

// Color mapping for propaganda techniques (updated for expert annotations)
const techniqueColors = {
    'emotionalExpression': '#ff6b6b',
    'simplification': '#ff8e53',
    'doubt': '#4ecdc4',
    'uncertainty': '#45b7d1',
    'appealToAuthority': '#96ceb4',
    'wavingTheFlag': '#ffeaa7',
    'reductioAdHitlerum': '#d5a6bd',
    'repetition': '#a9dfbf',
    'followingBehind': '#dda0dd',
    'whataboutismRedHerringStrawMan': '#98d8c8',
    'unclear': '#cccccc'
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
        
        // Add highlighted annotation with multiple technique support
        const labels = Array.isArray(annotation.labels) ? annotation.labels : [annotation.technique];
        const primaryTechnique = labels[0] || annotation.technique;
        const color = techniqueColors[primaryTechnique] || '#cccccc';
        
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
    
    // Group techniques by category
    const groupedTechniques = {};
    legendItems.forEach(item => {
        const techniqueInfo = getTechniqueInfo(item.technique);
        const category = techniqueInfo.category;
        
        if (!groupedTechniques[category]) {
            groupedTechniques[category] = [];
        }
        
        groupedTechniques[category].push({
            ...item,
            info: techniqueInfo,
            color: techniqueColors[item.technique] || '#cccccc'
        });
    });
    
    // Create legend with categories
    Object.keys(groupedTechniques).forEach(category => {
        // Category header
        const categoryHeader = document.createElement('div');
        categoryHeader.className = 'col-12 mt-3 mb-2';
        categoryHeader.innerHTML = `<h6 class="text-primary mb-1">${category}</h6><hr class="mt-0">`;
        legendContainer.appendChild(categoryHeader);
        
        // Techniques in category
        groupedTechniques[category].forEach(item => {
            const legendItem = document.createElement('div');
            legendItem.className = 'col-md-6 col-lg-4 mb-2';
            legendItem.innerHTML = `
                <div class="d-flex align-items-start">
                    <div style="width: 20px; height: 20px; background-color: ${item.color}; border-radius: 3px; margin-right: 8px; margin-top: 2px; flex-shrink: 0;"></div>
                    <div class="flex-grow-1">
                        <strong class="d-block">${item.info.name}</strong>
                        <small class="text-muted d-block" style="line-height: 1.3;">${item.info.definition}</small>
                    </div>
                </div>
            `;
            
            legendContainer.appendChild(legendItem);
        });
    });
    
    legend.style.display = 'block';
}

// ATSPARA technique definitions and metadata - Updated to match expert annotations
const atsparaTechniques = {
    // Technikos pagal faktines ATSPARA ekspertų anotacijas
    'emotionalExpression': {
        name: 'Emocinė raiška',
        category: 'Emocinė raiška',
        definition: 'Jausmų kelimas per emociškai paveikius žodžius',
        description: 'Stiprios emocijos keliamos per žodyną, toną ir retorinę raišką'
    },
    'simplification': {
        name: 'Supaprastinimas',
        category: 'Supaprastinimas',
        definition: 'Sudėtingų reiškinių sumažinimas į paprastus aiškimus',
        description: 'Kompleksinių problemų redukavimas į primityvius sprendimus'
    },
    'doubt': {
        name: 'Abejojimas',
        category: 'Diskreditavimas',
        definition: 'Patikimumo ir sprendimų kvestionavimas',
        description: 'Sisteminis nepasiticėjimo kėlimas institucijomis ir asmenimis'
    },
    'uncertainty': {
        name: 'Neapibrėžtumas',
        category: 'Manipuliavimas',
        definition: 'Tikslaus atsako ar pozicijos vengimas',
        description: 'Sąmoningas dviprasmiškumo kūrimas ir aiškumo vengimas'
    },
    'appealToAuthority': {
        name: 'Apeliavimas į autoritetą',
        category: 'Manipuliavimas',
        definition: 'Garsi asmenybė ar institucija remia poziciją',
        description: 'Argumentavimas remiantis autoriteto prestižu'
    },
    'wavingTheFlag': {
        name: 'Mojavimas vėliava',
        category: 'Patriotizmas',
        definition: 'Patriotinių jausmų eksploatavimas',
        description: 'Patriotizmo ir nacionalinio tapatumo instrumentalizavimas'
    },
    'reductioAdHitlerum': {
        name: 'Reductio ad Hitlerum',
        category: 'Diskreditavimas',
        definition: 'Lyginimas su nekenčiamomis figūromis',
        description: 'Oponento prilyginimas kraštutinai neigiamoms figūroms'
    },
    'repetition': {
        name: 'Pakartojimas',
        category: 'Įtakos didinimas',
        definition: 'Tos pačios žinutės ar idėjos kartojimas',
        description: 'Sistemingas tų pačių teiginių akcentavimas'
    },
    'followingBehind': {
        name: 'Sekimas iš paskos',
        category: 'Socialinis spaudimas',
        definition: 'Apeliavimas prisijungti prie daugumos',
        description: 'Spaudimas elgtis kaip visi kiti arba dauguma'
    },
    'whataboutismRedHerringStrawMan': {
        name: 'Whataboutism, Red Herring, Straw Man',
        category: 'Išsisukinėjimas',
        definition: 'Dėmesio nukreipimo technikos',
        description: 'Kombinuotos technikos dėmesio nukreipimui nuo esmės'
    },
    'unclear': {
        name: 'Neapibrėžta technika',
        category: 'Neapibrėžta',
        definition: 'Sunkiai identifikuojami propaganda fragmentai',
        description: 'Dviprasmiški ar neaiškūs propaganda elementai'
    }
};

function getTechniqueInfo(technique) {
    return atsparaTechniques[technique] || {
        name: technique,
        category: 'Nežinoma',
        definition: 'Neapibrėžta technika',
        description: technique
    };
}

function getTechniqueDescription(technique) {
    const info = getTechniqueInfo(technique);
    return info.description || info.definition || info.name;
}

function getTechniqueName(technique) {
    const info = getTechniqueInfo(technique);
    return info.name;
}

function getTechniqueDefinition(technique) {
    const info = getTechniqueInfo(technique);
    return info.definition;
}

function getTechniqueCategory(technique) {
    const info = getTechniqueInfo(technique);
    return info.category;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize expanded text view with annotations
function initializeExpandedTextView(textId) {
    const viewToggle = document.getElementsByName(`expandedViewType-${textId}`);
    const modelSelect = document.getElementById(`ai-model-select-${textId}`);
    const annotationToggle = document.getElementById(`annotation-toggle-${textId}`);
    
    // Add event listeners for view toggle
    viewToggle.forEach(radio => {
        radio.addEventListener('change', function() {
            loadExpandedTextAnnotations(textId);
        });
    });
    
    // Add event listener for model selection
    if (modelSelect) {
        modelSelect.addEventListener('change', function() {
            loadExpandedTextAnnotations(textId);
        });
    }
    
    // Add event listener for annotation toggle
    if (annotationToggle) {
        annotationToggle.addEventListener('change', function() {
            loadExpandedTextAnnotations(textId);
        });
    }
    
    // Load initial annotations
    loadExpandedTextAnnotations(textId);
}

function loadExpandedTextAnnotations(textId) {
    const viewType = document.querySelector(`input[name="expandedViewType-${textId}"]:checked`)?.value || 'ai';
    const selectedModel = document.getElementById(`ai-model-select-${textId}`)?.value || 'all';
    const annotationsEnabled = document.getElementById(`annotation-toggle-${textId}`)?.checked || false;
    const highlightedText = document.getElementById(`highlighted-text-${textId}`);
    const legend = document.getElementById(`legend-${textId}`);
    
    if (!highlightedText) return;
    
    // Show loading
    highlightedText.innerHTML = '<div class="text-center text-muted p-3"><i class="fas fa-spinner fa-spin"></i> Kraunama...</div>';
    legend.style.display = 'none';
    
    // Fetch annotations from API
    const params = new URLSearchParams({
        view: viewType,
        model: selectedModel,
        enabled: annotationsEnabled
    });
    
    fetch(`/api/text-annotations/${textId}?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (annotationsEnabled && data.annotations && data.annotations.length > 0) {
                    displayExpandedHighlightedText(textId, data.content, data.annotations, data.legend);
                    showExpandedLegend(textId, data.legend);
                } else {
                    // Show plain text without annotations
                    highlightedText.innerHTML = `<div class="p-3">${escapeHtml(data.content)}</div>`;
                    legend.style.display = 'none';
                }
            } else {
                highlightedText.innerHTML = `<div class="alert alert-warning">${data.message || 'Nepavyko įkelti anotacijų'}</div>`;
                legend.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error loading annotations:', error);
            highlightedText.innerHTML = '<div class="alert alert-danger">Klaida kraunant anotacijas</div>';
            legend.style.display = 'none';
        });
}

function displayExpandedHighlightedText(textId, content, annotations, legend) {
    const highlightedText = document.getElementById(`highlighted-text-${textId}`);
    
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
        
        // Add highlighted annotation with tooltip
        const color = techniqueColors[annotation.technique] || '#cccccc';
        const labels = Array.isArray(annotation.labels) ? annotation.labels : [annotation.technique];
        const description = getTechniqueDescription(annotation.technique);
        
        const techniqueInfo = getTechniqueInfo(annotation.technique);
        const tooltipContent = `${techniqueInfo.name}: ${techniqueInfo.definition}`;
        
        highlightedContent += `<span class="highlighted-annotation" 
                                     style="background-color: ${color}; padding: 2px 4px; border-radius: 3px; margin: 1px; cursor: help;"
                                     data-labels="${labels.join(', ')}"
                                     data-bs-toggle="tooltip"
                                     data-bs-placement="top"
                                     data-bs-html="true"
                                     title="${tooltipContent}">${escapeHtml(annotation.text)}</span>`;
        
        lastIndex = annotation.end;
    });
    
    // Add remaining text
    highlightedContent += escapeHtml(content.substring(lastIndex));
    
    highlightedText.innerHTML = `<div class="p-3" style="line-height: 1.8;">${highlightedContent}</div>`;
    
    // Initialize tooltips for new elements
    const tooltipTriggerList = highlightedText.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function showExpandedLegend(textId, legendItems) {
    const legend = document.getElementById(`legend-${textId}`);
    const legendContainer = document.getElementById(`legend-items-${textId}`);
    
    if (!legendItems || legendItems.length === 0) {
        legend.style.display = 'none';
        return;
    }
    
    legendContainer.innerHTML = '';
    
    // Group techniques by category
    const groupedTechniques = {};
    legendItems.forEach(item => {
        const techniqueInfo = getTechniqueInfo(item.technique);
        const category = techniqueInfo.category;
        
        if (!groupedTechniques[category]) {
            groupedTechniques[category] = [];
        }
        
        groupedTechniques[category].push({
            ...item,
            info: techniqueInfo,
            color: techniqueColors[item.technique] || '#cccccc'
        });
    });
    
    // Create legend with categories
    Object.keys(groupedTechniques).forEach(category => {
        // Category header
        const categoryHeader = document.createElement('div');
        categoryHeader.className = 'col-12 mt-3 mb-2';
        categoryHeader.innerHTML = `<h6 class="text-primary mb-1">${category}</h6><hr class="mt-0">`;
        legendContainer.appendChild(categoryHeader);
        
        // Techniques in category
        groupedTechniques[category].forEach(item => {
            const legendItem = document.createElement('div');
            legendItem.className = 'col-md-6 col-lg-4 mb-2';
            legendItem.innerHTML = `
                <div class="d-flex align-items-start">
                    <div style="width: 20px; height: 20px; background-color: ${item.color}; border-radius: 3px; margin-right: 8px; margin-top: 2px; flex-shrink: 0;"></div>
                    <div class="flex-grow-1">
                        <strong class="d-block">${item.info.name}</strong>
                        <small class="text-muted d-block" style="line-height: 1.3;">${item.info.definition}</small>
                    </div>
                </div>
            `;
            
            legendContainer.appendChild(legendItem);
        });
    });
    
    legend.style.display = 'block';
}

// Initialize modal text highlighting for each modal
function initializeModalTextHighlighting() {
    // Find all modals and initialize their text highlighting
    document.querySelectorAll('[id^="analysisModal"]').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const modalId = this.id;
            const textAnalysisId = modalId.replace('analysisModal', '');
            loadModalTextAnnotations(textAnalysisId);
        });
    });
    
    // Add event listeners for all modal controls
    document.querySelectorAll('[name^="modalViewType-"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const textAnalysisId = this.name.replace('modalViewType-', '');
            const modelSelector = document.getElementById(`modal-model-selector-${textAnalysisId}`);
            const annotationsEnabled = document.getElementById(`modal-annotation-toggle-${textAnalysisId}`)?.checked || false;
            
            // Show/hide model selector based on view type and annotations enabled
            if (modelSelector) {
                modelSelector.style.display = (this.value === 'ai' && annotationsEnabled) ? 'block' : 'none';
            }
            
            loadModalTextAnnotations(textAnalysisId);
        });
    });
    
    document.querySelectorAll('[id^="modal-ai-model-select-"]').forEach(select => {
        select.addEventListener('change', function() {
            const textAnalysisId = this.id.replace('modal-ai-model-select-', '');
            loadModalTextAnnotations(textAnalysisId);
        });
    });
    
    document.querySelectorAll('[id^="modal-annotation-toggle-"]').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const textAnalysisId = this.id.replace('modal-annotation-toggle-', '');
            const legend = document.getElementById(`modal-annotation-legend-${textAnalysisId}`);
            const modelSelector = document.getElementById(`modal-model-selector-${textAnalysisId}`);
            const viewType = document.querySelector(`input[name="modalViewType-${textAnalysisId}"]:checked`)?.value || 'ai';
            
            // Show/hide model selector
            if (modelSelector) {
                modelSelector.style.display = (this.checked && viewType === 'ai') ? 'block' : 'none';
            }
            
            if (this.checked) {
                loadModalTextAnnotations(textAnalysisId);
            } else {
                // Show plain text without highlighting
                const textContainer = document.getElementById(`modal-highlighted-text-${textAnalysisId}`);
                if (textContainer) {
                    // Load plain text from API
                    loadModalTextAnnotations(textAnalysisId);
                }
                if (legend) legend.style.display = 'none';
            }
        });
    });
}

function loadModalTextAnnotations(textAnalysisId) {
    const viewType = document.querySelector(`input[name="modalViewType-${textAnalysisId}"]:checked`)?.value || 'ai';
    const selectedModel = document.getElementById(`modal-ai-model-select-${textAnalysisId}`)?.value || 'all';
    const annotationsEnabled = document.getElementById(`modal-annotation-toggle-${textAnalysisId}`)?.checked || false;
    const highlightedText = document.getElementById(`modal-highlighted-text-${textAnalysisId}`);
    const legend = document.getElementById(`modal-annotation-legend-${textAnalysisId}`);
    const loadingSpinner = document.getElementById(`modal-loading-spinner-${textAnalysisId}`);
    
    if (!highlightedText) return;
    
    // Show/hide model selector based on view type AND annotations enabled
    const modelSelector = document.getElementById(`modal-model-selector-${textAnalysisId}`);
    if (modelSelector) {
        modelSelector.style.display = (viewType === 'ai' && annotationsEnabled) ? 'block' : 'none';
    }
    
    // Show loading
    if (loadingSpinner) loadingSpinner.style.display = 'block';
    highlightedText.innerHTML = '<div class="text-center text-muted p-3"><i class="fas fa-spinner fa-spin"></i> Kraunama...</div>';
    if (legend) legend.style.display = 'none';
    
    // Fetch annotations from API
    const params = new URLSearchParams({
        view: viewType,
        model: selectedModel,
        enabled: annotationsEnabled
    });
    
    fetch(`/api/text-annotations/${textAnalysisId}?${params}`)
        .then(response => response.json())
        .then(data => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            
            if (data.success) {
                if (annotationsEnabled && data.annotations && data.annotations.length > 0) {
                    displayModalHighlightedText(textAnalysisId, data.content, data.annotations, data.legend);
                    showModalLegend(textAnalysisId, data.legend);
                } else {
                    // Show plain text without annotations
                    highlightedText.innerHTML = `<div class="p-3">${escapeHtml(data.content)}</div>`;
                    if (legend) legend.style.display = 'none';
                }
            } else {
                highlightedText.innerHTML = `<div class="alert alert-warning">${data.message || 'Nepavyko įkelti anotacijų'}</div>`;
                if (legend) legend.style.display = 'none';
            }
        })
        .catch(error => {
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            console.error('Error loading modal annotations:', error);
            highlightedText.innerHTML = '<div class="alert alert-danger">Klaida kraunant anotacijas</div>';
            if (legend) legend.style.display = 'none';
        });
}

function displayModalHighlightedText(textAnalysisId, content, annotations, legend) {
    const highlightedText = document.getElementById(`modal-highlighted-text-${textAnalysisId}`);
    
    if (!annotations || annotations.length === 0) {
        highlightedText.innerHTML = `<div class="p-3">${escapeHtml(content)}</div>`;
        return;
    }
    
    // Sort annotations by start position
    const sortedAnnotations = [...annotations].sort((a, b) => a.start - b.start);
    
    // Remove overlapping annotations to prevent text duplication
    const cleanedAnnotations = [];
    let lastEnd = 0;
    
    sortedAnnotations.forEach(annotation => {
        // Only add if annotation doesn't overlap with previous one
        if (annotation.start >= lastEnd) {
            cleanedAnnotations.push(annotation);
            lastEnd = annotation.end;
        } else if (annotation.end > lastEnd) {
            // Partial overlap - adjust start position
            annotation.start = lastEnd;
            if (annotation.start < annotation.end) {
                // Extract correct text for adjusted annotation
                annotation.text = content.substring(annotation.start, annotation.end);
                cleanedAnnotations.push(annotation);
                lastEnd = annotation.end;
            }
        }
    });
    
    let highlightedContent = '';
    let lastIndex = 0;
    
    cleanedAnnotations.forEach(annotation => {
        // When AI provides text, find its actual position in content and trust it completely
        let actualStart, actualEnd, annotationText;
        
        if (annotation.text && annotation.text.length > 0) {
            // Trust AI-provided text completely - this is the core principle
            annotationText = annotation.text;
            
            // Search for the AI text in the content starting from our current position
            const searchFromCurrent = content.indexOf(annotationText, lastIndex);
            
            if (searchFromCurrent !== -1) {
                // Found the AI text at or after our current position - perfect!
                actualStart = searchFromCurrent;
                actualEnd = searchFromCurrent + annotationText.length;
            } else {
                // AI text not found from current position - search from beginning near coordinates
                const searchNearCoords = content.indexOf(annotationText, Math.max(0, annotation.start - 100));
                
                if (searchNearCoords !== -1) {
                    actualStart = searchNearCoords;
                    actualEnd = searchNearCoords + annotationText.length;
                    
                    // If this creates a gap, we'll handle it by adding the gap text
                    if (actualStart < lastIndex) {
                        // This means we found the text before our current position
                        // Skip this annotation to avoid duplication
                        console.warn('Skipping annotation to avoid duplication:', annotation);
                        return;
                    }
                } else {
                    // AI text not found anywhere - fall back to coordinates
                    console.warn('AI text not found in content, using coordinates:', annotation);
                    actualStart = Math.max(annotation.start, lastIndex);
                    actualEnd = Math.min(annotation.end, content.length);
                    annotationText = content.substring(actualStart, actualEnd);
                }
            }
        } else {
            // No AI text provided, use coordinate extraction
            actualStart = Math.max(annotation.start, lastIndex);
            actualEnd = Math.min(annotation.end, content.length);
            annotationText = content.substring(actualStart, actualEnd);
        }
        
        // Ensure no overlap with previous annotation
        if (actualStart < lastIndex) {
            actualStart = lastIndex;
        }
        if (actualEnd <= actualStart) {
            // Skip invalid annotation
            return;
        }
        
        // Add text before annotation (ensure no duplication)
        if (actualStart > lastIndex) {
            highlightedContent += escapeHtml(content.substring(lastIndex, actualStart));
        }
        
        // Add highlighted annotation with tooltip for multiple techniques
        const labels = Array.isArray(annotation.labels) ? annotation.labels : [annotation.technique];
        const primaryTechnique = labels[0] || annotation.technique;
        const color = techniqueColors[primaryTechnique] || '#cccccc';
        
        // Build tooltip content for multiple techniques
        let tooltipContent = '';
        if (labels.length > 1) {
            tooltipContent = labels.map(tech => {
                const info = getTechniqueInfo(tech);
                return `${info.name}: ${info.definition}`;
            }).join('<br><br>');
        } else {
            const techniqueInfo = getTechniqueInfo(primaryTechnique);
            tooltipContent = `${techniqueInfo.name}: ${techniqueInfo.definition}`;
        }
        
        highlightedContent += `<span class="highlighted-annotation" 
                                     style="background-color: ${color}; padding: 2px 4px; border-radius: 3px; margin: 1px; cursor: help;"
                                     data-labels="${labels.join(', ')}"
                                     data-bs-toggle="tooltip"
                                     data-bs-placement="top"
                                     data-bs-html="true"
                                     title="${tooltipContent}">${escapeHtml(annotationText)}</span>`;
        
        lastIndex = actualEnd;
    });
    
    // Add remaining text
    highlightedContent += escapeHtml(content.substring(lastIndex));
    
    highlightedText.innerHTML = `<div class="p-3" style="line-height: 1.8;">${highlightedContent}</div>`;
    
    // Initialize tooltips for new elements
    const tooltipTriggerList = highlightedText.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function showModalLegend(textAnalysisId, legendItems) {
    const legend = document.getElementById(`modal-annotation-legend-${textAnalysisId}`);
    const legendContainer = document.getElementById(`modal-legend-items-${textAnalysisId}`);
    
    if (!legendItems || legendItems.length === 0) {
        if (legend) legend.style.display = 'none';
        return;
    }
    
    if (legendContainer) {
        legendContainer.innerHTML = '';
        
        // Group techniques by category
        const groupedTechniques = {};
        legendItems.forEach(item => {
            const techniqueInfo = getTechniqueInfo(item.technique);
            const category = techniqueInfo.category;
            
            if (!groupedTechniques[category]) {
                groupedTechniques[category] = [];
            }
            
            groupedTechniques[category].push({
                ...item,
                info: techniqueInfo,
                color: techniqueColors[item.technique] || '#cccccc'
            });
        });
        
        // Create legend with categories
        Object.keys(groupedTechniques).forEach(category => {
            // Category header
            const categoryHeader = document.createElement('div');
            categoryHeader.className = 'col-12 mt-3 mb-2';
            categoryHeader.innerHTML = `<h6 class="text-primary mb-1">${category}</h6><hr class="mt-0">`;
            legendContainer.appendChild(categoryHeader);
            
            // Techniques in category
            groupedTechniques[category].forEach(item => {
                const legendItem = document.createElement('div');
                legendItem.className = 'col-md-6 col-lg-4 mb-2';
                legendItem.innerHTML = `
                    <div class="d-flex align-items-start">
                        <div style="width: 20px; height: 20px; background-color: ${item.color}; border-radius: 3px; margin-right: 8px; margin-top: 2px; flex-shrink: 0;"></div>
                        <div class="flex-grow-1">
                            <strong class="d-block">${item.info.name}</strong>
                            <small class="text-muted d-block" style="line-height: 1.3;">${item.info.definition}</small>
                        </div>
                    </div>
                `;
                
                legendContainer.appendChild(legendItem);
            });
        });
    }
    
    if (legend) legend.style.display = 'block';
}

// Function to update technique names throughout the page
function updateTechniqueNames() {
    // Update technique badges in the results table
    document.querySelectorAll('.badge.bg-secondary').forEach(badge => {
        const techniqueKey = badge.textContent.trim();
        const techniqueInfo = getTechniqueInfo(techniqueKey);
        if (techniqueInfo.name !== techniqueKey) {
            badge.textContent = techniqueInfo.name;
            badge.setAttribute('title', techniqueInfo.definition);
            badge.setAttribute('data-bs-toggle', 'tooltip');
        }
    });
    
    // Update technique names in modal results
    document.querySelectorAll('.badge.bg-secondary:not(.updated)').forEach(badge => {
        const techniqueKey = badge.textContent.trim();
        const techniqueInfo = getTechniqueInfo(techniqueKey);
        if (techniqueInfo.name !== techniqueKey) {
            badge.textContent = techniqueInfo.name;
            badge.setAttribute('title', techniqueInfo.definition);
            badge.setAttribute('data-bs-toggle', 'tooltip');
            badge.classList.add('updated');
        }
    });
    
    // Initialize tooltips for updated badges
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]:not(.tooltip-initialized)');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
        tooltipTriggerEl.classList.add('tooltip-initialized');
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeTextHighlighting();
    initializeModalTextHighlighting();
    updateTechniqueNames();
});

// Debug functionality
function showDebugInfo(textAnalysisId) {
    const modal = new bootstrap.Modal(document.getElementById('debugModal'));
    loadDebugInfo(textAnalysisId);
    modal.show();
}

function loadDebugInfo(textAnalysisId) {
    const container = document.getElementById('debugContent');
    const title = document.getElementById('debugModalTitle');
    
    title.textContent = `Debug informacija - Tekstas ID: ${textAnalysisId}`;
    container.innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
            <p class="mt-2 text-muted">Kraunama debug informacija...</p>
        </div>
    `;
    
    fetch(`/api/debug/${textAnalysisId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDebugInfo(data);
            } else {
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        ${data.message || 'Nepavyko įkelti debug informacijos'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading debug info:', error);
            container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Klaida kraunant debug informaciją
                </div>
            `;
        });
}

function displayDebugInfo(data) {
    const container = document.getElementById('debugContent');
    const textAnalysis = data.text_analysis;
    const debugInfo = data.debug_info;
    
    let html = `
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Teksto informacija</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Tekstas ID:</strong> ${textAnalysis.text_id}<br>
                        <strong>Analizės ID:</strong> ${textAnalysis.job_id}<br>
                        <strong>Teksto ilgis:</strong> ${textAnalysis.content_length} simboliai
                    </div>
                    <div class="col-md-6">
                        <strong>Sukurta:</strong> ${new Date(textAnalysis.created_at).toLocaleString('lt-LT')}
                    </div>
                </div>
                <div class="mt-3">
                    <strong>Teksto peržiūra:</strong>
                    <div class="bg-light p-2 rounded small" style="max-height: 100px; overflow-y: auto;">
                        ${escapeHtml(textAnalysis.content_preview)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Add debug info for each model
    Object.entries(debugInfo).forEach(([modelKey, modelDebug]) => {
        const statusClass = modelDebug.status === 'completed' ? 'success' : 
                           modelDebug.status === 'failed' ? 'danger' : 'warning';
        const statusIcon = modelDebug.status === 'completed' ? 'check-circle' : 
                          modelDebug.status === 'failed' ? 'times-circle' : 'clock';
        
        html += `
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-robot me-2"></i>
                        ${modelDebug.model_name} (${modelDebug.provider})
                    </h6>
                    <span class="badge bg-${statusClass}">
                        <i class="fas fa-${statusIcon} me-1"></i>
                        ${modelDebug.status}
                    </span>
                </div>
                <div class="card-body">
        `;
        
        // Execution time
        if (modelDebug.execution_time_ms) {
            html += `
                <div class="mb-3">
                    <strong>Vykdymo laikas:</strong> ${modelDebug.execution_time_ms}ms
                </div>
            `;
        }
        
        // API Configuration
        html += `
            <div class="mb-4">
                <h6 class="text-primary">API konfigūracija</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <tr><td><strong>Base URL:</strong></td><td><code>${modelDebug.api_config.base_url || 'N/A'}</code></td></tr>
                        <tr><td><strong>Modelis:</strong></td><td><code>${modelDebug.api_config.model || 'N/A'}</code></td></tr>
                        <tr><td><strong>Max tokens:</strong></td><td><code>${modelDebug.api_config.max_tokens || 'N/A'}</code></td></tr>
                        <tr><td><strong>Temperature:</strong></td><td><code>${modelDebug.api_config.temperature || 'N/A'}</code></td></tr>
                    </table>
                </div>
            </div>
        `;
        
        // Query reconstruction
        if (modelDebug.query && !modelDebug.query.error) {
            html += `
                <div class="mb-4">
                    <h6 class="text-primary">
                        Išsiųsta užklausa
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('query-${modelKey}')">
                            <i class="fas fa-copy me-1"></i>Kopijuoti
                        </button>
                    </h6>
                    <div class="accordion" id="queryAccordion-${modelKey}">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#queryCollapse-${modelKey}">
                                    <strong>URL ir Headers</strong>
                                </button>
                            </h2>
                            <div id="queryCollapse-${modelKey}" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <strong>Method:</strong> <code>${modelDebug.query.method}</code><br>
                                        <strong>URL:</strong> <code>${modelDebug.query.url}</code>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Headers:</strong>
                                        <pre class="bg-light p-2 rounded small">${JSON.stringify(modelDebug.query.headers, null, 2)}</pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#queryBodyCollapse-${modelKey}">
                                    <strong>Request Body</strong>
                                </button>
                            </h2>
                            <div id="queryBodyCollapse-${modelKey}" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <pre id="query-${modelKey}" class="bg-light p-3 rounded small" style="max-height: 400px; overflow-y: auto;">${JSON.stringify(modelDebug.query.body, null, 2)}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Response or Error
        if (modelDebug.error) {
            html += `
                <div class="mb-3">
                    <h6 class="text-danger">Klaida</h6>
                    <div class="alert alert-danger">
                        <code>${escapeHtml(modelDebug.error)}</code>
                    </div>
                </div>
            `;
        } else if (modelDebug.response) {
            html += `
                <div class="mb-3">
                    <h6 class="text-success">
                        Gauta response
                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('response-${modelKey}')">
                            <i class="fas fa-copy me-1"></i>Kopijuoti
                        </button>
                    </h6>
                    <div class="accordion" id="responseAccordion-${modelKey}">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#responseCollapse-${modelKey}">
                                    <strong>Response data</strong>
                                </button>
                            </h2>
                            <div id="responseCollapse-${modelKey}" class="accordion-collapse collapse">
                                <div class="accordion-body">
                                    <pre id="response-${modelKey}" class="bg-light p-3 rounded small" style="max-height: 400px; overflow-y: auto;">${JSON.stringify(modelDebug.response, null, 2)}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        navigator.clipboard.writeText(element.textContent).then(() => {
            // Show temporary success message
            const button = document.querySelector(`button[onclick="copyToClipboard('${elementId}')"]`);
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check me-1"></i>Nukopijuota!';
            button.classList.remove('btn-outline-secondary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy: ', err);
            alert('Nepavyko nukopijuoti į clipboard');
        });
    }
}
</script>

<!-- Debug Modal -->
<div class="modal fade" id="debugModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="debugModalTitle">Debug informacija</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="debugContent">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                        <p class="mt-2 text-muted">Kraunama...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
            </div>
        </div>
    </div>
</div>


@endsection