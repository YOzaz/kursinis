@extends('layout')

@section('title', 'Pagrindinis - Propagandos analizės sistema')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-upload me-2"></i>
                    Tekstų analizės paleidimas
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading mb-2">Sistemos galimybės</h6>
                            <p class="mb-1">Propaganda analizės sistema veikia dviem režimais:</p>
                            <ul class="mb-2">
                                <li><strong>Su ekspertų anotacijomis</strong> - palygina AI ir ekspertų rezultatus, skaičiuoja metrikas</li>
                                <li><strong>Be ekspertų anotacijų</strong> - analizuoja naują tekstą ir identifikuoja propagandos technikas</li>
                            </ul>
                            <p class="mb-0">Rezultatai eksportuojami CSV ir JSON formatais. Jei norite testuoti custom prompt'us, eikite į <a href="{{ route('experiments.index') }}" class="alert-link">Eksperimentų</a> skiltį.</p>
                        </div>
                    </div>
                </div>
                
                <p class="text-muted mb-4">
                    Įkelkite JSON failą (su arba be ekspertų anotacijų) ir pasirinkite LLM modelius analizei.
                </p>

                <form action="{{ route('upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                    @csrf
                    
                    <!-- Failų įkėlimo zona -->
                    <div class="mb-4">
                        <label for="json_file" class="form-label fw-bold">
                            <i class="fas fa-file-code me-2"></i>
                            JSON failas (su arba be ekspertų anotacijų)
                        </label>
                        <div class="upload-area" id="uploadArea">
                            <input type="file" class="form-control d-none" id="json_file" name="json_file" accept=".json" required>
                            <div id="uploadContent">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                <h5>Nuvilkite failą čia arba <span class="text-primary">spustelėkite pasirinkti</span></h5>
                                <p class="text-muted">Palaikomi formatai: .json (iki 10MB)</p>
                            </div>
                            <div id="fileInfo" class="d-none">
                                <i class="fas fa-file-check fa-2x text-success mb-2"></i>
                                <p class="mb-0" id="fileName"></p>
                                <small class="text-muted" id="fileSize"></small>
                            </div>
                        </div>
                    </div>

                    <!-- Modelių pasirinkimas -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-robot me-2"></i>
                            LLM modeliai analizei
                        </label>
                        <p class="text-muted small">Pasirinkite bent vieną modelį</p>
                        
                        <div id="model-selection">
                            <!-- Models will be loaded dynamically -->
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            loadModels();
                        });
                        
                        function loadModels() {
                            const models = @json(config('llm.models'));
                            const providers = @json(config('llm.providers'));
                            const container = document.getElementById('model-selection');
                            
                            let html = '';
                            
                            // Group models by provider
                            const groupedModels = {};
                            Object.keys(models).forEach(key => {
                                const model = models[key];
                                const provider = model.provider;
                                if (!groupedModels[provider]) {
                                    groupedModels[provider] = [];
                                }
                                groupedModels[provider].push({key, ...model});
                            });
                            
                            // Create sections for each provider
                            Object.keys(groupedModels).forEach(provider => {
                                const providerConfig = providers[provider];
                                const providerModels = groupedModels[provider];
                                
                                html += `
                                <div class="provider-section mb-4">
                                    <h6 class="mb-3">
                                        <i class="${providerConfig.icon} text-${providerConfig.color} me-2"></i>
                                        ${providerConfig.name}
                                    </h6>
                                    <div class="row">
                                `;
                                
                                providerModels.forEach(model => {
                                    const isDefault = model.is_default ? 'checked' : '';
                                    const tier = model.tier === 'premium' ? 
                                        '<span class="badge bg-warning text-dark ms-1">Premium</span>' : '';
                                    
                                    html += `
                                    <div class="col-md-6 mb-2">
                                        <div class="model-checkbox">
                                            <input type="checkbox" class="form-check-input" 
                                                   id="${model.key}" name="models[]" value="${model.key}" ${isDefault}>
                                            <label class="form-check-label w-100" for="${model.key}">
                                                <div class="d-flex align-items-center">
                                                    <i class="${providerConfig.icon} text-${providerConfig.color} me-2"></i>
                                                    <div class="flex-grow-1">
                                                        <strong>${model.model}</strong>
                                                        ${tier}
                                                        <small class="d-block text-muted">${model.description || ''}</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    `;
                                });
                                
                                html += `
                                    </div>
                                </div>
                                `;
                            });
                            
                            container.innerHTML = html;
                        }
                        </script>
                    </div>

                    <!-- Analizės paleidimo mygtukas -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="analyzeBtn">
                            <i class="fas fa-play me-2"></i>
                            Pradėti analizę
                        </button>
                    </div>
                </form>

                <!-- Informacija apie sistemos galimybes -->
                <hr class="my-4">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-tags fa-2x text-primary mb-2"></i>
                            <h6>21 propagandos technika</h6>
                            <small class="text-muted">Automatinis atpažinimas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                            <h6>Tikslumas metrikos</h6>
                            <small class="text-muted">Precision, Recall, F1</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-download fa-2x text-info mb-2"></i>
                            <h6>CSV eksportas</h6>
                            <small class="text-muted">Detalūs rezultatai</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h6>Realaus laiko progresas</h6>
                            <small class="text-muted">Stebėjimas</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('json_file');
    const uploadContent = document.getElementById('uploadContent');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const analyzeBtn = document.getElementById('analyzeBtn');
    const form = document.getElementById('uploadForm');

    // Failų drag & drop funkcionalumas
    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            displayFileInfo(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            displayFileInfo(e.target.files[0]);
        }
    });

    function displayFileInfo(file) {
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        
        uploadContent.classList.add('d-none');
        fileInfo.classList.remove('d-none');
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Formos validacija
    form.addEventListener('submit', function(e) {
        const selectedModels = document.querySelectorAll('input[name="models[]"]:checked');
        
        if (selectedModels.length === 0) {
            e.preventDefault();
            alert('Prašome pasirinkti bent vieną LLM modelį analizei.');
            return;
        }

        // Pakeisti mygtuką į loading būseną
        analyzeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Paleidžiama analizė...';
        analyzeBtn.disabled = true;
    });
});
</script>
@endsection