@extends('layout')

@section('title', 'Dashboard')

@section('content')
<div class="container mt-4">
    <h1 class="mb-4">Dashboard</h1>

    <!-- Global Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>{{ $globalStats['total_analyses'] ?? 0 }}</h4>
                            <p class="mb-0">Analizių</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>{{ $globalStats['total_texts'] ?? 0 }}</h4>
                            <p class="mb-0">Tekstų</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-text fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>{{ count($globalStats['model_performance'] ?? []) }}</h4>
                            <p class="mb-0">Modelių</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-robot fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>{{ $globalStats['total_metrics'] ?? 0 }}</h4>
                            <p class="mb-0">Metrikų</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-bar fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Analyses -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Paskutinės analizės</h6>
                </div>
                <div class="card-body">
                    @if($recentAnalyses->count() > 0)
                        @foreach($recentAnalyses as $analysis)
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div>
                                    <strong>{{ Str::limit($analysis->name ?? 'Analizė '.$analysis->job_id, 30) }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $analysis->created_at->format('Y-m-d H:i') }}</small>
                                </div>
                                <div>
                                    <span class="badge badge-{{ $analysis->status === 'completed' ? 'success' : ($analysis->status === 'processing' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($analysis->status) }}
                                    </span>
                                    <a href="{{ route('analyses.show', $analysis->job_id) }}" class="btn btn-sm btn-outline-primary ml-2">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('analyses.index') }}" class="btn btn-sm btn-outline-primary">
                                Peržiūrėti visas analizės
                            </a>
                        </div>
                    @else
                        <p class="text-muted text-center">Nėra analizių</p>
                        <div class="text-center">
                            <a href="{{ route('home') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Pradėti analizę
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Model Performance -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Modelių našumas</h6>
                </div>
                <div class="card-body">
                    @if(!empty($globalStats['model_performance']))
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Modelis</th>
                                        <th>Analizių</th>
                                        <th>Avg F1</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($globalStats['model_performance'] as $model => $stats)
                                        <tr>
                                            <td>{{ $model }}</td>
                                            <td>{{ $stats['total_analyses'] }}</td>
                                            <td>{{ number_format($stats['avg_f1_score'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted text-center">Nėra metrikų duomenų</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection