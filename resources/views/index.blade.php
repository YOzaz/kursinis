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
                            <i class="fas fa-lightbulb fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading mb-2">Kaip naudoti sistemą</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>1. Duomenų tipai:</strong></p>
                                    <ul class="mb-2 small">
                                        <li><strong>Su ekspertų anotacijomis</strong> - skaičiuoja tikslumas metrikas</li>
                                        <li><strong>Be anotacijų</strong> - tik AI analizė</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>2. Rezultatai:</strong></p>
                                    <ul class="mb-2 small">
                                        <li>11 ATSPARA propagandos technikų</li>
                                        <li>CSV/JSON eksportas</li>
                                        <li>Realaus laiko progresas</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-center">
                                <a href="{{ route('help.index') }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-question-circle me-1"></i>Išsami dokumentacija
                                </a>
                                <a href="{{ route('help.faq') }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-list me-1"></i>FAQ
                                </a>
                            </div>
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
                            <i class="fas fa-question-circle text-muted ms-2" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Įkelkite JSON failą su tekstais. Jei turite ekspertų anotacijas (iš Label Studio), sistema apskaičiuos palyginimo metrikas. Jei ne - tik AI analizė."></i>
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

                    <!-- AI modelių statusas -->
                    <div class="mb-4">
                        <div class="card border-info">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <i class="fas fa-heartbeat me-2 text-info"></i>
                                        AI modelių ryšio statusas
                                    </h6>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-info" id="refreshStatusBtn" onclick="refreshModelStatus()">
                                        <i class="fas fa-sync-alt me-1"></i>Atnaujinti
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="modelStatusContainer">
                                    <div class="text-center py-2">
                                        <i class="fas fa-spinner fa-spin me-2"></i>Tikrinamas ryšys su AI modeliais...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modelių pasirinkimas -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-robot me-2"></i>
                            LLM modeliai analizei
                            <i class="fas fa-question-circle text-muted ms-2" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Pasirinkite AI modelius, kurie analizuos tekstą. Rekomenduojama pasirinkti 2-3 modelius palyginimui. Claude ir GPT modeliai paprastai geresni lietuvių kalba."></i>
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
                                    const isDefault = '';
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
                                                    <div class="model-status-indicator" id="status-${model.key}">
                                                        <i class="fas fa-circle text-muted" title="Nežinomas statusas"></i>
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

                    <!-- RISEN Prompt konfigūracija -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-edit me-2"></i>
                            RISEN Prompt Konfigūracija
                            <i class="fas fa-question-circle text-muted ms-2" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="RISEN metodologija: Role, Instructions, Situation, Execution, Needle. Standartinis ATSPARA promptas optimizuotas lietuvių propagandos analizei."></i>
                        </label>

                        <!-- Prompt type selector -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prompt_type" id="standard_prompt" value="standard" checked>
                                <label class="form-check-label" for="standard_prompt">
                                    <strong>Standartinis ATSPARA RISEN promptas</strong>
                                    <small class="d-block text-muted">Profesionaliai sukurtas pagal RISEN metodologiją su 21 propaganda technika</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prompt_type" id="custom_prompt_radio" value="custom">
                                <label class="form-check-label" for="custom_prompt_radio">
                                    <strong>Pritaikytas RISEN promptas</strong>
                                    <small class="d-block text-muted">Modifikuokite bet kurią RISEN dalį pagal poreikius</small>
                                </label>
                            </div>
                        </div>

                        <!-- RISEN prompt konfigūracija -->
                        <div id="standard_prompt_section">
                            <div class="card bg-light">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">RISEN ATSPARA Promptas</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showFullPrompt()">
                                        <i class="fas fa-eye me-1"></i>Peržiūrėti pilną prompt'ą
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="p-3 border rounded">
                                                <strong class="text-primary"><i class="fas fa-user-tie me-2"></i>Role:</strong>
                                                <p class="small mb-0 mt-1">{{ $standardPrompt['role'] }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="p-3 border rounded">
                                                <strong class="text-success"><i class="fas fa-list-check me-2"></i>Instructions:</strong>
                                                <p class="small mb-0 mt-1">{{ Str::limit($standardPrompt['instructions'], 100) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 border rounded">
                                                <strong class="text-info"><i class="fas fa-map-marker-alt me-2"></i>Situation:</strong>
                                                <p class="small mb-0 mt-1">{{ Str::limit($standardPrompt['situation'], 80) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 border rounded">
                                                <strong class="text-warning"><i class="fas fa-cogs me-2"></i>Execution:</strong>
                                                <p class="small mb-0 mt-1">{{ Str::limit($standardPrompt['execution'], 80) }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="p-3 border rounded">
                                                <strong class="text-danger"><i class="fas fa-bullseye me-2"></i>Needle:</strong>
                                                <p class="small mb-0 mt-1">{{ $standardPrompt['needle'] }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Šis promptas apima 11 ATSPARA propagandos technikas ir JSON formato specifikaciją
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom RISEN prompt editor -->
                        <div id="custom_prompt_section" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">RISEN Prompt Redaktorius</h6>
                                    <small class="text-muted">Modifikuokite bet kurią RISEN dalį</small>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="custom_role" class="form-label">
                                                <strong class="text-primary"><i class="fas fa-user-tie me-2"></i>Role</strong>
                                                <small class="text-muted d-block">Kas yra AI modelis šioje užduotyje?</small>
                                            </label>
                                            <textarea class="form-control" id="custom_role" rows="3" 
                                                      placeholder="Pvz: Tu esi ekspertas...">{{ $standardPrompt['role'] }}</textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="custom_instructions" class="form-label">
                                                <strong class="text-success"><i class="fas fa-list-check me-2"></i>Instructions</strong>
                                                <small class="text-muted d-block">Ką tiksliai daryti?</small>
                                            </label>
                                            <textarea class="form-control" id="custom_instructions" rows="3" 
                                                      placeholder="Pvz: Analizuok tekstą...">{{ $standardPrompt['instructions'] }}</textarea>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="custom_situation" class="form-label">
                                                <strong class="text-info"><i class="fas fa-map-marker-alt me-2"></i>Situation</strong>
                                                <small class="text-muted d-block">Kokiame kontekste?</small>
                                            </label>
                                            <textarea class="form-control" id="custom_situation" rows="4" 
                                                      placeholder="Pvz: Tekstas iš žiniasklaidos...">{{ $standardPrompt['situation'] }}</textarea>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="custom_execution" class="form-label">
                                                <strong class="text-warning"><i class="fas fa-cogs me-2"></i>Execution</strong>
                                                <small class="text-muted d-block">Kaip atlikti užduotį?</small>
                                            </label>
                                            <textarea class="form-control" id="custom_execution" rows="4" 
                                                      placeholder="Pvz: 1) Perskaityk, 2) Identifikuok...">{{ $standardPrompt['execution'] }}</textarea>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="custom_needle" class="form-label">
                                                <strong class="text-danger"><i class="fas fa-bullseye me-2"></i>Needle</strong>
                                                <small class="text-muted d-block">Kokio formato atsakymo reikia?</small>
                                            </label>
                                            <textarea class="form-control" id="custom_needle" rows="4" 
                                                      placeholder="Pvz: Gražink JSON formatą...">{{ $standardPrompt['needle'] }}</textarea>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="resetToDefault()">
                                                <i class="fas fa-undo me-1"></i>Atstatyti standartinį
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="previewCustomPrompt()">
                                                <i class="fas fa-eye me-1"></i>Peržiūrėti pilną prompt'ą
                                            </button>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Automatiškai pridedamos ATSPARA technikos ir JSON formatas
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom prompt (for backward compatibility) -->
                    <div class="mb-4" style="display: none;">
                        <label for="custom_prompt" class="form-label">Custom Prompt (deprecated)</label>
                        <textarea class="form-control" id="custom_prompt" name="custom_prompt" rows="3" 
                                  placeholder="Enter custom prompt for analysis..."></textarea>
                    </div>

                    <!-- Analizės informacija -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Analizės pavadinimas (neprivalomas)</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Pvz.: Propagandos analizė 2025-01">
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Aprašymas (neprivalomas)</label>
                            <input type="text" class="form-control" id="description" name="description" placeholder="Trumpas analizės aprašymas">
                        </div>
                    </div>

                    <!-- Analizės paleidimo mygtukas -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg" id="analyzeBtn">
                            <i class="fas fa-play me-2"></i>
                            Pradėti analizę
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Prompt Preview Modal -->
<div class="modal fade" id="promptPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Standartinis ATSPARA prompt'as</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="promptPreviewContent" class="bg-light p-3" style="white-space: pre-wrap; max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
                <button type="button" class="btn btn-primary" onclick="loadDefaultPrompt(); bootstrap.Modal.getInstance(this.closest('.modal')).hide();">
                    <i class="fas fa-copy me-1"></i>Kopijuoti į custom prompt'ą
                </button>
            </div>
        </div>
    </div>
</div>

<!-- RISEN Prompt Builder Modal -->
<div class="modal fade" id="promptBuilderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">RISEN Prompt'o kūrėjas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>RISEN metodologija:</strong> Role, Instructions, Situation, Execution, Needle - struktūrizuotas prompt'o kūrimo metodas.
                </div>
                
                <div class="mb-3">
                    <label for="risen_role" class="form-label"><strong>Role</strong> - Vaidmuo</label>
                    <textarea class="form-control" id="risen_role" rows="2" 
                              placeholder="Pvz.: Tu esi propagandos analizės ekspertas, specializuojantis ATSPARA metodologijoje..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="risen_instructions" class="form-label"><strong>Instructions</strong> - Instrukcijos</label>
                    <textarea class="form-control" id="risen_instructions" rows="3" 
                              placeholder="Pvz.: Analizuok tekstą ir identifikuok propagandos technikas objektyviai..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="risen_situation" class="form-label"><strong>Situation</strong> - Situacija</label>
                    <textarea class="form-control" id="risen_situation" rows="2" 
                              placeholder="Pvz.: Tekstas pateiktas lietuvių kalba, reikia ATSPARA metodologijos..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="risen_execution" class="form-label"><strong>Execution</strong> - Vykdymas</label>
                    <textarea class="form-control" id="risen_execution" rows="3" 
                              placeholder="Pvz.: Žingsnis po žingsnio analizuok tekstą, ieškokių technikų..."></textarea>
                </div>
                
                <div class="mb-3">
                    <label for="risen_needle" class="form-label"><strong>Needle</strong> - Pagrindinis tikslas</label>
                    <textarea class="form-control" id="risen_needle" rows="2" 
                              placeholder="Pvz.: Grąžink JSON formatą su tiksliais propagandos technikų anotacijomis..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Atšaukti</button>
                <button type="button" class="btn btn-primary" onclick="buildRisenPrompt()">
                    <i class="fas fa-magic me-1"></i>Sukurti prompt'ą
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Model Status Details Modal -->
<div class="modal fade" id="modelStatusModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">AI modelių ryšio statusas - detaliai</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modelStatusDetails">
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-spin me-2"></i>Kraunama...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="refreshModelStatusDetails()">
                    <i class="fas fa-sync-alt me-1"></i>Atnaujinti
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Uždaryti</button>
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
    
    // Load model status on page load
    loadModelStatus();
    
    // Set up periodic status checking (every 5 minutes)
    setInterval(loadModelStatus, 5 * 60 * 1000);

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

        // Jei naudojamas custom prompt, sukurti iš RISEN dalių
        const promptType = document.querySelector('input[name="prompt_type"]:checked').value;
        if (promptType === 'custom') {
            const customParts = {
                role: document.getElementById('custom_role').value,
                instructions: document.getElementById('custom_instructions').value,
                situation: document.getElementById('custom_situation').value,
                execution: document.getElementById('custom_execution').value,
                needle: document.getElementById('custom_needle').value
            };
            
            // Sukurti hidden input su custom prompt dalimis
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'custom_prompt_parts';
            hiddenInput.value = JSON.stringify(customParts);
            form.appendChild(hiddenInput);
        }

        // Pakeisti mygtuką į loading būseną
        analyzeBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Paleidžiama analizė...';
        analyzeBtn.disabled = true;
    });

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Prompt interface functions
    const standardRadio = document.getElementById('standard_prompt');
    const customRadio = document.getElementById('custom_prompt_radio');
    const standardSection = document.getElementById('standard_prompt_section');
    const customSection = document.getElementById('custom_prompt_section');

    if (standardRadio && customRadio) {
        standardRadio.addEventListener('change', function() {
            if (this.checked) {
                standardSection.style.display = 'block';
                customSection.style.display = 'none';
            }
        });

        customRadio.addEventListener('change', function() {
            if (this.checked) {
                standardSection.style.display = 'none';
                customSection.style.display = 'block';
            }
        });
    }
});

function showFullPrompt() {
    const modal = new bootstrap.Modal(document.getElementById('promptPreviewModal'));
    loadPromptPreview();
    modal.show();
}

function resetToDefault() {
    document.getElementById('custom_role').value = @json($standardPrompt['role']);
    document.getElementById('custom_instructions').value = @json($standardPrompt['instructions']);
    document.getElementById('custom_situation').value = @json($standardPrompt['situation']);
    document.getElementById('custom_execution').value = @json($standardPrompt['execution']);
    document.getElementById('custom_needle').value = @json($standardPrompt['needle']);
}

function previewCustomPrompt() {
    // Update modal title
    document.querySelector('#promptPreviewModal .modal-title').textContent = 'Custom RISEN prompt\'o peržiūra';
    
    const modal = new bootstrap.Modal(document.getElementById('promptPreviewModal'));
    loadCustomPromptPreview();
    modal.show();
}

function loadCustomPromptPreview() {
    const customParts = {
        role: document.getElementById('custom_role').value,
        instructions: document.getElementById('custom_instructions').value,
        situation: document.getElementById('custom_situation').value,
        execution: document.getElementById('custom_execution').value,
        needle: document.getElementById('custom_needle').value
    };
    
    // Gauti ATSPARA propagandos technikas iš konfigūracijos
    const atsparaTechniques = @json(config('llm.propaganda_techniques'));
    
    // Sukurti RISEN formatą
    let prompt = `**ROLE**: ${customParts.role}\n\n`;
    prompt += `**INSTRUCTIONS**: ${customParts.instructions}\n\n`;
    prompt += `**SITUATION**: ${customParts.situation}\n\n`;
    prompt += `**EXECUTION**: ${customParts.execution}\n\n`;
    prompt += `**PROPAGANDOS TECHNIKOS (ATSPARA metodologija)**:\n`;
    
    // Pridėti visas ATSPARA technikas
    Object.entries(atsparaTechniques).forEach(([key, description]) => {
        prompt += `- ${key}: ${description}\n`;
    });
    
    prompt += `\n**NEEDLE**: ${customParts.needle}\n\n`;
    prompt += `**ATSAKYMO FORMATAS**: Grąžink JSON objektą su šiais laukais:\n`;
    prompt += `{\n`;
    prompt += `  "primaryChoice": {\n`;
    prompt += `    "choices": ["yes" arba "no"]\n`;
    prompt += `  },\n`;
    prompt += `  "annotations": [\n`;
    prompt += `    {\n`;
    prompt += `      "value": {\n`;
    prompt += `        "start": pozicijos_pradžia,\n`;
    prompt += `        "end": pozicijos_pabaiga,\n`;
    prompt += `        "text": "anotacijos_tekstas",\n`;
    prompt += `        "labels": ["techniką1", "techniką2"]\n`;
    prompt += `      }\n`;
    prompt += `    }\n`;
    prompt += `  ]\n`;
    prompt += `}`;
    
    document.getElementById('promptPreviewContent').textContent = prompt;
}

function showPromptBuilder() {
    const modal = new bootstrap.Modal(document.getElementById('promptBuilderModal'));
    modal.show();
}

function loadPromptPreview() {
    fetch('/api/default-prompt')
        .then(response => response.json())
        .then(data => {
            document.getElementById('promptPreviewContent').textContent = data.prompt;
        })
        .catch(error => {
            console.error('Error loading prompt preview:', error);
            document.getElementById('promptPreviewContent').textContent = 'Nepavyko įkelti prompt\'o peržiūros.';
        });
}

function buildRisenPrompt() {
    const role = document.getElementById('risen_role').value;
    const instructions = document.getElementById('risen_instructions').value;
    const situation = document.getElementById('risen_situation').value;
    const execution = document.getElementById('risen_execution').value;
    const needle = document.getElementById('risen_needle').value;

    const risenPrompt = `**Role**: ${role}

**Instructions**: ${instructions}

**Situation**: ${situation}

**Execution**: ${execution}

**Needle**: ${needle}`;

    document.getElementById('custom_prompt').value = risenPrompt;
    bootstrap.Modal.getInstance(document.getElementById('promptBuilderModal')).hide();
}

// Model status functions
function loadModelStatus() {
    fetch('/api/models/status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayModelStatus(data.data);
                updateModelIndicators(data.data.models);
            } else {
                displayModelStatusError('Nepavyko įkelti modelių statuso');
            }
        })
        .catch(error => {
            console.error('Error loading model status:', error);
            displayModelStatusError('Serverio klaida kraunant modelių statusą');
        });
}

function refreshModelStatus() {
    const refreshBtn = document.getElementById('refreshStatusBtn');
    const originalHtml = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Atnaujinama...';
    refreshBtn.disabled = true;
    
    // Show loading in status container
    document.getElementById('modelStatusContainer').innerHTML = `
        <div class="text-center py-2">
            <i class="fas fa-spinner fa-spin me-2"></i>Atnaujinamas modelių statusas...
        </div>
    `;
    
    fetch('/api/models/status/refresh', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayModelStatus(data.data);
            updateModelIndicators(data.data.models);
        } else {
            displayModelStatusError('Nepavyko atnaujinti modelių statuso');
        }
    })
    .catch(error => {
        console.error('Error refreshing model status:', error);
        displayModelStatusError('Serverio klaida atnaujinant modelių statusą');
    })
    .finally(() => {
        refreshBtn.innerHTML = originalHtml;
        refreshBtn.disabled = false;
    });
}

function displayModelStatus(systemHealth) {
    const container = document.getElementById('modelStatusContainer');
    const { status, total_models, online_models, offline_models, models } = systemHealth;
    
    let statusClass = 'success';
    let statusIcon = 'check-circle';
    let statusText = 'Visi modeliai veikia';
    
    if (status === 'critical') {
        statusClass = 'danger';
        statusIcon = 'times-circle';
        statusText = 'Nė vienas modelis nedostupas';
    } else if (status === 'degraded') {
        statusClass = 'warning';
        statusIcon = 'exclamation-triangle';
        statusText = 'Kai kurie modeliai nedostupani';
    }
    
    let html = `
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <i class="fas fa-${statusIcon} text-${statusClass} me-2 fa-lg"></i>
                    <div>
                        <strong class="text-${statusClass}">${statusText}</strong>
                        <div class="small text-muted">
                            ${online_models}/${total_models} modelių prisijungę
                            <button type="button" class="btn btn-link btn-sm p-0 ms-2" onclick="showModelStatusDetails()">
                                <i class="fas fa-info-circle"></i> Detaliau
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <div class="small text-muted">
                    Paskutinį kartą tikrinta: ${new Date().toLocaleTimeString('lt-LT')}
                </div>
            </div>
        </div>
    `;
    
    if (offline_models > 0) {
        html += `
            <div class="mt-3">
                <div class="small">
                    <strong>Problemos su modeliais:</strong>
                </div>
                <div class="mt-1">
        `;
        
        Object.entries(models).forEach(([modelKey, modelStatus]) => {
            if (!modelStatus.online) {
                html += `
                    <span class="badge bg-danger me-1" title="${modelStatus.message}">
                        ${modelStatus.model_name}
                    </span>
                `;
            }
        });
        
        html += `
                </div>
                <div class="small text-muted mt-1">
                    <i class="fas fa-info-circle me-1"></i>
                    Pelės užvedimu pamatysite detalesnę informaciją
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

function updateModelIndicators(models) {
    Object.entries(models).forEach(([modelKey, modelStatus]) => {
        const indicator = document.getElementById(`status-${modelKey}`);
        if (indicator) {
            let iconClass = 'fas fa-circle';
            let textClass = 'text-muted';
            let title = 'Nežinomas statusas';
            
            if (modelStatus.online) {
                iconClass = 'fas fa-circle';
                textClass = 'text-success';
                title = `Online (${modelStatus.response_time}ms)`;
            } else if (modelStatus.status === 'not_configured') {
                iconClass = 'fas fa-exclamation-circle';
                textClass = 'text-warning';
                title = 'Nesukonfigūruotas API raktas';
            } else {
                iconClass = 'fas fa-times-circle';
                textClass = 'text-danger';
                title = `Offline: ${modelStatus.message}`;
            }
            
            indicator.innerHTML = `<i class="${iconClass} ${textClass}" title="${title}"></i>`;
        }
    });
}

function displayModelStatusError(errorMessage) {
    const container = document.getElementById('modelStatusContainer');
    container.innerHTML = `
        <div class="text-center py-2 text-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>${errorMessage}
        </div>
    `;
}

function showModelStatusDetails() {
    const modal = new bootstrap.Modal(document.getElementById('modelStatusModal'));
    modal.show();
    loadModelStatusDetails();
}

function loadModelStatusDetails() {
    const container = document.getElementById('modelStatusDetails');
    container.innerHTML = `
        <div class="text-center py-3">
            <i class="fas fa-spinner fa-spin me-2"></i>Kraunama...
        </div>
    `;
    
    fetch('/api/models/status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayModelStatusDetails(data.data);
            } else {
                container.innerHTML = `
                    <div class="text-center py-3 text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Nepavyko įkelti detalaus statuso
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading detailed model status:', error);
            container.innerHTML = `
                <div class="text-center py-3 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Serverio klaida kraunant duomenis
                </div>
            `;
        });
}

function refreshModelStatusDetails() {
    // Clear cache and reload
    fetch('/api/models/status/refresh', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayModelStatusDetails(data.data);
            // Also update the main status display
            displayModelStatus(data.data);
            updateModelIndicators(data.data.models);
        } else {
            const container = document.getElementById('modelStatusDetails');
            container.innerHTML = `
                <div class="text-center py-3 text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>Nepavyko atnaujinti statuso
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error refreshing detailed model status:', error);
        const container = document.getElementById('modelStatusDetails');
        container.innerHTML = `
            <div class="text-center py-3 text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>Serverio klaida atnaujinant duomenis
            </div>
        `;
    });
}

function displayModelStatusDetails(systemHealth) {
    const container = document.getElementById('modelStatusDetails');
    const { status, total_models, online_models, offline_models, models, last_checked } = systemHealth;
    
    let html = `
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h5 class="text-primary">${total_models}</h5>
                        <small>Konfigūruoti modeliai</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h5 class="text-success">${online_models}</h5>
                        <small>Online modeliai</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h5 class="text-danger">${offline_models}</h5>
                        <small>Offline modeliai</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Modelis</th>
                        <th>Tiekėjas</th>
                        <th>Statusas</th>
                        <th>Atsakymo laikas</th>
                        <th>Konfigūracija</th>
                        <th>Pranešimas</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    Object.entries(models).forEach(([modelKey, modelStatus]) => {
        const statusBadge = getStatusBadge(modelStatus);
        const responseTime = modelStatus.response_time ? `${modelStatus.response_time}ms` : '-';
        const configStatus = modelStatus.configured ? 
            '<i class="fas fa-check text-success" title="Sukonfigūruotas"></i>' : 
            '<i class="fas fa-times text-danger" title="Nesukonfigūruotas"></i>';
        
        html += `
            <tr>
                <td>
                    <div class="d-flex align-items-center">
                        <i class="${modelStatus.provider_icon} text-${modelStatus.provider_color} me-2"></i>
                        <div>
                            <strong>${modelStatus.model_name}</strong>
                            ${modelStatus.tier === 'premium' ? '<span class="badge bg-warning text-dark ms-1">Premium</span>' : ''}
                            <div class="small text-muted">${modelStatus.description}</div>
                        </div>
                    </div>
                </td>
                <td>${modelStatus.provider_name}</td>
                <td>${statusBadge}</td>
                <td>${responseTime}</td>
                <td>${configStatus}</td>
                <td>
                    <span class="small text-muted" title="${modelStatus.message}">
                        ${truncateMessage(modelStatus.message, 30)}
                    </span>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
        
        <div class="mt-3 text-muted small">
            <i class="fas fa-clock me-1"></i>
            Paskutinį kartą tikrinta: ${new Date(last_checked).toLocaleString('lt-LT')}
        </div>
    `;
    
    container.innerHTML = html;
}

function getStatusBadge(modelStatus) {
    if (modelStatus.online) {
        return '<span class="badge bg-success">Online</span>';
    } else if (modelStatus.status === 'not_configured') {
        return '<span class="badge bg-warning">Nesukonfigūruotas</span>';
    } else if (modelStatus.status === 'auth_error') {
        return '<span class="badge bg-danger">Autentifikacijos klaida</span>';
    } else {
        return '<span class="badge bg-danger">Offline</span>';
    }
}

function truncateMessage(message, length) {
    if (!message) return '-';
    return message.length > length ? message.substring(0, length) + '...' : message;
}
</script>
@endsection