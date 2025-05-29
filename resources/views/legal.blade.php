@extends('layout')

@section('title', 'Teisinė informacija')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-balance-scale me-2"></i>Teisinė informacija</h1>
                    <p class="text-muted mb-0">Duomenų naudojimas, privatumas ir atsakomybė</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Grįžti
                </a>
            </div>

            <!-- Bendroji informacija -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle me-2"></i>Bendroji informacija</h3>
                </div>
                <div class="card-body">
                    <p>
                        Ši propagandos analizės sistema yra sukurta akademinių tyrimų tikslais 
                        Vilniaus universiteto Matematikos ir informatikos fakulteto magistro studijų programos rėmuose.
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-university text-primary me-2"></i>Institucija</h6>
                            <p>Vilniaus universitetas<br>
                            Matematikos ir informatikos fakultetas<br>
                            Universiteto g. 3, Vilnius</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar text-success me-2"></i>Sukūrimo data</h6>
                            <p>2025 m.<br>
                            Kursinis darbas</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duomenų naudojimas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-database me-2"></i>Duomenų naudojimas ir saugumas</h3>
                </div>
                <div class="card-body">
                    <h6>Kokie duomenys yra apdorojami:</h6>
                    <ul>
                        <li><strong>Tekstai analizei:</strong> Jūsų įkelti tekstai siunčiami į AI paslaugų teikėjus (OpenAI, Anthropic, Google)</li>
                        <li><strong>Analizės rezultatai:</strong> AI modelių atsakymai išsaugomi sistemos duomenų bazėje</li>
                        <li><strong>Metaduomenys:</strong> Analizės laikas, naudoti modeliai, vykdymo statistikos</li>
                        <li><strong>Ekspertų anotacijos:</strong> Jei pateiktos, naudojamos metrikų skaičiavimui</li>
                    </ul>

                    <h6 class="mt-4">Kaip duomenys yra apsaugoti:</h6>
                    <ul>
                        <li><strong>HTTPS šifravimas:</strong> Visi duomenų perdavimai apsaugoti SSL/TLS</li>
                        <li><strong>Universitetinė infrastruktūra:</strong> Serveriai veikia VU kontrollē</li>
                        <li><strong>Prieigos kontrolė:</strong> Ribota prieiga prie duomenų bazės</li>
                        <li><strong>API saugumas:</strong> Naudojami oficialūs API raktai su ribotomis teisėmis</li>
                    </ul>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Svarbu žinoti:</h6>
                        <p class="mb-0">
                            Jūsų tekstai bus siunčiami į trečiųjų šalių AI paslaugas (OpenAI, Anthropic, Google). 
                            Nors šie tiekėjai teigia, kad neišsaugo duomenų ilgalaikio, rekomenduojame nenaudoti 
                            konfidencialių ar asmeninių duomenų.
                        </p>
                    </div>
                </div>
            </div>

            <!-- GDPR ir privatumas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt me-2"></i>GDPR ir privatumo apsauga</h3>
                </div>
                <div class="card-body">
                    <h6>Jūsų teisės:</h6>
                    <ul>
                        <li><strong>Prieigos teisė:</strong> Galite paprašyti informacijos apie saugomus duomenis</li>
                        <li><strong>Ištaisymo teisė:</strong> Galite prašyti ištaisyti neteisingus duomenis</li>
                        <li><strong>Ištrynimo teisė:</strong> Galite prašyti ištrinti savo duomenis</li>
                        <li><strong>Perkėlimo teisė:</strong> Galite gauti duomenis struktūruotu formatu</li>
                    </ul>

                    <h6 class="mt-4">Duomenų saugojimo trukmė:</h6>
                    <ul>
                        <li><strong>Analizės rezultatai:</strong> Saugomi iki sistemos pabaigos (akademinio darbo įteikimo)</li>
                        <li><strong>Loginiai įrašai:</strong> 30 dienų saugojimo terminas</li>
                        <li><strong>Klaidų informacija:</strong> Ištrinami po problemos išsprendimo</li>
                    </ul>

                    <div class="alert alert-info">
                        <p class="mb-0">
                            <strong>Duomenų valdytojas:</strong> Vilniaus universitetas<br>
                            <strong>Kontaktas:</strong> marijus.planciunas@mif.stud.vu.lt
                        </p>
                    </div>
                </div>
            </div>

            <!-- Atsakomybės apribojimas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-circle me-2"></i>Atsakomybės apribojimas</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h6><strong>Svarbu:</strong></h6>
                        <p class="mb-0">
                            Ši sistema yra akademinio tyrimo produktas ir nėra skirta komerciniam naudojimui. 
                            AI rezultatai gali būti netikslūs arba šališki.
                        </p>
                    </div>

                    <h6>Sistemos apribojimai:</h6>
                    <ul>
                        <li><strong>Tikslumas:</strong> AI modeliai nėra 100% tikslūs</li>
                        <li><strong>Šališkumas:</strong> Modeliai gali turėti kultūrinių ar kalbinių šališkumų</li>
                        <li><strong>Kontekstas:</strong> AI gali nesuprasti sudėtingo kultūrinio konteksto</li>
                        <li><strong>Stabilumas:</strong> Kaip prototipas, sistema gali veikti nestabiliai</li>
                    </ul>

                    <h6 class="mt-4">Neatsakome už:</h6>
                    <ul>
                        <li>Klaidingus AI analizės rezultatus</li>
                        <li>Sistemos neveikimą arba duomenų praradimą</li>
                        <li>Trečiųjų šalių (AI tiekėjų) veiksmus</li>
                        <li>Sprendimus, priimtus remiantis sistemos rezultatais</li>
                    </ul>
                </div>
            </div>

            <!-- Akademinės etikos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap me-2"></i>Akademinės etikos principai</h3>
                </div>
                <div class="card-body">
                    <h6>Tyrimo etika:</h6>
                    <ul>
                        <li><strong>Skaidrumas:</strong> Metodologija ir kodas yra atviri peržiūrai</li>
                        <li><strong>Objektyvumas:</strong> Stengiamasi vengti šališkumo analizėje</li>
                        <li><strong>Atidžiai</strong> laikomasi ATSPARA projekto metodologijos</li>
                        <li><strong>Akademinė laisvė:</strong> Rezultatai pateikiami nekeičiant jų</li>
                    </ul>

                    <h6 class="mt-4">Citavimas ir naudojimas:</h6>
                    <ul>
                        <li>Sistema galima naudoti akademiniams tikslams</li>
                        <li>Prašome cituoti ATSPARA projektą ir šį darbą</li>
                        <li>Komerciniam naudojimui reikalingas atskirtas leidimas</li>
                    </ul>

                    <div class="alert alert-info">
                        <h6>Rekomenduojamas citavimas:</h6>
                        <p class="mb-0 font-monospace small">
                            Plančiūnas, M. (2025). Propagandos analizės sistema naudojant RISEN metodologiją 
                            ir ATSPARA korpusą. Kursinis darbas. Vilniaus universitetas.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kontaktai -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-envelope me-2"></i>Klausimų sprendimas</h3>
                </div>
                <div class="card-body">
                    <p>Jei turite klausimų dėl duomenų naudojimo, privatumo ar sistemos veikimo:</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Techninis kontaktas:</h6>
                            <p>
                                <strong>Marijus Plančiūnas</strong><br>
                                <a href="mailto:marijus.planciunas@mif.stud.vu.lt">
                                    marijus.planciunas@mif.stud.vu.lt
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Akademinis vadovas:</h6>
                            <p>
                                <strong>Prof. Dr. Darius Plikynas</strong><br>
                                <a href="mailto:darius.plikynas@mif.vu.lt">
                                    darius.plikynas@mif.vu.lt
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paskutinis atnaujinimas -->
            <div class="text-center text-muted">
                <small>
                    <i class="fas fa-calendar-alt me-1"></i>
                    Paskutinį kartą atnaujinta: 2025-05-28
                </small>
            </div>
        </div>
    </div>
</div>
@endsection