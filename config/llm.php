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
    | Duomenų šaltiniai:
    | - ATSPARA korpuso duomenys: https://www.atspara.mif.vu.lt/
    | - Klasifikavimo metodologija: Paulius Zaranka (paulius.zaranka@mif.vu.lt)
    |
    */

    'models' => [
        'claude-4' => [
            'api_key' => env('CLAUDE_API_KEY'),
            'base_url' => 'https://api.anthropic.com/v1',
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 4096,
            'temperature' => 0.1,
            'rate_limit' => env('CLAUDE_RATE_LIMIT', 50),
        ],
        
        'gemini-2.5-pro' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'model' => 'gemini-2.0-flash-exp',
            'max_tokens' => 4096,
            'temperature' => 0.1,
            'rate_limit' => env('GEMINI_RATE_LIMIT', 50),
        ],
        
        'gpt-4.1' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o',
            'max_tokens' => 4096,
            'temperature' => 0.1,
            'rate_limit' => env('OPENAI_RATE_LIMIT', 50),
        ],
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
    | Šios propagandos technikos apibrėžtos ATSPARA projekto tyrimuose
    | ir naudojamos Pauliaus Zarankos magistrinio darbo metodologijoje
    | lietuviško teksto analizei.
    |
    */

    'propaganda_techniques' => [
        'simplification' => 'Sudėtingų klausimų pernelyg paprastas pristatymas',
        'emotionalExpression' => 'Stiprių emocijų naudojimas racionalių argumentų vietoje',
        'uncertainty' => 'Neapibrėžtų teiginių naudojimas be įrodymų',
        'doubt' => 'Abejonių sėjimas patikimomis institucijomis ar faktais',
        'wavingTheFlag' => 'Patriotizmo išnaudojimas manipuliacijai',
        'reductioAdHitlerum' => 'Nepagrįsti lyginimai su totalitariniais režimais',
        'repetition' => 'Tų pačių teiginių kartojimas įtikimumui didinti',
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