@extends('layout')

@section('title', 'Eksperimentai')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Eksperimentai</h1>
        <a href="{{ route('experiments.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Naujas eksperimentas
        </a>
    </div>

    @if($experiments->count() > 0)
        <div class="row">
            @foreach($experiments as $experiment)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">{{ $experiment->name }}</h5>
                            <span class="badge badge-{{ $experiment->status === 'completed' ? 'success' : ($experiment->status === 'running' ? 'warning' : ($experiment->status === 'failed' ? 'danger' : 'secondary')) }}">
                                {{ ucfirst($experiment->status) }}
                            </span>
                        </div>
                        <div class="card-body">
                            @if($experiment->description)
                                <p class="card-text">{{ Str::limit($experiment->description, 100) }}</p>
                            @endif
                            
                            <div class="small text-muted mb-3">
                                <div><strong>Sukurta:</strong> {{ $experiment->created_at->format('Y-m-d H:i') }}</div>
                                @if($experiment->started_at)
                                    <div><strong>Pradėta:</strong> {{ $experiment->started_at->format('Y-m-d H:i') }}</div>
                                @endif
                                @if($experiment->completed_at)
                                    <div><strong>Užbaigta:</strong> {{ $experiment->completed_at->format('Y-m-d H:i') }}</div>
                                @endif
                            </div>

                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="small text-muted">Darbų</div>
                                    <div class="h5">{{ $experiment->analysisJobs->count() }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Rezultatų</div>
                                    <div class="h5">{{ $experiment->results->count() }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('experiments.show', $experiment) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i> Peržiūrėti
                                </a>
                                <div>
                                    <a href="{{ route('experiments.edit', $experiment) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteExperiment({{ $experiment->id }})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-5">
            <i class="fas fa-flask fa-4x text-muted mb-3"></i>
            <h3>Nėra eksperimentų</h3>
            <p class="text-muted">Sukurkite naują eksperimentą, kad pradėtumėte analizę.</p>
            <a href="{{ route('experiments.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Sukurti eksperimentą
            </a>
        </div>
    @endif
</div>

<script>
function deleteExperiment(id) {
    if (confirm('Ar tikrai norite ištrinti šį eksperimentą? Visi susiję duomenys bus prarasti.')) {
        fetch(`/experiments/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            location.reload();
        })
        .catch(error => {
            alert('Klaida trinant eksperimentą');
        });
    }
}
</script>
@endsection