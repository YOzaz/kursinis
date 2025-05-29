@extends('layout')

@section('title', 'Dažniausiai užduodami klausimai')

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-question-circle me-2"></i>Dažniausiai užduodami klausimai</h1>
                    <p class="text-muted mb-0">FAQ apie propagandos analizės sistemos naudojimą</p>
                </div>
                <a href="{{ route('help.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Grįžti į pagalbą
                </a>
            </div>

            <div class="accordion" id="faqAccordion">
                
                <!-- Bendrieji klausimai -->
                <h4 class="mt-4 mb-3 text-primary">Bendrieji klausimai</h4>
                
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                            Kas yra ATSPARA metodologija?
                        </button>
                    </h2>
                    <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>ATSPARA yra Vilniaus universiteto projektas, skirtas lietuvių kalbos propagandos ir dezinformacijos tyrimams. Metodologija aprėpia 21 propagandos techniką, pritaikytą lietuvių kultūros kontekstui.</p>
                            <p>Daugiau informacijos: <a href="https://www.atspara.mif.vu.lt/" target="_blank">atspara.mif.vu.lt</a></p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                            Koks yra sistemos tikslumas?
                        </button>
                    </h2>
                    <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Sistemos tikslumas priklauso nuo:</p>
                            <ul>
                                <li><strong>Teksto pobūdžio:</strong> Aiškesnė propaganda aptinkama geriau</li>
                                <li><strong>Pasirinkto AI modelio:</strong> Skirtingi modeliai turi skirtingus stiprumus</li>
                                <li><strong>Kultūrinio konteksto:</strong> Lietuvių kalbos specifikos</li>
                                <li><strong>Teksto ilgio:</strong> Ilgesni tekstai gali suteikti daugiau konteksto</li>
                            </ul>
                            <p>Rekomenduojame naudoti kelis modelius ir palyginti rezultatus, kadangi kiekvienas AI modelis turi savų ypatumų.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                            Kiek laiko užtrunka analizė?
                        </button>
                    </h2>
                    <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Analizės trukmė priklauso nuo:</p>
                            <ul>
                                <li><strong>Tekstų skaičiaus:</strong> ~2-5 sekundės per tekstą per modelį</li>
                                <li><strong>Pasirinktų modelių skaičiaus:</strong> 6 modeliai = 6x ilgiau</li>
                                <li><strong>Teksto ilgio:</strong> Ilgesni tekstai užtrunka šiek tiek ilgiau</li>
                            </ul>
                            <p><strong>Pavyzdys:</strong> 100 tekstų su 3 modeliais gali užtrukti 10-30 minučių priklausomai nuo API apkrovos.</p>
                        </div>
                    </div>
                </div>

                <!-- Naudojimo klausimai -->
                <h4 class="mt-5 mb-3 text-primary">Naudojimo klausimai</h4>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                            Kokio formato failą turiu įkelti?
                        </button>
                    </h2>
                    <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Sistema priima JSON failą su šia struktūra:</p>
                            <pre class="bg-light p-3"><code>{
  "texts": [
    {
      "id": "1",
      "content": "Analizuojamas tekstas..."
    },
    {
      "id": "2", 
      "content": "Kitas tekstas..."
    }
  ],
  "expert_annotations": {
    "1": {
      "primaryChoice": {"choices": ["yes"]},
      "annotations": [...]
    }
  }
}</code></pre>
                            <p><code>expert_annotations</code> sekcija yra neprivaloma.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                            Kaip pasirinkti modelius?
                        </button>
                    </h2>
                    <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p><strong>Premium modeliai</strong> (pagal nutylėjimą pažymėti):</p>
                            <ul>
                                <li><strong>Claude Opus 4:</strong> Naujausia Anthropic versija</li>
                                <li><strong>GPT-4.1:</strong> Optimizuota OpenAI versija</li>
                                <li><strong>Gemini 2.5 Pro:</strong> Google pažangiausias modelis</li>
                            </ul>
                            
                            <p><strong>Standard modeliai:</strong></p>
                            <ul>
                                <li><strong>Claude Sonnet 4:</strong> Greitas Anthropic modelis</li>
                                <li><strong>GPT-4o:</strong> OpenAI multimodalus modelis</li>
                                <li><strong>Gemini 2.5 Flash:</strong> Google greitas modelis</li>
                            </ul>
                            
                            <p>Rekomenduojame išbandyti kelis modelius ir palyginti rezultatus, nes skirtingi modeliai gali skirtingai interpretuoti tą patį tekstą.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                            Kas yra custom RISEN prompt'as?
                        </button>
                    </h2>
                    <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>RISEN prompt'as leidžia pritaikyti analizės instrukcijas specifiniams poreikiams:</p>
                            <ul>
                                <li><strong>Role:</strong> Keisti AI vaidmenį (pvz., žurnalistikos ekspertas)</li>
                                <li><strong>Instructions:</strong> Pridėti specifinių nurodymų</li>
                                <li><strong>Situation:</strong> Apibrėžti kontekstą (pvz., rinkimų propaganda)</li>
                                <li><strong>Execution:</strong> Modifikuoti analizės metodus</li>
                                <li><strong>Needle:</strong> Keisti rezultato formatą</li>
                            </ul>
                            <p>Naudokite "Peržiūrėti pilną prompt'ą" mygtuką, kad pamatytumėte galutinį prompt'ą.</p>
                        </div>
                    </div>
                </div>

                <!-- Techniniai klausimai -->
                <h4 class="mt-5 mb-3 text-primary">Techniniai klausimai</h4>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                            Kodėl nerodo metrikų?
                        </button>
                    </h2>
                    <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Metrikos nebus skaičiuojamos, jei:</p>
                            <ul>
                                <li>JSON faile nėra <code>expert_annotations</code> sekcijos</li>
                                <li>Ekspertų anotacijos neturi reikiamos struktūros</li>
                                <li>Text ID nesutampa tarp tekstų ir anotacijų</li>
                            </ul>
                            <p>Tokiu atveju matysite "Nėra ekspertų anotacijų" žinutę.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                            Kaip interpretuoti rezultatus?
                        </button>
                    </h2>
                    <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p><strong>Propaganda sprendimas:</strong></p>
                            <ul>
                                <li><span class="badge bg-danger">Propaganda</span> - AI nusprendė, kad tekstas yra propaganda</li>
                                <li><span class="badge bg-success">Ne propaganda</span> - AI nusprendė, kad tekstas nėra propaganda</li>
                            </ul>
                            
                            <p><strong>Technikos:</strong> Ženklels (badges) rodo aptiktas propagandos technikas.</p>
                            
                            <p><strong>Metrikos:</strong></p>
                            <ul>
                                <li><strong>P:</strong> Precision (tikslumas)</li>
                                <li><strong>R:</strong> Recall (atsaukimas)</li>
                                <li><strong>F1:</strong> F1 balas</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                            Ar galiu eksportuoti rezultatus?
                        </button>
                    </h2>
                    <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Taip! Sistema siūlo kelias eksportavimo galimybes:</p>
                            <ul>
                                <li><strong>CSV failas:</strong> Struktūruoti duomenys Excel analizei</li>
                                <li><strong>JSON failas:</strong> Pilni duomenys programiniam apdorojimui</li>
                            </ul>
                            
                            <p>CSV faile rasite:</p>
                            <ul>
                                <li>Teksto ID ir turinį</li>
                                <li>Propaganda sprendimus kiekviename modelyje</li>
                                <li>Aptiktas technikas</li>
                                <li>Metrikas (jei yra ekspertų anotacijos)</li>
                                <li>Vykdymo laikus</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Troubleshooting -->
                <h4 class="mt-5 mb-3 text-primary">Problemų sprendimas</h4>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq10">
                            Analizė "įstrigo" arba nepavyksta
                        </button>
                    </h2>
                    <div id="faq10" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p><strong>Galimos priežastys ir sprendimai:</strong></p>
                            <ul>
                                <li><strong>AI API problema:</strong> Palaukite ir pakartokite vėliau</li>
                                <li><strong>Per didelis failas:</strong> Pabandykite su mažesniu tekstų skaičiumi</li>
                                <li><strong>Neteisingas JSON formatas:</strong> Patikrinkite failo struktūrą</li>
                                <li><strong>Serverio perkrova:</strong> Pabandykite nepopuliariomis valandomis</li>
                            </ul>
                            
                            <p>Jei problema išlieka, susisiekite su administratoriumi su analizės ID.</p>
                        </div>
                    </div>
                </div>

                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq11">
                            Netikėti rezultatai
                        </button>
                    </h2>
                    <div id="faq11" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <p>Jei AI rezultatai atrodo netikėti:</p>
                            <ul>
                                <li><strong>Patikrinkite tekstą:</strong> Ar nėra koduotės problemų?</li>
                                <li><strong>Palyginkite modelius:</strong> Skirtingi modeliai gali duoti skirtingus rezultatus</li>
                                <li><strong>Vertinkite kontekstą:</strong> AI gali nesuprasti kultūrinio konteksto</li>
                                <li><strong>Naudokite custom prompt'ą:</strong> Pritaikykite instrukcijas specifiniam atvejui</li>
                            </ul>
                            
                            <p>Atsiminkite: AI nėra 100% tikslus ir rezultatai turi būti vertinami kritiškai.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-5">
                <div class="card-body text-center">
                    <h5>Neradote atsakymo?</h5>
                    <p class="text-muted">Susisiekite su mumis el. paštu</p>
                    <a href="mailto:marijus.planciunas@mif.stud.vu.lt" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>marijus.planciunas@mif.stud.vu.lt
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection