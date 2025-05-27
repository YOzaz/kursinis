@extends('layout')

@section('title', 'Redaguoti eksperimentą')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Redaguoti eksperimentą</h1>
        <a href="{{ route('experiments.show', $experiment) }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Atgal
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Eksperimento informacija</h5>
                </div>
                <div class="card-body">
                    <form id="experimentForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Pavadinimas *</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ $experiment->name }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Aprašymas</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Eksperimento tikslas ir aprašymas...">{{ $experiment->description }}</textarea>
                        </div>

                        <h6 class="mt-4 mb-3">RISEN Prompt konfigūracija</h6>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role (Vaidmuo) *</label>
                            <textarea class="form-control prompt-field" id="role" name="risen_config[role]" rows="2" required>{{ $experiment->risen_config['role'] ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions (Instrukcijos) *</label>
                            <textarea class="form-control prompt-field" id="instructions" name="risen_config[instructions]" rows="6" required>{{ $experiment->risen_config['instructions'] ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="situation" class="form-label">Situation (Situacija) *</label>
                            <textarea class="form-control prompt-field" id="situation" name="risen_config[situation]" rows="4" required>{{ $experiment->risen_config['situation'] ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="execution" class="form-label">Execution (Vykdymas) *</label>
                            <textarea class="form-control prompt-field" id="execution" name="risen_config[execution]" rows="4" required>{{ $experiment->risen_config['execution'] ?? '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label for="needle" class="form-label">Needle (Esmė) *</label>
                            <textarea class="form-control prompt-field" id="needle" name="risen_config[needle]" rows="4" required>{{ $experiment->risen_config['needle'] ?? '' }}</textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-info" onclick="previewPrompt()">
                                <i class="fas fa-eye"></i> Peržiūrėti prompt
                            </button>
                            <div>
                                <button type="button" class="btn btn-outline-danger" onclick="deleteExperiment()">
                                    <i class="fas fa-trash"></i> Ištrinti
                                </button>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Išsaugoti pakeitimus
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6>Prompt peržiūra</h6>
                </div>
                <div class="card-body">
                    <div id="promptPreview" class="bg-light p-3 rounded" style="font-family: monospace; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">
                        {{ $experiment->custom_prompt }}
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h6>RISEN metodologija</h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <div class="mb-2">
                            <strong>R</strong>ole - AI vaidmuo ir kompetencijos
                        </div>
                        <div class="mb-2">
                            <strong>I</strong>nstructions - Detali užduočių specifikacija
                        </div>
                        <div class="mb-2">
                            <strong>S</strong>ituation - Konteksto ir aplinkybių aprašymas
                        </div>
                        <div class="mb-2">
                            <strong>E</strong>xecution - Vykdymo proceso nurodymai
                        </div>
                        <div>
                            <strong>N</strong>eedle - Pagrindinė užduotis ir rezultato formatas
                        </div>
                    </div>
                </div>
            </div>

            @if($experiment->results->count() > 0)
                <div class="card mt-3">
                    <div class="card-header">
                        <h6>Eksperimento statistika</h6>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="text-muted">Rezultatų</div>
                                    <div class="h5">{{ $experiment->results->count() }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted">Darbų</div>
                                    <div class="h5">{{ $experiment->analysisJobs->count() }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
function previewPrompt() {
    const formData = new FormData(document.getElementById('experimentForm'));
    const risenConfig = {};
    
    // Collect RISEN config
    formData.forEach((value, key) => {
        if (key.startsWith('risen_config[')) {
            const field = key.match(/risen_config\[(\w+)\]/)[1];
            risenConfig[field] = value;
        }
    });

    fetch('/experiments/preview-prompt', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ risen_config: risenConfig })
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('promptPreview').textContent = data.prompt;
    })
    .catch(error => {
        document.getElementById('promptPreview').innerHTML = '<em class="text-danger">Klaida generuojant prompt</em>';
    });
}

// Auto-preview when fields change
document.querySelectorAll('.prompt-field').forEach(field => {
    field.addEventListener('input', debounce(previewPrompt, 1000));
});

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Handle form submission
document.getElementById('experimentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    
    // Collect form data
    formData.forEach((value, key) => {
        if (key.startsWith('risen_config[')) {
            if (!data.risen_config) data.risen_config = {};
            const field = key.match(/risen_config\[(\w+)\]/)[1];
            data.risen_config[field] = value;
        } else if (key !== '_method' && key !== '_token') {
            data[key] = value;
        }
    });

    fetch('/experiments/{{ $experiment->id }}', {
        method: 'PUT',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        window.location.href = '/experiments/' + data.id;
    })
    .catch(error => {
        alert('Klaida išsaugant eksperimentą');
    });
});

function deleteExperiment() {
    if (confirm('Ar tikrai norite ištrinti šį eksperimentą? Visi susiję duomenys bus prarasti.')) {
        fetch('/experiments/{{ $experiment->id }}', {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            window.location.href = '/experiments';
        })
        .catch(error => {
            alert('Klaida trinant eksperimentą');
        });
    }
}
</script>
@endsection