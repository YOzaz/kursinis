@extends('layout')

@section('title', __('messages.system_settings'))

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>{{ __('messages.system_settings') }}</h1>
            <p class="text-muted mb-0">{{ __('messages.model_configuration_and_system_parameters') }}</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> {{ __('messages.back') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>{{ __('messages.error') }}:</strong> {{ __('messages.error_check_input') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($user)
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-key me-2"></i>{{ __('messages.my_api_keys') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ __('messages.information') }}:</strong> {{ __('messages.api_keys_info') }}
                    </div>

                    <form method="POST" action="{{ route('settings.updateApiKeys') }}">
                        @csrf
                        
                        <div class="row">
                            @foreach($providers as $providerKey => $provider)
                                @php
                                    $existingKey = $userApiKeys->get($providerKey);
                                    $hasKey = $existingKey && $existingKey->api_key;
                                @endphp
                                
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">
                                                <i class="{{ $provider['icon'] }} text-{{ $provider['color'] }} me-2"></i>
                                                {{ $provider['name'] }}
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="api_key_{{ $providerKey }}" class="form-label">
                                                    {{ __('messages.api_key') }}
                                                    @if($hasKey)
                                                        <span class="badge bg-success ms-2">{{ __('messages.configured') }}</span>
                                                    @endif
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" 
                                                           class="form-control @error('api_keys.'.$providerKey) is-invalid @enderror" 
                                                           id="api_key_{{ $providerKey }}" 
                                                           name="api_keys[{{ $providerKey }}]" 
                                                           placeholder="{{ $hasKey ? $existingKey->masked_api_key : __('messages.enter_api_key') }}"
                                                           value="{{ old('api_keys.'.$providerKey) }}">
                                                    @if($hasKey)
                                                        <button type="button" 
                                                                class="btn btn-outline-danger" 
                                                                onclick="deleteApiKey('{{ $providerKey }}')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                                @error('api_keys.'.$providerKey)
                                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                                @enderror
                                                @if($existingKey && $existingKey->last_used_at)
                                                    <small class="text-muted">
                                                        {{ __('messages.last_used') }}: {{ $existingKey->last_used_at->format('Y-m-d H:i') }}
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>{{ __('messages.save_api_keys') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs me-2"></i>{{ __('messages.model_default_settings') }}
                    </h5>
                    <form method="POST" action="{{ route('settings.resetDefaults') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning" 
                                onclick="return confirm('{{ __('messages.restore_defaults_confirm') }}')">
                            <i class="fas fa-undo me-1"></i>{{ __('messages.restore_defaults') }}
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ __('messages.important') }}:</strong> {{ __('messages.important_settings_info') }}
                    </div>

                    <form method="POST" action="{{ route('settings.updateDefaults') }}">
                        @csrf
                        
                        @foreach($providers as $providerKey => $provider)
                            <div class="mb-5">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">
                                    <i class="{{ $provider['icon'] }} text-{{ $provider['color'] }} me-2"></i>
                                    {{ $provider['name'] }} {{ __('messages.models') }}
                                </h6>
                                
                                @foreach($models as $modelKey => $model)
                                    @if($model['provider'] === $providerKey)
                                        <div class="card mb-3 border-{{ $provider['color'] }}">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">
                                                    {{ $model['model'] }}
                                                    @if($model['tier'] === 'premium')
                                                        <span class="badge bg-warning text-dark ms-2">Premium</span>
                                                    @endif
                                                    @if($model['is_default'])
                                                        <span class="badge bg-primary ms-2">Numatytasis</span>
                                                    @endif
                                                </h6>
                                                <small class="text-muted">{{ $model['description'] }}</small>
                                            </div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="temp_{{ $modelKey }}" class="form-label">
                                                                {{ __('messages.temperature') }}
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="{{ __('messages.temperature_tooltip') }}"></i>
                                                            </label>
                                                            <input type="number" 
                                                                   class="form-control @error('models.'.$modelKey.'.temperature') is-invalid @enderror" 
                                                                   id="temp_{{ $modelKey }}" 
                                                                   name="models[{{ $modelKey }}][temperature]" 
                                                                   value="{{ old('models.'.$modelKey.'.temperature', $model['temperature']) }}" 
                                                                   min="0" max="2" step="0.01">
                                                            @error('models.'.$modelKey.'.temperature')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="tokens_{{ $modelKey }}" class="form-label">
                                                                {{ __('messages.max_tokens') }}
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="{{ __('messages.max_tokens_tooltip') }}"></i>
                                                            </label>
                                                            <input type="number" 
                                                                   class="form-control @error('models.'.$modelKey.'.max_tokens') is-invalid @enderror" 
                                                                   id="tokens_{{ $modelKey }}" 
                                                                   name="models[{{ $modelKey }}][max_tokens]" 
                                                                   value="{{ old('models.'.$modelKey.'.max_tokens', $model['max_tokens']) }}" 
                                                                   min="100" max="8192">
                                                            @error('models.'.$modelKey.'.max_tokens')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="top_p_{{ $modelKey }}" class="form-label">
                                                                {{ __('messages.top_p') }}
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="{{ __('messages.top_p_tooltip') }}"></i>
                                                            </label>
                                                            <input type="number" 
                                                                   class="form-control @error('models.'.$modelKey.'.top_p') is-invalid @enderror" 
                                                                   id="top_p_{{ $modelKey }}" 
                                                                   name="models[{{ $modelKey }}][top_p]" 
                                                                   value="{{ old('models.'.$modelKey.'.top_p', $model['top_p'] ?? 0.95) }}" 
                                                                   min="0" max="1" step="0.01">
                                                            @error('models.'.$modelKey.'.top_p')
                                                                <div class="invalid-feedback">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    
                                                    @if($model['provider'] === 'google')
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="top_k_{{ $modelKey }}" class="form-label">
                                                                    {{ __('messages.top_k') }}
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="{{ __('messages.top_k_tooltip') }}"></i>
                                                                </label>
                                                                <input type="number" 
                                                                       class="form-control @error('models.'.$modelKey.'.top_k') is-invalid @enderror" 
                                                                       id="top_k_{{ $modelKey }}" 
                                                                       name="models[{{ $modelKey }}][top_k]" 
                                                                       value="{{ old('models.'.$modelKey.'.top_k', $model['top_k'] ?? 40) }}" 
                                                                       min="1" max="100">
                                                                @error('models.'.$modelKey.'.top_k')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($model['provider'] === 'openai')
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="freq_pen_{{ $modelKey }}" class="form-label">
                                                                    {{ __('messages.frequency_penalty') }}
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="{{ __('messages.frequency_penalty_tooltip') }}"></i>
                                                                </label>
                                                                <input type="number" 
                                                                       class="form-control @error('models.'.$modelKey.'.frequency_penalty') is-invalid @enderror" 
                                                                       id="freq_pen_{{ $modelKey }}" 
                                                                       name="models[{{ $modelKey }}][frequency_penalty]" 
                                                                       value="{{ old('models.'.$modelKey.'.frequency_penalty', $model['frequency_penalty'] ?? 0.0) }}" 
                                                                       min="-2" max="2" step="0.1">
                                                                @error('models.'.$modelKey.'.frequency_penalty')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($model['provider'] === 'openai')
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="pres_pen_{{ $modelKey }}" class="form-label">
                                                                    {{ __('messages.presence_penalty') }}
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="{{ __('messages.presence_penalty_tooltip') }}"></i>
                                                                </label>
                                                                <input type="number" 
                                                                       class="form-control @error('models.'.$modelKey.'.presence_penalty') is-invalid @enderror" 
                                                                       id="pres_pen_{{ $modelKey }}" 
                                                                       name="models[{{ $modelKey }}][presence_penalty]" 
                                                                       value="{{ old('models.'.$modelKey.'.presence_penalty', $model['presence_penalty'] ?? 0.0) }}" 
                                                                       min="-2" max="2" step="0.1">
                                                                @error('models.'.$modelKey.'.presence_penalty')
                                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('messages.save_settings') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Tooltips are now initialized globally in layout.blade.php

// Function to delete API key
function deleteApiKey(provider) {
    if (confirm('{{ __('messages.delete_api_key_confirm') }}')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route('settings.deleteApiKey') }}';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        form.appendChild(methodField);
        
        const providerField = document.createElement('input');
        providerField.type = 'hidden';
        providerField.name = 'provider';
        providerField.value = provider;
        form.appendChild(providerField);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection