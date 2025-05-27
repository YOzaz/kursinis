@extends('layouts.app')

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
                                            @if($analysis->experiment_id)
                                                <span class="badge bg-info">Eksperimentas</span>
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
                                        <dt class="col-sm-4">Modelis:</dt>
                                        <dd class="col-sm-8">{{ $analysis->model }}</dd>
                                        <dt class="col-sm-4">Sukurta:</dt>
                                        <dd class="col-sm-8">{{ $analysis->created_at->format('Y-m-d H:i:s') }}</dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <dl class="row">
                                        <dt class="col-sm-4">Tekstų kiekis:</dt>
                                        <dd class="col-sm-8">{{ $analysis->textAnalyses->count() }}</dd>
                                        @if($analysis->completed_at)
                                            <dt class="col-sm-4">Baigta:</dt>
                                            <dd class="col-sm-8">{{ $analysis->completed_at->format('Y-m-d H:i:s') }}</dd>
                                        @endif
                                        @if($analysis->experiment_id)
                                            <dt class="col-sm-4">Eksperimentas:</dt>
                                            <dd class="col-sm-8">
                                                <a href="{{ route('experiments.show', $analysis->experiment_id) }}">
                                                    Peržiūrėti eksperimentą
                                                </a>
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
                                                <th>Propaganda sprendimas</th>
                                                <th>Patikimumas</th>
                                                <th>Kategorijos</th>
                                                <th>Veiksmai</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($analysis->textAnalyses as $textAnalysis)
                                                <tr>
                                                    <td>
                                                        <div class="text-truncate" style="max-width: 200px;" title="{{ $textAnalysis->text_content }}">
                                                            {{ Str::limit($textAnalysis->text_content, 100) }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->is_propaganda)
                                                            <span class="badge bg-danger">Propaganda</span>
                                                        @else
                                                            <span class="badge bg-success">Ne propaganda</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->confidence_score)
                                                            <div class="progress" style="width: 100px;">
                                                                <div class="progress-bar" role="progressbar" 
                                                                     style="width: {{ $textAnalysis->confidence_score * 100 }}%">
                                                                    {{ round($textAnalysis->confidence_score * 100) }}%
                                                                </div>
                                                            </div>
                                                        @else
                                                            <span class="text-muted">-</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($textAnalysis->categories)
                                                            @foreach(json_decode($textAnalysis->categories, true) as $category)
                                                                <span class="badge bg-info me-1">{{ $category }}</span>
                                                            @endforeach
                                                        @else
                                                            <span class="text-muted">-</span>
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
                                        <h3 class="text-primary">{{ number_format($statistics['overall_accuracy'] * 100, 1) }}%</h3>
                                    </div>
                                </div>
                                <hr>
                                <dl class="row mb-0">
                                    <dt class="col-6">Tikslumas:</dt>
                                    <dd class="col-6">{{ number_format($statistics['precision'] * 100, 1) }}%</dd>
                                    <dt class="col-6">Atsaukimas:</dt>
                                    <dd class="col-6">{{ number_format($statistics['recall'] * 100, 1) }}%</dd>
                                    <dt class="col-6">F1 balas:</dt>
                                    <dd class="col-6">{{ number_format($statistics['f1_score'] * 100, 1) }}%</dd>
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
                                {{ $textAnalysis->text_content }}
                            </div>
                        </div>
                        
                        @if($textAnalysis->analysis_result)
                            <div class="mb-3">
                                <label class="form-label fw-bold">AI analizės rezultatas:</label>
                                <div class="border p-3 rounded">
                                    <pre class="mb-0">{{ $textAnalysis->analysis_result }}</pre>
                                </div>
                            </div>
                        @endif

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
@endsection