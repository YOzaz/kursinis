@extends('layout')

@section('title', $experiment->name)

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $experiment->name }}</h1>
        <div>
            @if($experiment->results->count() > 0)
                <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download"></i> Eksportuoti
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('experiments.export-csv', $experiment) }}">
                            <i class="fas fa-file-csv"></i> Rezultatai (CSV)
                        </a></li>
                        <li><a class="dropdown-item" href="{{ route('experiments.export-stats-csv', $experiment) }}">
                            <i class="fas fa-chart-bar"></i> Statistikos (CSV)
                        </a></li>
                        <li><a class="dropdown-item" href="{{ route('experiments.export-json', $experiment) }}">
                            <i class="fas fa-file-code"></i> Pilni duomenys (JSON)
                        </a></li>
                    </ul>
                </div>
            @endif
            <a href="{{ route('experiments.edit', $experiment) }}" class="btn btn-outline-secondary">
                <i class="fas fa-edit"></i> Redaguoti
            </a>
            <a href="{{ route('experiments.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Atgal
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Eksperimento informacija</h5>
                    <span class="badge badge-{{ $experiment->status === 'completed' ? 'success' : ($experiment->status === 'running' ? 'warning' : ($experiment->status === 'failed' ? 'danger' : 'secondary')) }}">
                        {{ ucfirst($experiment->status) }}
                    </span>
                </div>
                <div class="card-body">
                    @if($experiment->description)
                        <p><strong>Aprašymas:</strong> {{ $experiment->description }}</p>
                    @endif
                    
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Sukurta:</strong> {{ $experiment->created_at->format('Y-m-d H:i') }}</p>
                            @if($experiment->started_at)
                                <p><strong>Pradėta:</strong> {{ $experiment->started_at->format('Y-m-d H:i') }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($experiment->completed_at)
                                <p><strong>Užbaigta:</strong> {{ $experiment->completed_at->format('Y-m-d H:i') }}</p>
                            @endif
                            <p><strong>Darbų:</strong> {{ $experiment->analysisJobs->count() }}</p>
                            <p><strong>Rezultatų:</strong> {{ $experiment->results->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Naudotas prompt</h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;">{{ $experiment->custom_prompt }}</pre>
                </div>
            </div>

            @if($experiment->analysisJobs->count() > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Analizės darbai</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Darbo ID</th>
                                        <th>Statusas</th>
                                        <th>Tekstų</th>
                                        <th>Progresas</th>
                                        <th>Sukurta</th>
                                        <th>Veiksmai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($experiment->analysisJobs as $job)
                                        <tr>
                                            <td><code>{{ Str::limit($job->job_id, 8) }}</code></td>
                                            <td>
                                                <span class="badge badge-{{ $job->status === 'completed' ? 'success' : ($job->status === 'processing' ? 'warning' : ($job->status === 'failed' ? 'danger' : 'secondary')) }}">
                                                    {{ ucfirst($job->status) }}
                                                </span>
                                            </td>
                                            <td>{{ $job->processed_texts }}/{{ $job->total_texts }}</td>
                                            <td>
                                                <div class="progress" style="width: 100px;">
                                                    <div class="progress-bar" style="width: {{ $job->getProgressPercentage() }}%"></div>
                                                </div>
                                                <small>{{ number_format($job->getProgressPercentage(), 1) }}%</small>
                                            </td>
                                            <td>{{ $job->created_at->format('m-d H:i') }}</td>
                                            <td>
                                                <a href="{{ route('progress', $job->job_id) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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
            <div class="card mb-4">
                <div class="card-header">
                    <h6>Paleisti analizę</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                        @csrf
                        <input type="hidden" name="experiment_id" value="{{ $experiment->id }}">
                        
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV failas</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">Pirmoji kolona turi būti tekstas, antroji - ekspertų anotacijos (JSON)</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pasirinkite modelius:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="claude" name="models[]" value="claude-4">
                                <label class="form-check-label" for="claude">Claude 3.5 Sonnet</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="gemini" name="models[]" value="gemini-2.5-pro">
                                <label class="form-check-label" for="gemini">Gemini 2.5 Pro Preview</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="openai" name="models[]" value="gpt-4.1">
                                <label class="form-check-label" for="openai">GPT-4o</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-play"></i> Pradėti analizę
                        </button>
                    </form>
                </div>
            </div>

            @if($experiment->risen_config)
                <div class="card">
                    <div class="card-header">
                        <h6>RISEN konfigūracija</h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="mb-2">
                                <strong>Role:</strong>
                                <div class="text-muted">{{ Str::limit($experiment->risen_config['role'] ?? '', 100) }}</div>
                            </div>
                            <div class="mb-2">
                                <strong>Instructions:</strong>
                                <div class="text-muted">{{ Str::limit($experiment->risen_config['instructions'] ?? '', 100) }}</div>
                            </div>
                            <div class="mb-2">
                                <strong>Situation:</strong>
                                <div class="text-muted">{{ Str::limit($experiment->risen_config['situation'] ?? '', 100) }}</div>
                            </div>
                            <div class="mb-2">
                                <strong>Execution:</strong>
                                <div class="text-muted">{{ Str::limit($experiment->risen_config['execution'] ?? '', 100) }}</div>
                            </div>
                            <div>
                                <strong>Needle:</strong>
                                <div class="text-muted">{{ Str::limit($experiment->risen_config['needle'] ?? '', 100) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Statistics and Charts Section -->
    @if(!empty($statistics['models']) && count($statistics['models']) > 0)
        <div class="row mt-4">
            <div class="col-12">
                <h3>Eksperimento statistikos</h3>
            </div>
        </div>

        <!-- Model Statistics Cards -->
        <div class="row mb-4">
            @foreach($statistics['models'] as $model => $modelStats)
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-header">
                            <h6>{{ $model }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="small text-muted">Analizių</div>
                                    <div class="h5">{{ $modelStats['total_analyses'] }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Vid. laikas</div>
                                    <div class="h6">{{ number_format($modelStats['avg_execution_time'], 2) }}s</div>
                                </div>
                            </div>
                            <hr>
                            <div class="mb-2">
                                <small><strong>Precision:</strong> {{ number_format($modelStats['avg_precision'], 3) }}</small>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-info" style="width: {{ $modelStats['avg_precision'] * 100 }}%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <small><strong>Recall:</strong> {{ number_format($modelStats['avg_recall'], 3) }}</small>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-warning" style="width: {{ $modelStats['avg_recall'] * 100 }}%"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <small><strong>F1:</strong> {{ number_format($modelStats['avg_f1'], 3) }}</small>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: {{ $modelStats['avg_f1'] * 100 }}%"></div>
                                </div>
                            </div>
                            <div>
                                <small><strong>Kappa:</strong> {{ number_format($modelStats['avg_kappa'], 3) }}</small>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-primary" style="width: {{ abs($modelStats['avg_kappa']) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Charts Section -->
        @if(!empty($statistics['charts']))
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6>Metrikų palyginimas</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="metricsChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h6>Vykdymo laikai</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="timeChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Model Comparison Table -->
        @if(!empty($statistics['comparison']))
            <div class="card">
                <div class="card-header">
                    <h6>Detalus modelių palyginimas</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Modelis</th>
                                    <th>Precision</th>
                                    <th>Recall</th>
                                    <th>F1 Score</th>
                                    <th>Cohen's Kappa</th>
                                    <th>Standartinis nuokrypis</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statistics['comparison'] as $model => $metrics)
                                    <tr>
                                        <td><strong>{{ $model }}</strong></td>
                                        <td>
                                            {{ number_format($metrics['precision']['avg'], 3) }}
                                            <small class="text-muted">(±{{ number_format($metrics['precision']['std'], 3) }})</small>
                                        </td>
                                        <td>
                                            {{ number_format($metrics['recall']['avg'], 3) }}
                                            <small class="text-muted">(±{{ number_format($metrics['recall']['std'], 3) }})</small>
                                        </td>
                                        <td>
                                            {{ number_format($metrics['f1_score']['avg'], 3) }}
                                            <small class="text-muted">(±{{ number_format($metrics['f1_score']['std'], 3) }})</small>
                                        </td>
                                        <td>
                                            {{ number_format($metrics['cohens_kappa']['avg'], 3) }}
                                            <small class="text-muted">(±{{ number_format($metrics['cohens_kappa']['std'], 3) }})</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $metrics['f1_score']['std'] < 0.1 ? 'success' : ($metrics['f1_score']['std'] < 0.2 ? 'warning' : 'danger') }}">
                                                {{ number_format($metrics['f1_score']['std'], 3) }}
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
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statisticsData = @json($statistics ?? []);
    
    if (statisticsData.charts && statisticsData.charts.metrics_comparison) {
        // Metrics Comparison Chart
        const ctx1 = document.getElementById('metricsChart');
        if (ctx1) {
            const metricsData = statisticsData.charts.metrics_comparison;
            
            new Chart(ctx1, {
                type: 'radar',
                data: {
                    labels: ['Precision', 'Recall', 'F1 Score', 'Cohen\'s Kappa'],
                    datasets: metricsData.map((modelData, index) => ({
                        label: modelData.model,
                        data: [
                            modelData.precision,
                            modelData.recall,
                            modelData.f1_score,
                            Math.abs(modelData.cohens_kappa)
                        ],
                        backgroundColor: `rgba(${index * 80 + 50}, ${index * 60 + 100}, ${index * 40 + 150}, 0.2)`,
                        borderColor: `rgba(${index * 80 + 50}, ${index * 60 + 100}, ${index * 40 + 150}, 1)`,
                        borderWidth: 2
                    }))
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
        }
    }

    if (statisticsData.charts && statisticsData.charts.execution_time) {
        // Execution Time Chart
        const ctx2 = document.getElementById('timeChart');
        if (ctx2) {
            const timeData = statisticsData.charts.execution_time;
            
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: timeData.map(d => d.model),
                    datasets: [{
                        label: 'Vidutinis laikas (s)',
                        data: timeData.map(d => d.avg_time),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)'
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
    }
});
</script>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const models = document.querySelectorAll('input[name="models[]"]:checked');
    if (models.length === 0) {
        e.preventDefault();
        alert('Pasirinkite bent vieną modelį analizei.');
        return;
    }
    
    const fileInput = document.getElementById('csv_file');
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Pasirinkite CSV failą.');
        return;
    }
});
</script>
@endsection