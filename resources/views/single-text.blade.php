@extends('layout')

@section('title', __('messages.single_text_analysis') . ' - ' . __('messages.title'))

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h3 class="card-title mb-0">
                    <i class="fas fa-edit me-2"></i>
                    {{ __('messages.single_text_analysis') }}
                </h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-lightbulb fa-lg"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading mb-2">{{ __('messages.single_text_description') }}</h6>
                            <p class="mb-1"><strong>{{ __('messages.text_input') }}:</strong></p>
                            <ul class="mb-2 small">
                                <li>{{ __('messages.paste_or_type_text') }}</li>
                                <li>Text length: 10 - 50,000 characters</li>
                                <li>No expert annotations required</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <form action="{{ route('single-text.upload') }}" method="POST" id="singleTextForm">
                    @csrf
                    
                    <!-- Text Input -->
                    <div class="mb-4">
                        <label for="text_content" class="form-label">
                            <i class="fas fa-file-text me-1"></i>{{ __('messages.text_input') }} *
                        </label>
                        <textarea 
                            name="text_content" 
                            id="text_content" 
                            class="form-control @error('text_content') is-invalid @enderror" 
                            rows="10" 
                            placeholder="{{ __('messages.paste_or_type_text') }}"
                            required
                        >{{ old('text_content') }}</textarea>
                        <div class="form-text">
                            <span id="char-count">0</span> / 50,000 characters
                        </div>
                        @error('text_content')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Model Selection -->
                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-robot me-1"></i>{{ __('messages.select_models') }} *
                        </label>
                        <div class="row">
                            @php
                                $models = config('llm.models', []);
                                $oldModels = old('models', []);
                            @endphp
                            @foreach($models as $key => $modelConfig)
                                <div class="col-md-6 mb-2">
                                    <div class="model-checkbox">
                                        <div class="form-check">
                                            <input 
                                                class="form-check-input" 
                                                type="checkbox" 
                                                name="models[]" 
                                                value="{{ $key }}" 
                                                id="model_{{ $key }}"
                                                {{ in_array($key, $oldModels) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label w-100" for="model_{{ $key }}">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <strong>{{ $modelConfig['name'] ?? $key }}</strong>
                                                        @if(isset($modelConfig['description']))
                                                            <br><small class="text-muted">{{ $modelConfig['description'] }}</small>
                                                        @endif
                                                    </div>
                                                    @if(str_contains($key, 'claude'))
                                                        <i class="fas fa-brain text-primary"></i>
                                                    @elseif(str_contains($key, 'gemini'))
                                                        <i class="fas fa-star text-warning"></i>
                                                    @elseif(str_contains($key, 'gpt'))
                                                        <i class="fas fa-cog text-success"></i>
                                                    @endif
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('models')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <!-- Optional Fields -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label">
                                <i class="fas fa-tag me-1"></i>{{ __('messages.analysis_name') }}
                            </label>
                            <input 
                                type="text" 
                                name="name" 
                                id="name" 
                                class="form-control @error('name') is-invalid @enderror" 
                                value="{{ old('name') }}"
                                placeholder="{{ __('messages.single_text_analysis') }}"
                            >
                            @error('name')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left me-1"></i>{{ __('messages.analysis_description') }}
                            </label>
                            <input 
                                type="text" 
                                name="description" 
                                id="description" 
                                class="form-control @error('description') is-invalid @enderror" 
                                value="{{ old('description') }}"
                                placeholder="Optional description..."
                            >
                            @error('description')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>{{ __('messages.back') }}
                        </a>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="fas fa-play me-1"></i>{{ __('messages.analyze_text') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Jobs -->
        @if($recentJobs->count() > 0)
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>{{ __('messages.recent_analyses') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($recentJobs as $job)
                            <div class="col-md-6 mb-3">
                                <div class="card border-left-primary">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">{{ $job->name }}</h6>
                                                <small class="text-muted">
                                                    {{ $job->created_at->diffForHumans() }} • 
                                                    <span class="badge badge-{{ $job->status === 'completed' ? 'success' : ($job->status === 'failed' ? 'danger' : 'warning') }}">
                                                        {{ __('messages.' . $job->status) }}
                                                    </span>
                                                </small>
                                            </div>
                                            <a href="{{ route('analyses.show', $job->job_id) }}" class="btn btn-sm btn-outline-primary">
                                                {{ __('messages.view_details') }}
                                            </a>
                                        </div>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const textArea = document.getElementById('text_content');
    const charCount = document.getElementById('char-count');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('singleTextForm');
    
    // Character counter
    function updateCharCount() {
        const count = textArea.value.length;
        charCount.textContent = count.toLocaleString();
        
        if (count > 50000) {
            charCount.parentElement.classList.add('text-danger');
            submitBtn.disabled = true;
        } else {
            charCount.parentElement.classList.remove('text-danger');
            submitBtn.disabled = false;
        }
    }
    
    textArea.addEventListener('input', updateCharCount);
    updateCharCount(); // Initial count
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const selectedModels = document.querySelectorAll('input[name="models[]"]:checked');
        const textContent = textArea.value.trim();
        
        if (selectedModels.length === 0) {
            e.preventDefault();
            alert('{{ __("messages.no_models_selected") }}');
            return;
        }
        
        if (textContent.length < 10) {
            e.preventDefault();
            alert('Text must be at least 10 characters long.');
            return;
        }
        
        if (textContent.length > 50000) {
            e.preventDefault();
            alert('Text cannot exceed 50,000 characters.');
            return;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>{{ __("messages.loading") }}';
        submitBtn.disabled = true;
    });
});
</script>
@endsection