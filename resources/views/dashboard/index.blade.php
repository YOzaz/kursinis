@extends('layout')

@section('title', 'Dashboard - Propagandos analizės sistema')

@section('content')
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
        </h1>
        <div>
            <button class="btn btn-outline-primary" id="exportStatsBtn">
                <i class="fas fa-download me-1"></i>Eksportuoti statistikas
            </button>
        </div>
    </div>

    <!-- Enhanced Quick Actions Bar -->
    <div class="card mb-4 border-primary quick-actions-card">
        <div class="card-header bg-primary text-white d-flex align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-bolt me-2"></i>Greiti veiksmai
            </h5>
            <span class="badge bg-light text-primary ms-2">Dažniausiai naudojamos funkcijos</span>
        </div>
        <div class="card-body py-3">
            <div class="row g-3">
                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('create') }}" class="btn btn-primary btn-lg w-100 quick-action-btn">
                        <i class="fas fa-plus fa-2x mb-2 d-block"></i>
                        <div class="fw-bold">Nauja analizė</div>
                        <small class="opacity-75">Sukurti naują teksto analizę</small>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="{{ route('analyses.index') }}" class="btn btn-outline-primary btn-lg w-100 quick-action-btn">
                        <i class="fas fa-list fa-2x mb-2 d-block"></i>
                        <div class="fw-bold">Visos analizės</div>
                        <small class="opacity-75">Peržiūrėti visas analizės</small>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <a href="/mission-control" class="btn btn-outline-info btn-lg w-100 quick-action-btn">
                        <i class="fas fa-satellite-dish fa-2x mb-2 d-block"></i>
                        <div class="fw-bold">Mission Control</div>
                        <small class="opacity-75">Sistemos būklės monitoringas</small>
                    </a>
                </div>
                <div class="col-lg-3 col-md-6">
                    <button class="btn btn-outline-success btn-lg w-100 quick-action-btn" id="exportDashboardBtn">
                        <i class="fas fa-download fa-2x mb-2 d-block"></i>
                        <div class="fw-bold">Eksportuoti</div>
                        <small class="opacity-75">Atsisiųsti duomenis</small>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Global KPI Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">{{ $globalStats['total_analyses'] ?? 0 }}</h2>
                            <p class="mb-0 opacity-75">Viso analizių</p>
                            <small class="opacity-50">Su {{ $globalStats['total_texts'] ?? 0 }} tekstų</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-chart-line fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            @php
                                $avgF1 = 0;
                                $modelCount = count($globalStats['model_performance'] ?? []);
                                if ($modelCount > 0) {
                                    $totalF1 = array_sum(array_column($globalStats['model_performance'], 'avg_f1_score'));
                                    $avgF1 = $totalF1 / $modelCount;
                                }
                            @endphp
                            <h2 class="mb-1">{{ number_format($avgF1 * 100, 1) }}%</h2>
                            <p class="mb-0 opacity-75">Vidutinis F1 balas</p>
                            <small class="opacity-50">Visų modelių</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-bullseye fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">{{ count(config('llm.models', [])) }}</h2>
                            <p class="mb-0 opacity-75">Konfigūruoti modeliai</p>
                            <small class="opacity-50">Claude Opus/Sonnet 4, GPT-4o/4.1, Gemini Pro/Flash</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-robot fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card bg-gradient-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            @php
                                $avgExecutionTime = 0;
                                if (!empty($globalStats['avg_execution_times'])) {
                                    $avgExecutionTime = array_sum($globalStats['avg_execution_times']) / count($globalStats['avg_execution_times']);
                                }
                            @endphp
                            <h2 class="mb-1">{{ number_format($avgExecutionTime / 1000, 1) }}s</h2>
                            <p class="mb-0 opacity-75">Vidutinis laikas</p>
                            <small class="opacity-50">Analizės trukmė</small>
                        </div>
                        <div class="text-end">
                            <i class="fas fa-clock fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Modelių našumo palyginimas
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="performanceChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Propagandos technikų pasiskirstymas
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="techniquesChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Model Performance Comparison Table -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-trophy me-2"></i>Modelių našumo palyginimas
                    </h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="metricType" id="f1Score" checked>
                        <label class="btn btn-outline-primary" for="f1Score">F1 balas</label>
                        
                        <input type="radio" class="btn-check" name="metricType" id="precision">
                        <label class="btn btn-outline-primary" for="precision">Tikslumas</label>
                        
                        <input type="radio" class="btn-check" name="metricType" id="recall">
                        <label class="btn btn-outline-primary" for="recall">Atsaukimas</label>
                        
                        <input type="radio" class="btn-check" name="metricType" id="speed">
                        <label class="btn btn-outline-primary" for="speed">Greitis</label>
                    </div>
                </div>
                <div class="card-body">
                    @if(!empty($globalStats['model_performance']))
                        <div class="table-responsive">
                            <table class="table table-hover" id="performanceTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <i class="fas fa-robot me-1"></i>Modelis
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-chart-line me-1"></i>Analizių
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Kiek analizių atlikta šiuo modeliu"></i>
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-bullseye me-1"></i>F1 balas
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Harmoninė tikslumo ir atsaukimo vidurkis"></i>
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-crosshairs me-1"></i>Tikslumas
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Kiek AI rastų propagandos fragmentų yra teisingi"></i>
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-search me-1"></i>Atsaukimas
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Kokią dalį visų propagandos fragmentų AI surado"></i>
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-tachometer-alt me-1"></i>Greitis
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Vidutinė analizės trukmė"></i>
                                        </th>
                                        <th class="text-center">
                                            <i class="fas fa-star me-1"></i>Įvertis
                                            <i class="fas fa-question-circle text-muted ms-1" 
                                               data-bs-toggle="tooltip" 
                                               title="Bendras modelio įvertinimas"></i>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($globalStats['model_performance'] as $model => $stats)
                                        @php
                                            $f1Score = $stats['avg_f1_score'] ?? 0;
                                            $precision = $stats['avg_precision'] ?? 0;
                                            $recall = $stats['avg_recall'] ?? 0;
                                            $avgTime = $globalStats['avg_execution_times'][$model] ?? 0;
                                            
                                            // Calculate overall score (weighted average)
                                            $overallScore = ($f1Score * 0.5) + ($precision * 0.25) + ($recall * 0.25);
                                            
                                            // Determine performance level
                                            $performanceClass = $f1Score >= 0.7 ? 'success' : ($f1Score >= 0.4 ? 'warning' : 'danger');
                                            $performanceIcon = $f1Score >= 0.7 ? 'fas fa-check-circle' : ($f1Score >= 0.4 ? 'fas fa-exclamation-triangle' : 'fas fa-times-circle');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    @if(str_contains($model, 'claude'))
                                                        <i class="fas fa-brain text-primary me-2"></i>
                                                    @elseif(str_contains($model, 'gemini'))
                                                        <i class="fas fa-star text-warning me-2"></i>
                                                    @elseif(str_contains($model, 'gpt'))
                                                        <i class="fas fa-cog text-success me-2"></i>
                                                    @else
                                                        <i class="fas fa-robot text-secondary me-2"></i>
                                                    @endif
                                                    <strong>{{ $model }}</strong>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary">{{ $stats['total_analyses'] }}</span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <span class="me-2">{{ number_format($f1Score * 100, 1) }}%</span>
                                                    <div class="progress" style="width: 60px; height: 8px;">
                                                        <div class="progress-bar bg-{{ $performanceClass }}" 
                                                             style="width: {{ $f1Score * 100 }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">{{ number_format($precision * 100, 1) }}%</td>
                                            <td class="text-center">{{ number_format($recall * 100, 1) }}%</td>
                                            <td class="text-center">
                                                @if($avgTime > 0)
                                                    @if($avgTime < 1000)
                                                        {{ number_format($avgTime) }}ms
                                                    @else
                                                        {{ number_format($avgTime / 1000, 1) }}s
                                                    @endif
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <i class="{{ $performanceIcon }} text-{{ $performanceClass }} me-1"></i>
                                                <span class="text-{{ $performanceClass }}">
                                                    {{ number_format($overallScore * 100, 0) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Nėra našumo duomenų</h5>
                            <p class="text-muted">Paleiskite analizę, kad pamatytumėte modelių našumo statistikas</p>
                            <a href="{{ route('create') }}" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Pradėti analizę
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Stats -->
        <div class="col-lg-4">
            <!-- Model Rankings -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-medal me-2"></i>Modelių reitingas
                    </h6>
                </div>
                <div class="card-body">
                    @if(!empty($globalStats['model_performance']))
                        @php
                            $sortedModels = collect($globalStats['model_performance'])
                                ->sortByDesc('avg_f1_score')
                                ->take(5);
                        @endphp
                        @foreach($sortedModels as $model => $stats)
                            @php
                                $rank = $loop->iteration;
                                $medalClass = $rank === 1 ? 'text-warning' : ($rank === 2 ? 'text-secondary' : ($rank === 3 ? 'text-danger' : 'text-muted'));
                                $medalIcon = $rank <= 3 ? 'fas fa-medal' : 'fas fa-trophy';
                            @endphp
                            <div class="d-flex justify-content-between align-items-center mb-2 {{ $rank <= 3 ? 'p-2 bg-light rounded' : '' }}">
                                <div class="d-flex align-items-center">
                                    <i class="{{ $medalIcon }} {{ $medalClass }} me-2"></i>
                                    <span class="fw-bold">#{{ $rank }}</span>
                                    <span class="ms-2">{{ $model }}</span>
                                </div>
                                <span class="badge bg-primary">{{ number_format($stats['avg_f1_score'] * 100, 1) }}%</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted text-center">Nėra duomenų reitingui</p>
                    @endif
                </div>
            </div>

            <!-- Recent Analyses -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>Paskutinės analizės
                    </h6>
                    <a href="{{ route('analyses.index') }}" class="btn btn-sm btn-outline-primary">
                        Visos
                    </a>
                </div>
                <div class="card-body">
                    @if($recentAnalyses->count() > 0)
                        @foreach($recentAnalyses->take(5) as $analysis)
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                                <div class="flex-grow-1">
                                    <div class="fw-bold mb-1">
                                        {{ Str::limit($analysis->name ?? 'Analizė '.$analysis->job_id, 25) }}
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>{{ $analysis->created_at->diffForHumans() }}
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="mb-1">
                                        @switch($analysis->status)
                                            @case('completed')
                                                <span class="badge bg-success">Baigta</span>
                                                @break
                                            @case('processing')
                                                <span class="badge bg-warning">Vykdoma</span>
                                                @break
                                            @case('failed')
                                                <span class="badge bg-danger">Nepavyko</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ ucfirst($analysis->status) }}</span>
                                        @endswitch
                                    </div>
                                    <a href="{{ route('analyses.show', $analysis->job_id) }}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-3">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted">Nėra analizių</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <!-- Additional dashboard elements for tests -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>Time Series Analysis
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="timeSeriesChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
$(document).ready(function() {
    // Tooltips are now initialized globally in layout.blade.php

    // Initialize DataTable for performance table
    @if(!empty($globalStats['model_performance']))
    const performanceTable = $('#performanceTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[2, 'desc']], // Sort by F1 score descending
        columnDefs: [
            { 
                targets: [1, 2, 3, 4, 5, 6], // Numeric columns
                type: 'num-fmt'
            },
            {
                targets: [6], // Overall score column
                render: function(data, type, row) {
                    if (type === 'display') {
                        return data; // Keep the formatted display with icon
                    }
                    return parseFloat(data); // For sorting, use numeric value
                }
            }
        ],
        language: {
            "search": "Ieškoti:",
            "lengthMenu": "Rodyti _MENU_ įrašų puslapyje",
            "info": "Rodoma _START_ - _END_ iš _TOTAL_ įrašų",
            "infoEmpty": "Nėra duomenų",
            "infoFiltered": "(išfiltruota iš _MAX_ įrašų)",
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
    
    // Metric type switching
    const metricRadios = document.querySelectorAll('input[name="metricType"]');
    metricRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            let columnIndex;
            switch(this.id) {
                case 'f1Score':
                    columnIndex = 2;
                    break;
                case 'precision':
                    columnIndex = 3;
                    break;
                case 'recall':
                    columnIndex = 4;
                    break;
                case 'speed':
                    columnIndex = 5;
                    break;
                default:
                    columnIndex = 2;
            }
            
            // Clear previous sorting and sort by selected metric
            performanceTable.order([columnIndex, 'desc']).draw();
        });
    });
    @endif

    // Export functionality
    $('#exportStatsBtn, #exportDashboardBtn').on('click', function() {
        window.location.href = '/api/dashboard/export';
    });
});
</script>

<style>
.bg-gradient-primary {
    background: linear-gradient(45deg, #007bff, #0056b3);
}

.bg-gradient-success {
    background: linear-gradient(45deg, #28a745, #1e7e34);
}

.bg-gradient-info {
    background: linear-gradient(45deg, #17a2b8, #117a8b);
}

.bg-gradient-warning {
    background: linear-gradient(45deg, #ffc107, #d39e00);
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.card:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transition: box-shadow 0.15s ease-in-out;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}

.progress {
    background-color: #e9ecef;
}

/* Enhanced Quick Actions Styling */
.quick-actions-card {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-width: 2px;
}

.quick-action-btn {
    height: 120px;
    border-width: 2px;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.quick-action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.quick-action-btn i {
    transition: transform 0.3s ease;
}

.quick-action-btn:hover i {
    transform: scale(1.1);
}

.quick-action-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s ease;
}

.quick-action-btn:hover::after {
    left: 100%;
}
</style>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Model Performance Chart
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    const performanceData = @json($globalStats['model_performance'] ?? []);
    
    const modelNames = Object.keys(performanceData);
    const f1Scores = modelNames.map(model => (performanceData[model]['avg_f1_score'] || 0) * 100);
    const precisionScores = modelNames.map(model => (performanceData[model]['avg_precision'] || 0) * 100);
    const recallScores = modelNames.map(model => (performanceData[model]['avg_recall'] || 0) * 100);
    
    const performanceChart = new Chart(performanceCtx, {
        type: 'bar',
        data: {
            labels: modelNames,
            datasets: [{
                label: 'F1 Score (%)',
                data: f1Scores,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }, {
                label: 'Precision (%)',
                data: precisionScores,
                backgroundColor: 'rgba(255, 99, 132, 0.7)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            }, {
                label: 'Recall (%)',
                data: recallScores,
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Modelių našumo metrikos'
                },
                legend: {
                    position: 'top'
                }
            }
        }
    });

    // Techniques Distribution Chart
    const techniquesCtx = document.getElementById('techniquesChart').getContext('2d');
    const techniqueStats = @json($globalStats['top_techniques'] ?? []);
    
    const techniqueLabels = Object.keys(techniqueStats);
    const techniqueCounts = Object.values(techniqueStats);
    
    // Generate colors for techniques
    const colors = [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
        '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
    ];
    
    const techniquesChart = new Chart(techniquesCtx, {
        type: 'doughnut',
        data: {
            labels: techniqueLabels,
            datasets: [{
                data: techniqueCounts,
                backgroundColor: colors.slice(0, techniqueLabels.length),
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Dažniausiai aptiktos technikos'
                },
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 15,
                        padding: 15
                    }
                }
            }
        }
    });

    // Time Series Chart
    const timeSeriesCtx = document.getElementById('timeSeriesChart').getContext('2d');
    const timeSeriesData = @json($globalStats['time_series_data'] ?? []);
    
    const timeSeriesLabels = timeSeriesData.map(item => item.label);
    const timeSeriesCounts = timeSeriesData.map(item => item.count);
    
    const timeSeriesChart = new Chart(timeSeriesCtx, {
        type: 'line',
        data: {
            labels: timeSeriesLabels,
            datasets: [{
                label: 'Analyses per Day',
                data: timeSeriesCounts,
                borderColor: 'rgba(54, 162, 235, 1)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            return Number.isInteger(value) ? value : '';
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Analizių skaičius per paskutines 30 dienų'
                },
                legend: {
                    display: false
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            return timeSeriesData[index].date;
                        },
                        label: function(context) {
                            const count = context.parsed.y;
                            return count === 1 ? '1 analizė' : count + ' analizės';
                        }
                    }
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            }
        }
    });
});
</script>

@endsection