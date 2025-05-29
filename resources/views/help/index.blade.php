@extends('layout')

@section('title', 'Pagalba ir dokumentacija')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-question-circle me-2"></i>Pagalba ir dokumentacija</h1>
                    <p class="text-muted mb-0">Išsami informacija apie propagandos analizės sistemą</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Grįžti
                </a>
            </div>

            <!-- Quick links -->
            <div class="row mb-5">
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-question fa-3x text-primary mb-3"></i>
                            <h5>Dažniausiai užduodami klausimai</h5>
                            <p class="text-muted">Atsakymai į populiariausius klausimus</p>
                            <a href="{{ route('help.faq') }}" class="btn btn-primary">
                                <i class="fas fa-list me-1"></i>Peržiūrėti FAQ
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-envelope fa-3x text-success mb-3"></i>
                            <h5>Susisiekite su mumis</h5>
                            <p class="text-muted">Reikia papildomos pagalbos?</p>
                            <a href="mailto:marijus.planciunas@mif.stud.vu.lt" class="btn btn-success">
                                <i class="fas fa-envelope me-1"></i>Rašyti email
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RISEN metodologija -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-brain me-2"></i>RISEN metodologija</h3>
                </div>
                <div class="card-body">
                    <p>RISEN yra struktūruotas prompt'ų kūrimo metodas, optimizuotas AI modelių instrukcijoms:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-user-tie text-primary me-2"></i><strong>Role (Vaidmuo)</strong></h6>
                            <p class="small">Apibrėžia AI vaidmenį ir ekspertizės sritį - propagandos analizės ekspertas</p>
                            
                            <h6><i class="fas fa-list-check text-success me-2"></i><strong>Instructions (Instrukcijos)</strong></h6>
                            <p class="small">Konkretūs nurodymai, ką AI turi daryti - analizuoti tekstą pagal ATSPARA metodologiją</p>
                            
                            <h6><i class="fas fa-map text-info me-2"></i><strong>Situation (Situacija)</strong></h6>
                            <p class="small">Kontekstas ir aplinkybės - lietuvių kalbos tekstų propaganda analizė</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-cogs text-warning me-2"></i><strong>Execution (Vykdymas)</strong></h6>
                            <p class="small">Detalūs žingsniai ir metodai - kaip atlikti analizę žingsnis po žingsnio</p>
                            
                            <h6><i class="fas fa-crosshairs text-danger me-2"></i><strong>Needle (Esmė)</strong></h6>
                            <p class="small">Svarbiausias rezultatas - JSON formato atsakymas su propaganda sprendimu ir anotacijomis</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ATSPARA propagandos technikos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt me-2"></i>ATSPARA propagandos technikos</h3>
                </div>
                <div class="card-body">
                    <p>Sistema atpažįsta <strong>11 propagandos technikos</strong> pagal faktines ATSPARA ekspertų anotacijas:</p>
                    
                    <div class="row">
                        @php
                            $techniques = config('llm.propaganda_techniques');
                            $techniqueGroups = [
                                'Emocinė raiška' => ['emotionalExpression'],
                                'Išsisukinėjimas' => ['whataboutismRedHerringStrawMan'],
                                'Supaprastinimas' => ['simplification'],
                                'Neapibrėžtumas' => ['uncertainty'],
                                'Apeliavimas į autoritetą' => ['appealToAuthority'],
                                'Patriotizmas' => ['wavingTheFlag'],
                                'Socialinis spaudimas' => ['followingBehind'],
                                'Diskreditavimas' => ['doubt'],
                                'Lyginimas su nekenčiamais' => ['reductioAdHitlerum'],
                                'Įtakos didinimas' => ['repetition'],
                                'Neapibrėžta' => ['unclear']
                            ];
                        @endphp
                        
                        @foreach($techniqueGroups as $groupName => $techKeys)
                            <div class="col-lg-6 mb-3">
                                <h6 class="text-primary">{{ $groupName }}</h6>
                                <ul class="list-unstyled ms-3">
                                    @foreach($techKeys as $key)
                                        @if(isset($techniques[$key]))
                                            <li class="mb-2">
                                                <strong>{{ $key }}:</strong><br>
                                                <small class="text-muted">{{ $techniques[$key] }}</small>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Metrikos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line me-2"></i>Tikslumas metrikos</h3>
                </div>
                <div class="card-body">
                    <p>Sistema skaičiuoja šias metrikas lyginant AI rezultatus su ekspertų anotacijomis:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-bullseye text-success me-2"></i><strong>Precision (Tikslumas)</strong></h6>
                            <p class="small">Kiek AI rastų propagandos fragmentų iš tikrųjų yra propaganda.</p>
                            <code>TP / (TP + FP)</code>
                            
                            <h6><i class="fas fa-search text-info me-2"></i><strong>Recall (Atsaukimas)</strong></h6>
                            <p class="small">Kokią dalį visų propagandos fragmentų AI surado.</p>
                            <code>TP / (TP + FN)</code>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-balance-scale text-warning me-2"></i><strong>F1 Score</strong></h6>
                            <p class="small">Bendras tikslumo ir atsaukimo įvertis (harmoninis vidurkis).</p>
                            <code>2 × (Precision × Recall) / (Precision + Recall)</code>
                            
                            <h6><i class="fas fa-map-marker-alt text-danger me-2"></i><strong>Position Accuracy</strong></h6>
                            <p class="small">Kiek tiksliai AI nustatė propagandos fragmentų pozicijas tekste.</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Paaiškinimas:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>TP (True Positives)</strong> - Teisingi teigiami: AI teisingai atpažino propagandą</li>
                            <li><strong>FP (False Positives)</strong> - Klaidingi teigiami: AI klaidingai atpažino propagandą</li>
                            <li><strong>FN (False Negatives)</strong> - Klaidingi neigiami: AI praleido tikrą propagandą</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sistemos galimybės -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-cog me-2"></i>Sistemos galimybės</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-robot text-primary me-2"></i>AI modeliai</h6>
                            <ul class="small">
                                <li>Claude Opus 4 & Sonnet 4 (Anthropic)</li>
                                <li>GPT-4.1 & GPT-4o (OpenAI)</li>
                                <li>Gemini 2.5 Pro & Flash (Google)</li>
                            </ul>
                            
                            <h6><i class="fas fa-file-alt text-success me-2"></i>Palaikomi formatai</h6>
                            <ul class="small">
                                <li>JSON failai su tekstų sąrašu</li>
                                <li>Ekspertų anotacijos (neprivaloma)</li>
                                <li>CSV eksportas rezultatams</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-language text-info me-2"></i>Kalbos palaikymas</h6>
                            <ul class="small">
                                <li>Optimizuota lietuvių kalbai</li>
                                <li>ATSPARA metodologija</li>
                                <li>Kultūrinis kontekstas</li>
                            </ul>
                            
                            <h6><i class="fas fa-chart-bar text-warning me-2"></i>Analizės funkcijos</h6>
                            <ul class="small">
                                <li>Realaus laiko progresas</li>
                                <li>Pakartotinės analizės</li>
                                <li>Custom RISEN prompt'ai</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Akademinis kontekstas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap me-2"></i>Akademinis tyrimo kontekstas</h3>
                </div>
                <div class="card-body">
                    <p>Ši sistema sukurta kursinio darbo rėmuose, siekiant:</p>
                    <ul>
                        <li><strong>Ištirti</strong> AI modelių efektyvumą lietuvių kalbos propagandos analizėje</li>
                        <li><strong>Pritaikyti</strong> ATSPARA projekto metodologiją automatinei analizei</li>
                        <li><strong>Palyginti</strong> skirtingų AI modelių rezultatus</li>
                        <li><strong>Sukurti</strong> įrankį, kuris galėtų būti naudojamas tolimesniems tyrimams</li>
                    </ul>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Tyrimo pobūdis:</strong> Rezultatai naudojami akademiniais tikslais ir gali būti netikslūs. 
                        Sistema yra prototipas, skirtas metodologijos tyrimui.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection