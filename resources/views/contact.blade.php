@extends('layout')

@section('title', 'Kontaktai')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-envelope me-2"></i>Kontaktai</h1>
                    <p class="text-muted mb-0">Susisiekite su sistemos kūrėjais</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Grįžti
                </a>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-graduate me-2"></i>Kursinio darbo autorius
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Marijus Plančiūnas</h6>
                            <p class="text-muted">Magistro studijos, Duomenų mokslas</p>
                            
                            <div class="contact-info">
                                <div class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:marijus.planciunas@mif.stud.vu.lt">
                                        marijus.planciunas@mif.stud.vu.lt
                                    </a>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-university text-success me-2"></i>
                                    Vilniaus universitetas, MIF
                                </div>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <strong>Atsakingas už:</strong><br>
                                • Sistemos architektūra ir plėtra<br>
                                • AI modelių integracija<br>
                                • RISEN metodologijos implementacija<br>
                                • Techninės problemos ir klaidų taisymas
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Mokslinis vadovas
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Prof. Dr. Darius Plikynas</h6>
                            <p class="text-muted">Duomenų mokslo ir skaitmeninių technologijų katedra</p>
                            
                            <div class="contact-info">
                                <div class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:darius.plikynas@mif.vu.lt">
                                        darius.plikynas@mif.vu.lt
                                    </a>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-university text-success me-2"></i>
                                    Vilniaus universitetas, MIF
                                </div>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <strong>Atsakingas už:</strong><br>
                                • Mokslinė metodologija<br>
                                • ATSPARA projekto koordinavimas<br>
                                • Akademinės konsultacijos<br>
                                • Rezultatų vertinimas
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>ATSPARA projektas
                    </h5>
                </div>
                <div class="card-body">
                    <p>
                        Ši sistema yra ATSPARA projekto dalis - Vilniaus universiteto iniciatyvos, 
                        skirtos propagandos ir dezinformacijos tyrimams lietuvių kalboje.
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-globe text-primary me-2"></i>Projekto svetainė</h6>
                            <a href="https://www.atspara.mif.vu.lt/" target="_blank" class="btn btn-outline-primary mb-3">
                                <i class="fas fa-external-link-alt me-1"></i>atspara.mif.vu.lt
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-database text-success me-2"></i>Duomenų šaltiniai</h6>
                            <p class="small text-muted">
                                Anotacijos metodologija ir propagandos technikos klasifikacija
                                remiantis ATSPARA korpuso tyrimais.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-bug me-2"></i>Problemų pranešimas
                    </h5>
                </div>
                <div class="card-body">
                    <p>Jei susidūrėte su techninėmis problemomis, prašome pateikti šią informaciją:</p>
                    
                    <div class="alert alert-info">
                        <h6>Reikiama informacija:</h6>
                        <ul class="mb-0">
                            <li><strong>Analizės ID</strong> (jei susijęs su konkrečia analize)</li>
                            <li><strong>Klaidos aprašymas</strong> (ką bandėte daryti, kas nutiko)</li>
                            <li><strong>Naršyklė ir versija</strong> (pvz., Chrome 118)</li>
                            <li><strong>Įkelto failo dydis ir struktūra</strong> (jei susijęs su įkėlimu)</li>
                            <li><strong>Ekrano nuotrauka</strong> (jei yra klaidos pranešimas)</li>
                        </ul>
                    </div>
                    
                    <p>Kuo išsamesnė informacija, tuo greičiau galėsime išspręsti problemą.</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-clock me-2"></i>Atsako laikas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <h6>Kritinės klaidos</h6>
                                <small class="text-muted">Iki 24 val.</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-tools fa-2x text-warning mb-2"></i>
                                <h6>Techninės problemos</h6>
                                <small class="text-muted">2-3 darbo dienos</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3">
                                <i class="fas fa-question-circle fa-2x text-info mb-2"></i>
                                <h6>Bendrieji klausimai</h6>
                                <small class="text-muted">Iki 1 savaitės</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Pastaba:</strong> Atsakymo laikas gali būti ilgesnis studentų sesijos metu arba atostogų laikotarpiu.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection