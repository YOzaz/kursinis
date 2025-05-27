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
            'base_url' => 'https://generativelanguage.googleapis.com/',
            'model' => 'gemini-2.0-flash',
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