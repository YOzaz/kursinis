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
                            <p class="text-muted">Kursinio darbo autorius, Duomenų mokslas</p>
                            
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
                        Šis kursinis darbas remiasi ATSPARA projektu - Vilniaus universiteto iniciatyva, 
                        skirta propagandos ir dezinformacijos tyrimams lietuvių kalboje.
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
                        <i class="fas fa-info-circle me-2"></i>Akademinis tyrimas
                    </h5>
                </div>
                <div class="card-body">
                    <p>Ši sistema yra sukurta akademinio tyrimo tikslais. Jei turite klausimų apie:</p>
                    
                    <ul>
                        <li><strong>Tyrimo metodologiją</strong> ir ATSPARA projekto pritaikymą</li>
                        <li><strong>Rezultatų interpretavimą</strong> ir analizės proceso ypatumus</li>
                        <li><strong>Techninės sistemos veikimą</strong> ir galimas problemas</li>
                        <li><strong>Duomenų naudojimą</strong> tyrimo kontekste</li>
                    </ul>
                    
                    <p>Prašome susisiekti el. paštu pateikiant kuo išsamesnę informaciją apie jūsų klausimą.</p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection