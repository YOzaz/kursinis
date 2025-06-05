@extends('layout')

@section('title', 'Analizės progresas - Propagandos analizės sistema')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-chart-line me-2"></i>
                    Analizės progresas
                </h3>
            </div>
            <div class="card-body">
                <!-- Darbo informacija -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <h6 class="fw-bold">Darbo ID:</h6>
                        <p class="font-monospace">{{ $job->job_id }}</p>
                    </div>
                    <div class="col-md-4">
                        @if($job->name)
                            <h6 class="fw-bold">Pavadinimas:</h6>
                            <p>{{ $job->name }}</p>
                        @endif
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold">Sukurta:</h6>
                        <p>{{ $job->created_at->format('Y-m-d H:i:s') }}</p>
                        <div class="auto-refresh-status">
                            <i class="fas fa-sync-alt me-1"></i>Auto-refresh active
                        </div>
                    </div>
                </div>

                <!-- Mission Control Links -->
                <div class="text-center mb-4">
                    <a href="{{ route('mission-control') }}?job_id={{ $job->job_id }}" class="btn btn-outline-primary" target="_blank">
                        <i class="fas fa-satellite-dish me-2"></i>
                        🤖 Mission Control (Filtered)
                        <i class="fas fa-external-link-alt ms-2"></i>
                    </a>
                    <a href="{{ route('mission-control') }}" class="btn btn-outline-secondary" target="_blank">
                        <i class="fas fa-chart-line me-2"></i>
                        System-Wide View
                    </a>
                    <div class="text-muted mt-2">
                        <small>Advanced real-time monitoring with technical details and system-wide status</small>
                    </div>
                </div>

                <!-- Statuso indikatorius -->
                <div class="text-center mb-4">
                    <div id="statusIndicator">
                        @if($job->status === 'pending')
                            <div class="progress-circle"></div>
                            <span class="badge status-badge bg-warning">Laukiama</span>
                        @elseif($job->status === 'processing')
                            <div class="progress-circle"></div>
                            <span class="badge status-badge bg-primary">Apdorojama</span>
                        @elseif($job->status === 'completed')
                            <i class="fas fa-check-circle fa-5x text-success mb-3"></i><br>
                            <span class="badge status-badge bg-success">Baigta</span>
                        @elseif($job->status === 'failed')
                            <i class="fas fa-times-circle fa-5x text-danger mb-3"></i><br>
                            <span class="badge status-badge bg-danger">Nepavyko</span>
                        @endif
                    </div>
                </div>

                <!-- Progreso juosta -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="fw-bold">Progresas:</span>
                        <span id="progressText">{{ $job->processed_texts }} / {{ $job->total_texts }}</span>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-primary progress-bar-striped" 
                             id="progressBar"
                             role="progressbar" 
                             style="width: {{ $job->getProgressPercentage() }}%"
                             aria-valuenow="{{ $job->getProgressPercentage() }}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <span id="progressPercent">{{ round($job->getProgressPercentage(), 1) }}%</span>
                        </div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted" id="progressExplanation">
                            @php
                                $textCount = \App\Models\TextAnalysis::where('job_id', $job->job_id)->distinct('text_id')->count();
                                $modelCount = $job->models ? count(json_decode($job->models, true)) : 0;
                            @endphp
                            @if($textCount > 0 && $modelCount > 0)
                                {{ $textCount }} tekstų analizė su {{ $modelCount }} modeliais (failo apdorojimas)
                            @else
                                Analizės darbų progreso sekimas
                            @endif
                        </small>
                    </div>
                </div>

                <!-- Klaidos pranešimas -->
                @if($job->error_message)
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Klaida:</strong> {{ $job->error_message }}
                    </div>
                @endif

                <!-- Veiksmai -->
                <div class="d-grid gap-2">
                    @if($job->status === 'completed')
                        <a href="{{ route('analyses.show', ['jobId' => $job->job_id]) }}" 
                           class="btn btn-success btn-lg">
                            <i class="fas fa-chart-line me-2"></i>
                            Peržiūrėti detalų analizės puslapį
                        </a>
                        <a href="{{ route('api.results.get', ['jobId' => $job->job_id]) }}" 
                           class="btn btn-outline-info btn-lg" target="_blank">
                            <i class="fas fa-eye me-2"></i>
                            Peržiūrėti rezultatus (JSON)
                        </a>
                        <a href="{{ route('api.results.export', ['jobId' => $job->job_id]) }}" 
                           class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-download me-2"></i>
                            Atsisiųsti CSV failą
                        </a>
                    @endif
                    
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>
                        Grįžti į pradžią
                    </a>
                </div>

                <!-- Atnaujinimo info -->
                @if(in_array($job->status, ['pending', 'processing']))
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-sync-alt me-1"></i>
                            Puslapis automatiškai atsinaujina kas 5 sekundes
                        </small>
                    </div>
                @endif
            </div>
        </div>

        <!-- Papildoma informacija -->
        @if($job->status === 'completed')
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Analizės informacija
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Apdoroti analizės darbai:</h6>
                            <p>{{ $job->processed_texts }}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Viso analizės darbų:</h6>
                            <p>{{ $job->total_texts }}</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Failo apdorojimas:</strong> Naudojama file attachment metodika - visi tekstai siunčiami kiekvienam modeliui vienu kartu. 
                        Progresas atsispindi pagal užbaigtus modelius.
                        @php
                            $textCount = \App\Models\TextAnalysis::where('job_id', $job->job_id)->distinct('text_id')->count();
                            $modelCount = $job->models ? count(json_decode($job->models, true)) : 0;
                        @endphp
                        @if($textCount > 0 && $modelCount > 0)
                            <br><small><strong>Šis darbas:</strong> {{ $textCount }} tekstai × {{ $modelCount }} modeliai = {{ $job->total_texts }} analizės</small>
                        @endif
                    </div>
                    
                    <hr>
                    
                    <h6 class="fw-bold mb-3">Analizuojamos propagandos technikos:</h6>
                    <div class="row">
                        @php
                            $techniques = config('llm.propaganda_techniques');
                        @endphp
                        @foreach($techniques as $key => $description)
                            <div class="col-md-6 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-tag text-primary me-2"></i>
                                    <div>
                                        <strong>{{ $key }}</strong>
                                        <small class="d-block text-muted">{{ $description }}</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
@if(in_array($job->status, ['pending', 'processing']))
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Automatinis atnaujinimas kas 5 sekundes
    function updateProgress() {
        fetch(`{{ route('api.status.get', ['jobId' => $job->job_id]) }}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Klaida gaunant statusą:', data.error);
                    return;
                }

                // Atnaujinti progreso informaciją
                const progressPercent = Math.round((data.processed_texts / data.total_texts) * 100);
                
                document.getElementById('progressText').textContent = 
                    `${data.processed_texts} / ${data.total_texts}`;
                document.getElementById('progressBar').style.width = `${progressPercent}%`;
                document.getElementById('progressBar').setAttribute('aria-valuenow', progressPercent);
                document.getElementById('progressPercent').textContent = `${progressPercent}%`;

                // Atnaujinti statusą
                if (data.status === 'completed') {
                    // Perkrauti puslapį, kad parodytų rezultatus
                    location.reload();
                } else if (data.status === 'failed') {
                    // Perkrauti puslapį, kad parodytų klaidos pranešimą
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Fetch klaida:', error);
            });
    }

    // Paleisti periodinį atnaujinimą
    setInterval(updateProgress, 5000);
});
</script>
@endif
@endsection