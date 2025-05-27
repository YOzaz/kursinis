@extends('layout')

@section('title', 'Analizių sąrašas')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Analizių sąrašas</h1>
            <p class="text-muted mb-0">Visos atliktos propagandos analizės</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nauja analizė
        </a>
    </div>

    <div class="alert alert-info mb-4">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-info-circle fa-lg"></i>
            </div>
            <div>
                <h6 class="alert-heading mb-2">Analizių tipai</h6>
                <ul class="mb-0">
                    <li><strong>Su ekspertų anotacijomis</strong> - palyginamos AI ir ekspertų anotacijos, skaičiuojamos metrikos</li>
                    <li><strong>Be ekspertų anotacijų</strong> - tik AI analizė naujam tekstui</li>
                    <li><strong>Eksperimentinės</strong> - naudojant custom prompt'us iš <a href="{{ route('experiments.index') }}" class="alert-link">eksperimentų</a></li>
                </ul>
            </div>
        </div>
    </div>

    @if($analyses->count() > 0)
        <div class="row">
            @foreach($analyses as $analysis)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                @if($analysis->experiment_id)
                                    <i class="fas fa-flask text-warning me-1"></i>Eksperimentas
                                @else
                                    <i class="fas fa-chart-line text-primary me-1"></i>Standartinė analizė
                                @endif
                            </h6>
                            <span class="badge badge-{{ $analysis->status === 'completed' ? 'success' : ($analysis->status === 'processing' ? 'warning' : ($analysis->status === 'failed' ? 'danger' : 'secondary')) }}">
                                {{ ucfirst($analysis->status) }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="small text-muted mb-3">
                                <div><strong>ID:</strong> {{ $analysis->job_id }}</div>
                                <div><strong>Sukurta:</strong> {{ $analysis->created_at->format('Y-m-d H:i') }}</div>
                                @if($analysis->started_at)
                                    <div><strong>Pradėta:</strong> {{ $analysis->started_at->format('Y-m-d H:i') }}</div>
                                @endif
                                @if($analysis->completed_at)
                                    <div><strong>Užbaigta:</strong> {{ $analysis->completed_at->format('Y-m-d H:i') }}</div>
                                    <div><strong>Trukmė:</strong> {{ $analysis->started_at->diffInSeconds($analysis->completed_at) }}s</div>
                                @endif
                            </div>

                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="small text-muted">Tekstų</div>
                                    <div class="h5">{{ $analysis->textAnalyses->count() }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Modelių</div>
                                    <div class="h5">{{ count(explode(',', $analysis->models ?? '')) }}</div>
                                </div>
                            </div>

                            @if($analysis->status === 'completed' && $analysis->textAnalyses->count() > 0)
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> Analizė baigta sėkmingai
                                </small>
                            @elseif($analysis->status === 'processing')
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: 60%"></div>
                                </div>
                                <small class="text-warning">
                                    <i class="fas fa-cog fa-spin"></i> Analizė vykdoma...
                                </small>
                            @elseif($analysis->status === 'failed')
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-danger" style="width: 30%"></div>
                                </div>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> Analizė nepavyko
                                </small>
                            @endif
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                @if($analysis->status === 'completed')
                                    <a href="{{ route('analyses.show', $analysis->job_id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Peržiūrėti
                                    </a>
                                @else
                                    <a href="{{ route('progress', $analysis->job_id) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-clock"></i> Statusas
                                    </a>
                                @endif
                                
                                @if($analysis->experiment_id)
                                    <a href="{{ route('experiments.show', $analysis->experiment_id) }}" class="btn btn-sm btn-outline-warning">
                                        <i class="fas fa-flask"></i> Eksperimentas
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {{ $analyses->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
            <h3>Nėra analizių</h3>
            <p class="text-muted">
                Dar nėra atlikta jokių propagandos analizių.<br>
                Pradėkite naują analizę įkeldami JSON failą su tekstais.
            </p>
            <a href="{{ route('home') }}" class="btn btn-primary">
                <i class="fas fa-upload"></i> Pradėti analizę
            </a>
        </div>
    @endif
</div>
@endsection