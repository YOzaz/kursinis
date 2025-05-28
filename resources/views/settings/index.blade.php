@extends('layout')

@section('title', 'Sistemos nustatymai')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Sistemos nustatymai</h1>
            <p class="text-muted mb-0">Modelių konfigūracija ir sistemos parametrai</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Grįžti
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
            <strong>Klaida:</strong> Patikrinkite įvesties duomenis.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-cogs me-2"></i>Modelių numatytosios nuostatos
                    </h5>
                    <form method="POST" action="{{ route('settings.resetDefaults') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-warning" 
                                onclick="return confirm('Ar tikrai norite grąžinti visas nuostatas į pradinius nustatymus?')">
                            <i class="fas fa-undo me-1"></i>Atkurti numatytuosius
                        </button>
                    </form>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Svarbu:</strong> Šie nustatymai paveiks visas naujas analizes. 
                        Esančios analizės išliks nepaveiktos. Žemesnė temperatūra (0.05) užtikrina 
                        nuoseklesnius propaganda analizės rezultatus.
                    </div>

                    <form method="POST" action="{{ route('settings.updateDefaults') }}">
                        @csrf
                        
                        @foreach($providers as $providerKey => $provider)
                            <div class="mb-5">
                                <h6 class="fw-bold border-bottom pb-2 mb-3">
                                    <i class="{{ $provider['icon'] }} text-{{ $provider['color'] }} me-2"></i>
                                    {{ $provider['name'] }} modeliai
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
                                                                Temperatūra
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="Kontroliuoja atsitiktinumą. Žemesnės reikšmės (0.05) - nuoseklesni rezultatai, aukštesnės - kūrybiškesni."></i>
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
                                                                Maksimalūs tokenai
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="Maksimalus generuojamo atsakymo ilgis tokenais."></i>
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
                                                                Top P
                                                                <i class="fas fa-question-circle text-muted ms-1" 
                                                                   data-bs-toggle="tooltip" 
                                                                   title="Kontroliuoja žodžių pasirinkimo įvairovę. 0.95 rekomenduojama analizei."></i>
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
                                                                    Top K
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="Riboja kandidatų žodžių skaičių (tik Google modeliams)."></i>
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
                                                                    Frequency Penalty
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="Mažina žodžių kartojimą (tik OpenAI modeliams)."></i>
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
                                                                    Presence Penalty
                                                                    <i class="fas fa-question-circle text-muted ms-1" 
                                                                       data-bs-toggle="tooltip" 
                                                                       title="skatina naujų temų apsvarstimą (tik OpenAI modeliams)."></i>
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
                                <i class="fas fa-save me-2"></i>Išsaugoti nustatymus
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
</script>
@endsection