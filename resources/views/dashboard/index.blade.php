@extends('layout')

@section('title', 'Statistikos dashboard')

@section('content')
<div class="container mt-4">
    <h1 class="mb-4">Statistikos dashboard</h1>

    <!-- Global Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>{{ $globalStats['total_experiments'] ?? 0 }}</h4>
                            <p class="mb-0">Eksperimentų</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-flask fa-2x"></i>
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
                            <h4>{{ count($globalStats['recent_activity'] ?? []) }}</h4>
                            <p class="mb-0">Paskutinių veiksmų</p>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Model Performance Chart -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Modelių našumo palyginimas</h5>
                </div>
                <div class="card-body">
                    <canvas id="modelPerformanceChart"></canvas>
                </div>
            </div>

            <!-- Execution Time Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Vykdymo laiko palyginimas</h5>
                </div>
                <div class="card-body">
                    <canvas id="executionTimeChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Experiments and Activity -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Paskutiniai eksperimentai</h6>
                </div>
                <div class="card-body">
                    @if($experiments->count() > 0)
                        @foreach($experiments as $experiment)
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div>
                                    <strong>{{ Str::limit($experiment->name, 20) }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $experiment->created_at->format('m-d H:i') }}</small>
                                </div>
                                <div>
                                    <span class="badge badge-{{ $experiment->status === 'completed' ? 'success' : ($experiment->status === 'running' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($experiment->status) }}
                                    </span>
                                    <a href="{{ route('experiments.show', $experiment) }}" class="btn btn-sm btn-outline-primary ml-2">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                        <div class="text-center mt-3">
                            <a href="{{ route('experiments.index') }}" class="btn btn-sm btn-outline-primary">
                                Peržiūrėti visus eksperimentus
                            </a>
                        </div>
                    @else
                        <p class="text-muted text-center">Nėra eksperimentų</p>
                        <div class="text-center">
                            <a href="{{ route('experiments.create') }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Sukurti eksperimentą
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            @if(!empty($globalStats['recent_activity']))
                <div class="card">
                    <div class="card-header">
                        <h6>Paskutinė veikla</h6>
                    </div>
                    <div class="card-body">
                        @foreach($globalStats['recent_activity'] as $activity)
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <strong>{{ Str::limit($activity['experiment_name'], 15) }}</strong>
                                    <br>
                                    <small class="text-muted">{{ $activity['model'] }} • {{ $activity['created_at'] }}</small>
                                </div>
                                <div class="text-right">
                                    <small class="text-info">{{ $activity['execution_time'] }}s</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Model Performance Table -->
    @if(!empty($globalStats['model_performance']))
        <div class="card">
            <div class="card-header">
                <h5>Detalus modelių našumas</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Modelis</th>
                                <th>Analizių</th>
                                <th>Vidutinis Precision</th>
                                <th>Vidutinis Recall</th>
                                <th>Vidutinis F1</th>
                                <th>Vykdymo laikas (s)</th>
                                <th>Patikimumo įvertis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($globalStats['model_performance'] as $model => $performance)
                                <tr>
                                    <td><strong>{{ $model }}</strong></td>
                                    <td>{{ $performance['total_analyses'] }}</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-info" style="width: {{ $performance['avg_precision'] * 100 }}%">
                                                {{ number_format($performance['avg_precision'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-warning" style="width: {{ $performance['avg_recall'] * 100 }}%">
                                                {{ number_format($performance['avg_recall'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar bg-success" style="width: {{ $performance['avg_f1'] * 100 }}%">
                                                {{ number_format($performance['avg_f1'], 3) }}
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ number_format($performance['avg_execution_time'], 2) }}</td>
                                    <td>
                                        <span class="badge badge-{{ $performance['reliability_score'] > 0.8 ? 'success' : ($performance['reliability_score'] > 0.6 ? 'warning' : 'danger') }}">
                                            {{ number_format($performance['reliability_score'], 3) }}
                                        </span>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Model Performance Chart
    const modelPerformanceData = @json($globalStats['model_performance'] ?? []);
    
    if (Object.keys(modelPerformanceData).length > 0) {
        const ctx1 = document.getElementById('modelPerformanceChart').getContext('2d');
        
        const models = Object.keys(modelPerformanceData);
        const precisionData = models.map(model => modelPerformanceData[model].avg_precision);
        const recallData = models.map(model => modelPerformanceData[model].avg_recall);
        const f1Data = models.map(model => modelPerformanceData[model].avg_f1);

        new Chart(ctx1, {
            type: 'radar',
            data: {
                labels: models,
                datasets: [
                    {
                        label: 'Precision',
                        data: precisionData,
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'Recall',
                        data: recallData,
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 2
                    },
                    {
                        label: 'F1 Score',
                        data: f1Data,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 0.2
                        }
                    }
                }
            }
        });

        // Execution Time Chart
        const ctx2 = document.getElementById('executionTimeChart').getContext('2d');
        const executionTimes = models.map(model => modelPerformanceData[model].avg_execution_time);

        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: models,
                datasets: [{
                    label: 'Vidutinis vykdymo laikas (s)',
                    data: executionTimes,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Laikas (sekundės)'
                        }
                    }
                }
            }
        });
    }
});
</script>
@endsection