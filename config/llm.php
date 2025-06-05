<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LLM API konfigūracija
    |--------------------------------------------------------------------------
    |
    | Čia aprašytos visų LLM modelių konfigūracijos propagandos ir 
    | dezinformacijos analizei lietuviškame tekste.
    |
    | Kursinio darbo autorius: Marijus Plančiūnas (marijus.planciunas@mif.stud.vu.lt)
    | Dėstytojas: Prof. Dr. Darius Plikynas (darius.plikynas@mif.vu.lt)
    |
    | Duomenų šaltiniai ir metodologija:
    | - ATSPARA korpuso duomenys ir klasifikavimo metodologija: https://www.atspara.mif.vu.lt/
    |
    | API implementacija:
    | - OpenAI: naudoja oficialų openai-php/client paketą (fiksuotas 404 error)
    | - Claude: naudoja HTTP klientą su teisingais v1 endpoint'ais
    | - Gemini: naudoja HTTP klientą su v1beta API
    |
    */

    'models' => [
        
        // === ANTHROPIC CLAUDE MODELIAI ===
        
        'claude-opus-4' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1/',
            'model' => 'claude-opus-4-20250514',
            'max_tokens' => 4096,
            'context_window' => 200000, // 200K tokens input context
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'provider' => 'anthropic',
            'tier' => 'premium',
            'description' => 'Anthropic\'s most advanced coding model, world\'s best coding model',
            'is_default' => true,
            'batch_size' => 50, // Number of texts to process in one request
        ],
        
        'claude-sonnet-4' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1/',
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'context_window' => 200000, // 200K tokens input context
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'provider' => 'anthropic',
            'tier' => 'standard',
            'description' => 'Evolution of Claude 3.5 Sonnet, excelling in coding',
            'is_default' => false,
            'batch_size' => 50, // Number of texts to process in one request
        ],

        // === OPENAI GPT MODELIAI ===
        
        'gpt-4.1' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4.1',
            'max_tokens' => 4096,
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'provider' => 'openai',
            'tier' => 'premium',
            'description' => 'OpenAI GPT-4.1 model with latest improvements',
            'context_window' => 1000000, // 1M tokens input context
            'is_default' => true,
            'batch_size' => 100, // Number of texts to process in one request
        ],
        
        'gpt-4o-latest' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'max_tokens' => 4096,
            'context_window' => 128000, // 128K tokens input context
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'provider' => 'openai',
            'tier' => 'standard',
            'description' => 'OpenAI\'s multimodal flagship model with audio, vision, and text',
            'is_default' => false,
            'batch_size' => 50, // Number of texts to process in one request
        ],

        // === GOOGLE GEMINI MODELIAI ===
        
        'gemini-2.5-pro' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/',
            'model' => 'gemini-2.5-pro-preview-05-06',
            'max_tokens' => 32768, // Increased to handle large responses
            'context_window' => 2000000, // 2M tokens input context
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'top_k' => 40,
            'provider' => 'google',
            'tier' => 'premium',
            'description' => 'Google\'s most advanced model for complex reasoning tasks',
            'is_default' => true,
            'batch_size' => 200, // Number of texts to process in one request
        ],
        
        'gemini-2.5-flash' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/',
            'model' => 'gemini-2.5-flash-preview-05-20',
            'max_tokens' => 32768, // Increased to handle large responses
            'context_window' => 1000000, // 1M tokens input context
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'top_k' => 40,
            'provider' => 'google',
            'tier' => 'standard',
            'description' => 'Google\'s fast and efficient model with latest capabilities',
            'is_default' => false,
            'batch_size' => 100, // Number of texts to process in one request
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Tiekėjų konfigūracija
    |--------------------------------------------------------------------------
    */
    
    'providers' => [
        'anthropic' => [
            'name' => 'Anthropic',
            'icon' => 'fas fa-brain',
            'color' => 'primary',
            'default_model' => 'claude-opus-4',
        ],
        'openai' => [
            'name' => 'OpenAI',
            'icon' => 'fas fa-cog',
            'color' => 'success',
            'default_model' => 'gpt-4.1',
        ],
        'google' => [
            'name' => 'Google',
            'icon' => 'fas fa-star',
            'color' => 'warning',
            'default_model' => 'gemini-2.5-pro',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Klaidų valdymo konfigūracija
    |--------------------------------------------------------------------------
    */
    
    'error_handling' => [
        'continue_on_failure' => true,
        'max_retries_per_model' => 3,
        'retry_delay_seconds' => 2,
        'exponential_backoff' => true,
        'timeout_seconds' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Bendrieji nustatymai
    |--------------------------------------------------------------------------
    */

    'max_concurrent_requests' => env('MAX_CONCURRENT_REQUESTS', 10),
    'retry_attempts' => env('RETRY_ATTEMPTS', 3),
    'request_timeout' => env('REQUEST_TIMEOUT', 60),
    'retry_delay' => 2, // sekundės

    /*
    |--------------------------------------------------------------------------
    | Propagandos technikos (ATSPARA projekto klasifikacija)
    |--------------------------------------------------------------------------
    |
    | Šios propagandos technikos apibrėžtos ATSPARA projekto anotavimo
    | instrukcijose ir naudojamos lietuviško teksto analizei.
    |
    */

    'propaganda_techniques' => [
        // Technikos pagal oficialia ATSPARA metodologija (10 kategorijų)
        'emotionalExpression' => 'Emocinė raiška - bandoma sukelti stiprius jausmus; emocinė leksika, etiketės, vertybiniai argumentai, hiperbolizavimas/sumenkinimas. Apima: apeliavimą į jausmus ir baimę, etiketų klijavimą (ad hominem), perdėtą vertinimą, blizgančius apibendrimus.',
        'whataboutismRedHerringStrawMan' => 'Whataboutism, Red Herring, Straw Man - išsisukinėjimas; oponento pozicijos, jo teiginių menkinimas; dėmesio nukreipimas kitur. Apima: whataboutism, nereikšmingų duomenų pateikimą, pozicijos iškraipymą.',
        'simplification' => 'Supaprastinimas - daroma prielaida, kad yra viena problemos priežastis; kaltė perkeliama vienam asmeniui/grupei, neanalizuojant problemos sudėtingumo. Apima: priežastinį supaprastinimą, juoda-balta mąstymą, klišės, šūkius.',
        'uncertainty' => 'Neapibrėžtumas, sąmoningas neaiškios kalbos vartojimas - vartojama neaiški kalba, kad auditorija žinutę galėtų interpretuoti savaip. Argumente vartojama neaiški frazė su keliomis galimomis reikšmėmis.',
        'appealToAuthority' => 'Apeliavimas į autoritetą - cituojami garsūs, žinomi autoritetai, kurie remia propagandisto idėją, argumentus, poziciją ir veiksmus. Rėmimasis autoritetu be papildomų įrodymų.',
        'wavingTheFlag' => 'Mojavimas vėliava (Vėliavos kėlimas) - pastangos pateisinti veiksmą remiantis patriotiškumu ar teisinantis, kad veiksmas duos naudos šaliai/grupei žmonių.',
        'followingBehind' => 'Sekimas iš paskos (Bandwagon) - apeliavimas į bandos jausmą. Pasinaudojama tuo, kas vadinama "bandos jausmu". Žmonės linkę priklausyti daugumai ir nenori likti nuošalyje.',
        'doubt' => 'Abejojimas - šmeižtas, abejonės dėl kieno nors patikimumo. Apima: abejojimą asmens/grupės patikimumu ir šmeižtą (bandymą pakenkti reputacijai).',
        'reductioAdHitlerum' => 'Reductio ad Hitlerum - siekiama įtikinti nepritarti veiksmui/idėjai, nurodant, kad tai populiaru tarp grupių, kurių tikslinis auditorija nekenčia.',
        'repetition' => 'Pakartojimas - tekste kartojama ta pati žinutė ar idėja siekiant ją įtvirtinti auditorijos sąmonėje.',
        'unclear' => 'Neapibrėžta technika - sunkiai identifikuojami ar dviprasmiški propaganda fragmentai, kuriuose technika neaiški.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dezinformacijos naratyvai (ATSPARA projekto duomenys)
    |--------------------------------------------------------------------------
    |
    | Dezinformacijos naratyvai identifikuoti ATSPARA projekto tyrimuose
    | lietuviškoje žiniasklaidoje ir socialiniuose tinkluose.
    |
    */

    'disinformation_narratives' => [
        // Naratyvai pagal faktines ATSPARA ekspertų anotacijas
        'distrustOfLithuanianInstitutions' => 'Nepasitikėjimas Lietuvos institucijomis',
        'distrustOfWesternInstitutions' => 'Nepasitikėjimas Vakarų institucijomis', 
        'lithuanianDefamation' => 'Lietuvos šmeižimas ir diskreditavimas',
        'propagandaCitation' => 'Propagandos citavimas ir platinimas',
        'warInUkraine' => 'Karo Ukrainoje naratyvas',
        'nonDeterminable' => 'Neapibrėžiamas naratyvas',
    ],

];