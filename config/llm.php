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
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'provider' => 'anthropic',
            'tier' => 'premium',
            'description' => 'Anthropic\'s most advanced coding model, world\'s best coding model',
            'is_default' => true,
        ],
        
        'claude-sonnet-4' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1/',
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'provider' => 'anthropic',
            'tier' => 'standard',
            'description' => 'Evolution of Claude 3.5 Sonnet, excelling in coding',
            'is_default' => false,
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
            'description' => 'OpenAI\'s latest flagship model with improved coding',
            'context_window' => 1000000,
            'is_default' => true,
        ],
        
        'gpt-4o-latest' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'max_tokens' => 4096,
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
            'provider' => 'openai',
            'tier' => 'standard',
            'description' => 'OpenAI\'s multimodal flagship model with audio, vision, and text',
            'is_default' => false,
        ],

        // === GOOGLE GEMINI MODELIAI ===
        
        'gemini-2.5-pro' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/',
            'model' => 'gemini-2.5-pro-experimental',
            'max_tokens' => 4096,
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'top_k' => 40,
            'provider' => 'google',
            'tier' => 'premium',
            'description' => 'Google\'s most advanced model for complex reasoning tasks',
            'is_default' => true,
        ],
        
        'gemini-2.5-flash' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/',
            'model' => 'gemini-2.5-flash-preview-04-17',
            'max_tokens' => 4096,
            'temperature' => 0.05,  // Lower for propaganda analysis consistency
            'top_p' => 0.95,
            'top_k' => 40,
            'provider' => 'google',
            'tier' => 'standard',
            'description' => 'Google\'s best price-performance model with thinking capabilities',
            'thinking_budget' => 2048,
            'is_default' => false,
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
        // 1. Emocinė raiška
        'emotionalAppeal' => 'Apeliavimas į jausmus - siekiama sukelti stiprius jausmus/emocijas',
        'appealToFear' => 'Apeliavimas į baimę - sėjama panika ir nerimas',
        'loadedLanguage' => 'Vertinamoji, emocinė leksika - stiprias asociacijas turintys žodžiai',
        'nameCalling' => 'Etikečių klijavimas - neigiamų žodžių vartojimas oponentui sumenkinti',
        'exaggeration' => 'Perdėtas vertinimas/hiperbolizavimas arba sumenkinimas',
        'glitteringGeneralities' => '"Blizgantys" apibendrinimai - vertybiniai žodžiai be konteksto',
        
        // 2. Whataboutism_Red Herring_Straw Man
        'whataboutism' => 'Whataboutism - diskreditavimas per veidmainiavimu kaltinimą',
        'redHerring' => 'Red Herring - nereikšmingų dalykų pateikimas dėmesio nukreipimui',
        'strawMan' => 'Straw Man - oponento pozicijos pakeitimas panašiu teiginiu',
        
        // 3. Supaprastinimas
        'causalOversimplification' => 'Supaprastinimas - paprasti atsakymai į sudėtingas problemas',
        'blackAndWhite' => 'Juoda-balta - tik du alternatyvūs variantai',
        'thoughtTerminatingCliche' => 'Klišės - stereotipiniai posakiai',
        'slogans' => 'Šūkiai - santrauka, žymi frazė',
        
        // 4. Neapibrėžtumas
        'obfuscation' => 'Neapibrėžtumas - sąmoningas neaiškios kalbos vartojimas',
        
        // 5. Apeliavimas į autoritetą
        'appealToAuthority' => 'Apeliavimas į autoritetą - garsi asmenybė remia poziciją',
        
        // 6. Mojavimas vėliava
        'flagWaving' => 'Mojavimas vėliava - patriotizmu pagrįsti argumentai',
        
        // 7. Sekimas iš paskos
        'bandwagon' => 'Bandwagon - apeliavimas į bandos jausmą',
        
        // 8. Abejojimas
        'doubt' => 'Abejojimas - grupės/asmens patikimumo kvestionavimas',
        'smears' => 'Šmeižtas - reputacijos kenkimas',
        
        // 9. Reductio ad hitlerum
        'reductioAdHitlerum' => 'Reductio ad hitlerum - lyginimas su nekenčiamomis grupėmis',
        
        // 10. Pakartojimas
        'repetition' => 'Pakartojimas - tos pačios žinutės kartojimas',
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
        'distrustOfLithuanianInstitutions' => 'Nepasitikėjimas Lietuvos institucijomis',
        'natoDistrust' => 'Pasitikėjimo NATO mažinimas',
    ],

];