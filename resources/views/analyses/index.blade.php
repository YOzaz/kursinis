@extends('layout')

@section('title', __('messages.analysis_list'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>{{ __('messages.analysis_list') }}</h1>
            <p class="text-muted mb-0">{{ __('messages.all_completed_analyses') }}</p>
        </div>
        <a href="{{ route('create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> {{ __('messages.new_analysis') }}
        </a>
    </div>

    <div class="alert alert-info mb-4">
        <div class="d-flex">
            <div class="me-3">
                <i class="fas fa-info-circle fa-lg"></i>
            </div>
            <div>
                <h6 class="alert-heading mb-2">{{ __('messages.analysis_types') }}</h6>
                <ul class="mb-0">
                    <li><strong>{{ __('messages.with_expert_annotations') }}</strong> - {{ __('messages.system_compares_ai_expert') }}</li>
                    <li><strong>{{ __('messages.without_expert_annotations') }}</strong> - {{ __('messages.only_ai_analyzes') }}</li>
<li><strong>{{ __('messages.with_risen_prompt') }}</strong> - {{ __('messages.uses_modified_prompt') }}</li>
                    <li><strong>{{ __('messages.repeated') }}</strong> - {{ __('messages.repeats_existing_analysis') }}</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="search-input" class="form-label">{{ __('messages.search') }}</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="search-input" placeholder="{{ __('messages.search_by_name_or_id') }}">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="status-filter" class="form-label">{{ __('messages.status') }}</label>
                    <select class="form-select" id="status-filter">
                        <option value="">{{ __('messages.all_statuses') }}</option>
                        <option value="completed">{{ __('messages.completed') }}</option>
                        <option value="processing">{{ __('messages.processing') }}</option>
                        <option value="failed">{{ __('messages.failed') }}</option>
                        <option value="pending">{{ __('messages.pending') }}</option>
                        <option value="cancelled">{{ __('messages.cancelled') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type-filter" class="form-label">{{ __('messages.type') }}</label>
                    <select class="form-select" id="type-filter">
                        <option value="">{{ __('messages.all_types') }}</option>
                        <option value="standard">{{ __('messages.standard') }}</option>
                        <option value="custom">Custom prompt</option>
                        <option value="repeat">{{ __('messages.repeated') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" id="clear-filters">
                        <i class="fas fa-times me-1"></i>{{ __('messages.clear') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if($analyses->count() > 0)
        <div class="row" id="analyses-container">
            @foreach($analyses as $analysis)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">
                                @if($analysis->reference_analysis_id)
                                    <i class="fas fa-redo text-info me-1"></i>{{ __('messages.repeated_analysis') }}
                                @elseif($analysis->usesCustomPrompt())
                                    <i class="fas fa-edit text-warning me-1"></i>Custom prompt
                                @else
                                    <i class="fas fa-chart-line text-primary me-1"></i>{{ __('messages.standard_analysis') }}
                                @endif
                            </h6>
                            <span class="badge badge-{{ $analysis->status === 'completed' ? 'success' : ($analysis->status === 'processing' ? 'warning' : ($analysis->status === 'failed' ? 'danger' : ($analysis->status === 'cancelled' ? 'dark' : 'secondary'))) }}">
                                {{ $analysis->status === 'cancelled' ? __('messages.cancelled') : __('messages.'.$analysis->status) }}
                            </span>
                        </div>
                        <div class="card-body">
                            @if($analysis->name)
                                <h6 class="mb-2">{{ $analysis->name }}</h6>
                            @endif
                            @if($analysis->description)
                                <p class="small text-muted mb-2">{{ Str::limit($analysis->description, 100) }}</p>
                            @endif
                            @if($analysis->reference_analysis_id)
                                <div class="small text-info mb-2">
                                    <i class="fas fa-link"></i> {{ __('messages.link') }}: {{ $analysis->reference_analysis_id }}
                                </div>
                            @endif
                            <div class="small text-muted mb-3">
                                <div><strong>ID:</strong> {{ $analysis->job_id }}</div>
                                <div><strong>{{ __('messages.created_at') }}:</strong> {{ $analysis->created_at->format('Y-m-d H:i') }}</div>
                            </div>

                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="small text-muted">{{ __('messages.texts') }}</div>
                                    <div class="h5">{{ $analysis->textAnalyses->count() }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">{{ __('messages.models') }}</div>
    @php
                                        // Count actual models that produced results using comparison metrics
                                        $modelCount = $analysis->comparisonMetrics()->distinct('model_name')->count('model_name');
                                        
                                        // If no metrics yet, use requested models count as fallback
                                        if ($modelCount === 0 && $analysis->requested_models) {
                                            if (is_array($analysis->requested_models)) {
                                                $modelCount = count($analysis->requested_models);
                                            } elseif (is_string($analysis->requested_models)) {
                                                $modelCount = count(explode(',', $analysis->requested_models));
                                            } else {
                                                $modelCount = 1; // fallback
                                            }
                                        }
                                        
                                        // Ensure at least 1 model if we have analysis records
                                        if ($modelCount === 0 && $analysis->textAnalyses->count() > 0) {
                                            $modelCount = 1;
                                        }
                                    @endphp
                                    <div class="h5">{{ $modelCount }}</div>
                                </div>
                            </div>

                            @if($analysis->status === 'completed' && $analysis->textAnalyses->count() > 0)
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: 100%"></div>
                                </div>
                                <small class="text-success">
                                    <i class="fas fa-check-circle"></i> {{ __('messages.analysis_completed_successfully') }}
                                </small>
                            @elseif($analysis->status === 'processing')
                                @php
                                    $progressPercent = $analysis->getProgressPercentage();
                                @endphp
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-warning progress-bar-striped progress-bar-animated" style="width: {{ $progressPercent }}%"></div>
                                </div>
                                <small class="text-warning">
                                    <i class="fas fa-cog fa-spin"></i> {{ __('messages.analysis_in_progress') }} ({{ round($progressPercent, 1) }}%)
                                </small>
                            @elseif($analysis->status === 'failed')
                                @php
                                    $failedProgressPercent = $analysis->getProgressPercentage();
                                @endphp
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-danger" style="width: {{ max($failedProgressPercent, 10) }}%"></div>
                                </div>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle"></i> {{ __('messages.analysis_failed') }} ({{ round($failedProgressPercent, 1) }}%)
                                </small>
                            @elseif($analysis->status === 'cancelled')
                                <div class="progress mb-2" style="height: 8px;">
                                    <div class="progress-bar bg-dark" style="width: 100%"></div>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-ban"></i> {{ __('messages.analysis_cancelled') }}
                                </small>
                            @endif
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    @if($analysis->status === 'completed')
                                        <a href="{{ route('analyses.show', $analysis->job_id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i> {{ __('messages.view') }}
                                        </a>
                                    @elseif($analysis->status === 'cancelled')
                                        <span class="text-muted small">
                                            <i class="fas fa-ban"></i> {{ __('messages.analysis_cancelled') }}
                                        </span>
                                    @else
                                        <a href="{{ route('progress', $analysis->job_id) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-clock"></i> {{ __('messages.status') }}
                                        </a>
                                    @endif
                                    
                                    @if($analysis->experiment_id)
                                        <a href="{{ route('experiments.show', $analysis->experiment_id) }}" class="btn btn-sm btn-outline-warning ms-1">
                                            <i class="fas fa-flask"></i> {{ __('messages.experiment') }}
                                        </a>
                                    @endif
                                </div>
                                
                                @if($analysis->status === 'cancelled')
                                    <form method="POST" action="{{ route('analysis.delete') }}" class="d-inline" onsubmit="return confirm('{{ __('messages.confirm_delete_analysis') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="job_id" value="{{ $analysis->job_id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('messages.delete_analysis_confirm') }}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {{ $analyses->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
            <h3>{{ __('messages.no_analyses') }}</h3>
            <p class="text-muted">
                {{ __('messages.no_analyses_performed_yet') }}<br>
                {{ __('messages.start_new_analysis_by_uploading') }}
            </p>
            <a href="{{ route('create') }}" class="btn btn-primary">
                <i class="fas fa-upload"></i> {{ __('messages.start_analysis_button') }}
            </a>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');
    const typeFilter = document.getElementById('type-filter');
    const clearFiltersBtn = document.getElementById('clear-filters');
    const analysesContainer = document.getElementById('analyses-container');
    
    // Get all analysis cards
    const analysisCards = Array.from(analysesContainer.children);
    
    function applyFilters() {
        const searchTerm = searchInput.value.toLowerCase();
        const statusValue = statusFilter.value;
        const typeValue = typeFilter.value;
        
        analysisCards.forEach(card => {
            let visible = true;
            
            // Search filter
            if (searchTerm) {
                const cardText = card.textContent.toLowerCase();
                visible = visible && cardText.includes(searchTerm);
            }
            
            // Status filter
            if (statusValue && visible) {
                const statusBadge = card.querySelector('.badge');
                if (statusBadge) {
                    const statusClasses = statusBadge.className;
                    switch(statusValue) {
                        case 'completed':
                            visible = visible && statusClasses.includes('badge-success');
                            break;
                        case 'processing':
                            visible = visible && statusClasses.includes('badge-warning');
                            break;
                        case 'failed':
                            visible = visible && statusClasses.includes('badge-danger');
                            break;
                        case 'pending':
                            visible = visible && statusClasses.includes('badge-secondary');
                            break;
                        case 'cancelled':
                            visible = visible && statusClasses.includes('badge-dark');
                            break;
                    }
                }
            }
            
            // Type filter
            if (typeValue && visible) {
                const cardHeader = card.querySelector('.card-title');
                if (cardHeader) {
                    const headerText = cardHeader.textContent.toLowerCase();
                    switch(typeValue) {
                        case 'standard':
                            visible = visible && headerText.includes('standartinė');
                            break;
                        case 'custom':
                            visible = visible && headerText.includes('custom');
                            break;
                        case 'repeat':
                            visible = visible && headerText.includes('pakartotinė');
                            break;
                    }
                }
            }
            
            // Show/hide card
            card.style.display = visible ? 'block' : 'none';
        });
        
        // Check if any cards are visible
        const visibleCards = analysisCards.filter(card => card.style.display !== 'none');
        if (visibleCards.length === 0) {
            showNoResultsMessage();
        } else {
            hideNoResultsMessage();
        }
    }
    
    function showNoResultsMessage() {
        let noResultsMsg = document.getElementById('no-results-message');
        if (!noResultsMsg) {
            noResultsMsg = document.createElement('div');
            noResultsMsg.id = 'no-results-message';
            noResultsMsg.className = 'col-12 text-center py-5';
            noResultsMsg.innerHTML = `
                <div class="text-muted">
                    <i class="fas fa-search fa-3x mb-3"></i>
                    <h5>{{ __('messages.no_results_found') }}</h5>
                    <p>{{ __('messages.try_changing_filters') }}</p>
                </div>
            `;
            analysesContainer.appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = 'block';
    }
    
    function hideNoResultsMessage() {
        const noResultsMsg = document.getElementById('no-results-message');
        if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }
    
    function clearFilters() {
        searchInput.value = '';
        statusFilter.value = '';
        typeFilter.value = '';
        applyFilters();
    }
    
    // Event listeners
    searchInput.addEventListener('input', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);
    clearFiltersBtn.addEventListener('click', clearFilters);
    
    // Initialize filters
    applyFilters();
});
</script>

@endsection