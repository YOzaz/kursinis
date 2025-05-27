<?php $__env->startSection('title', 'Pagrindinis - Propagandos analizės sistema'); ?>

<?php $__env->startSection('content'); ?>
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
                <p class="text-muted mb-4">
                    Įkelkite JSON failą su ekspertų anotacijomis ir pasirinkite LLM modelius analizei.
                    Sistema palyginsir ekspertų ir dirbtinio intelekto anotacijas bei apskaičiuos tikslumo metrikas.
                </p>

                <form action="<?php echo e(route('upload')); ?>" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <?php echo csrf_field(); ?>
                    
                    <!-- Failų įkėlimo zona -->
                    <div class="mb-4">
                        <label for="json_file" class="form-label fw-bold">
                            <i class="fas fa-file-code me-2"></i>
                            JSON failas su ekspertų anotacijomis
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
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="model-checkbox">
                                    <input type="checkbox" class="form-check-input" id="claude" name="models[]" value="claude-4">
                                    <label class="form-check-label w-100" for="claude">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-brain text-primary me-2"></i>
                                            <div>
                                                <strong>Claude 4</strong>
                                                <small class="d-block text-muted">Anthropic AI</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="model-checkbox">
                                    <input type="checkbox" class="form-check-input" id="gemini" name="models[]" value="gemini-2.5-pro">
                                    <label class="form-check-label w-100" for="gemini">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-star text-warning me-2"></i>
                                            <div>
                                                <strong>Gemini 2.5 Pro</strong>
                                                <small class="d-block text-muted">Google AI</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="model-checkbox">
                                    <input type="checkbox" class="form-check-input" id="gpt" name="models[]" value="gpt-4.1">
                                    <label class="form-check-label w-100" for="gpt">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-cog text-success me-2"></i>
                                            <div>
                                                <strong>GPT-4.1</strong>
                                                <small class="d-block text-muted">OpenAI</small>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
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
                            <h6>7 propagandos technikos</h6>
                            <small class="text-muted">Automatinis atpažinimas</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                            <h6>Tikslumas metrikOs</h6>
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
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
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
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yozaz/www/vu/kursinis/resources/views/index.blade.php ENDPATH**/ ?>