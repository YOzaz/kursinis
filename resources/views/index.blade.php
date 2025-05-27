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
                            <p class="mb-0">Rezultatai eksportuojami CSV ir JSON formatais. Custom prompt'ą galite nurodyti žemiau formos lauke.</p>
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

                    <!-- Custom prompt'o konfigūracija -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-edit me-2"></i>
                            Prompt konfigūracija
                            <i class="fas fa-question-circle text-muted ms-2" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="top" 
                               title="Galite naudoti standartinį ATSPARA prompt'ą arba sukurti savo. Standartinis prompt'as optimizuotas lietuvių propagandos analizei."></i>
                        </label>

                        <!-- Prompt type selector -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prompt_type" id="standard_prompt" value="standard" checked>
                                <label class="form-check-label" for="standard_prompt">
                                    <strong>Standartinis ATSPARA prompt'as</strong>
                                    <small class="d-block text-muted">Optimizuotas lietuvių propagandos analizei su 21 technika</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="prompt_type" id="custom_prompt_radio" value="custom">
                                <label class="form-check-label" for="custom_prompt_radio">
                                    <strong>Pritaikytas prompt'as</strong>
                                    <small class="d-block text-muted">Modifikuokite standartinį arba sukurkite visiškai naują</small>
                                </label>
                            </div>
                        </div>

                        <!-- Standard prompt preview -->
                        <div id="standard_prompt_section">
                            <div class="card bg-light">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Standartinio prompt'o struktūra</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="showFullPrompt()">
                                        <i class="fas fa-eye me-1"></i>Peržiūrėti pilną prompt'ą
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>Role:</strong>
                                            <small class="d-block text-muted">ATSPARA propagandos analizės ekspertas</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Instructions:</strong>
                                            <small class="d-block text-muted">Objektyviai identifikuok propagandos technikas</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Techniques:</strong>
                                            <small class="d-block text-muted">21 ATSPARA propagandos technika</small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Output:</strong>
                                            <small class="d-block text-muted">Griežtas JSON formatas</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom prompt editor -->
                        <div id="custom_prompt_section" style="display: none;">
                            <div class="mb-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="loadDefaultPrompt()">
                                    <i class="fas fa-copy me-1"></i>Kopijuoti standartinį prompt'ą
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="showPromptBuilder()">
                                    <i class="fas fa-magic me-1"></i>RISEN prompt'o kūrėjas
                                </button>
                            </div>
                            <textarea class="form-control" id="custom_prompt" name="custom_prompt" rows="10" 
                                      placeholder="Įveskite savo custom prompt'ą propaganda analizei..."></textarea>
                            <div class="form-text">
                                <small><strong>Patarimas:</strong> Naudokite RISEN metodologiją - Role, Instructions, Situation, Execution, Needle</small>
                            </div>
                        </div>
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
                document.getElementById('custom_prompt').value = '';
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

function loadDefaultPrompt() {
    fetch('/api/default-prompt')
        .then(response => response.json())
        .then(data => {
            document.getElementById('custom_prompt').value = data.prompt;
        })
        .catch(error => {
            console.error('Error loading default prompt:', error);
            // Fallback prompt
            const fallbackPrompt = `**Role**: Tu esi propagandos analizės ekspertas, specializuojantis ATSPARA metodologijos taikyme.

**Instructions**: Analizuok pateiktą tekstą ir identifikuok propagandos technikas objektyviai ir tiksliai.

**Techniques**: Naudok ATSPARA apibrėžtas 21 propagandos techniką.

**Output**: Grąžink tik JSON formatą pagal specifikaciją.`;
            document.getElementById('custom_prompt').value = fallbackPrompt;
        });
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
</script>
@endsection