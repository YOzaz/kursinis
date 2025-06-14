<?php

return [
    // Navigation and Layout
    'title' => 'Propagandos analizės sistema',
    'home' => 'Pagrindinis',
    'analyses' => 'Analizės',
    'help' => 'Pagalba',
    'settings' => 'Nustatymai',
    'login' => 'Prisijungti',
    'logout' => 'Atsijungti',
    'dashboard' => 'Valdymo skydas',
    'mission_control' => 'Mission Control',

    // Analysis Interface
    'analysis' => 'Analizė',
    'analysis_details' => 'Analizės detalės',
    'analysis_id' => 'Analizės ID',
    'text_upload' => 'Tekstų analizės paleidimas',
    'upload_json' => 'JSON failas (su arba be ekspertų anotacijų)',
    'drag_drop_files' => 'Nuvilkite failus čia arba spustelėkite pasirinkti',
    'select_models' => 'LLM modeliai analizei',
    'start_analysis' => 'Nauja analizė',
    'analysis_name' => 'Analizės pavadinimas (neprivalomas)',
    'analysis_description' => 'Aprašymas (neprivalomas)',
    
    // Model Selection
    'all_models' => 'Visi modeliai (kombinuoti)',
    'all_models_explanation' => 'Kaip veikia "Visi modeliai" režimas',
    'all_models_description' => 'Kombinuojamos anotacijos iš visų modelių. Paryškinti fragmentai rodo vietas, kuriose bent vienas modelis aptiko propagandos technikas.',
    'consensus_info' => 'Anotacijos, su kuriomis sutinka dauguma modelių, pažymėtos specialiais indikatoriais.',
    'consensus' => 'Konsensusas',
    'consensus_desc' => 'Dauguma modelių sutinka',
    'partial_agreement' => 'Dalinis sutarimas',
    'partial_agreement_desc' => 'Keli modeliai sutinka',
    'single_model' => 'Vienas modelis',
    'single_model_desc' => 'Tik vienas modelis aptiko',
    
    // Annotations
    'expert_annotations' => 'Ekspertų anotacijos',
    'ai_annotations' => 'AI anotacijos',
    'show_annotations' => 'Rodyti anotacijas',
    'model_agreement' => 'Modelių sutarimas',
    'models' => 'Modeliai',
    'majority_agrees' => 'Dauguma modelių sutinka',
    'only_one_model' => 'Tik vienas modelis aptiko',
    
    // Status and Results
    'status' => 'Statusas',
    'completed' => 'Baigta',
    'failed' => 'Nepavyko',
    'processing' => 'Vykdoma',
    'pending' => 'Laukiama',
    'cancelled' => 'Atšaukta',
    
    // Actions
    'download_json' => 'Atsisiųsti JSON',
    'download_csv' => 'Atsisiųsti CSV',
    'repeat_analysis' => 'Pakartoti analizę',
    'back_to_list' => 'Grįžti į sąrašą',
    'close' => 'Uždaryti',
    'cancel' => 'Atšaukti',
    'save' => 'Išsaugoti',
    'loading' => 'Kraunama...',
    'refresh' => 'Atnaujinti',
    
    // Statistics
    'analysis_statistics' => 'Analizės statistika',
    'total_texts' => 'Iš viso tekstų',
    'with_expert_annotations' => 'Su ekspertų anotacijomis',
    'successful_analyses' => 'Sėkmingos analizės',
    'failed_analyses' => 'Nesėkmingos analizės',
    
    // Errors and Messages
    'error_loading_annotations' => 'Klaida kraunant anotacijas',
    'no_annotations_found' => 'Šiam tekstui nėra ekspertų anotacijų',
    'analysis_not_found' => 'Analizė nerasta',
    'file_upload_error' => 'Failo įkėlimo klaida',
    'no_models_selected' => 'Prašome pasirinkti bent vieną modelį analizei.',
    'no_files_selected' => 'Prašome pasirinkti bent vieną JSON failą analizei.',
    
    // Model Status
    'model_status' => 'AI modelių ryšio statusas',
    'all_models_working' => 'Visi modeliai veikia',
    'some_models_offline' => 'Kai kurie modeliai nedostupani',
    'no_models_available' => 'Nė vienas modelis nedostupas',
    'models_online' => 'modelių prisijungę',
    'last_checked' => 'Paskutinį kartą tikrinta',
    
    // Dashboard
    'export_data' => 'Eksportuoti duomenis',
    'dashboard_statistics' => 'Dashboard statistikos',
    'json_format' => 'JSON formatas',
    'csv_format' => 'CSV formatas',
    'structured_data' => 'Struktūrizuoti duomenys programoms',
    'excel_spreadsheets' => 'Excel, skaičiuoklės',
    'export_info' => 'Eksportuojama: globalios statistikos, modelių našumas, propagandos technikos, chronologiniai duomenys',
    'total_analyses' => 'Viso analizių',
    'with_texts' => 'Su :count tekstų',
    'completed_analyses' => 'Užbaigtos analizės',
    'success_rate' => 'Sėkmės dažnis',
    'avg_execution_time' => 'Vidutinis vykdymo laikas',
    'minutes' => 'min',
    'texts_with_propaganda' => 'Tekstai su propaganda',
    'detection_rate' => 'Aptikimo dažnis',
    'avg_f1_score' => 'Vidutinis F1 balas',
    'propaganda_texts_only' => 'Tik propagandos tekstai',
    'configured_models' => 'Konfigūruoti modeliai',
    'claude_gpt_gemini' => 'Claude Opus/Sonnet 4, GPT-4o/4.1, Gemini Pro/Flash',
    
    // Model Performance
    'model_performance' => 'Modelių našumas',
    'model' => 'Modelis',
    'analyses_count' => 'Analizių',
    'success_rate_percent' => 'Sėkmės dažnis',
    'avg_time' => 'Vid. laikas',
    'last_used' => 'Paskutinį kartą',
    'never' => 'Niekada',
    
    // Propaganda Techniques
    'propaganda_techniques' => 'Propagandos technikos',
    'technique' => 'Technika',
    'frequency' => 'Dažnumas',
    'in_texts' => 'tekstuose',
    
    // Recent Activity
    'recent_activity' => 'Paskutinė veikla',
    'recent_analyses' => 'Paskutinės analizės',
    'view_all' => 'Žiūrėti visas',
    'view_details' => 'Žiūrėti detales',
    'ago' => 'prieš',
    'just_now' => 'ką tik',
    
    // Time periods
    'seconds' => 'sek.',
    'minutes_short' => 'min.',
    'hours' => 'val.',
    'days' => 'd.',
    'weeks' => 'sav.',
    'months' => 'mėn.',
    'years' => 'm.',
    
    // Analysis Types
    'standard_analysis' => 'Standartinė analizė',
    'custom_prompt' => 'Custom prompt',
    'repeated_analysis' => 'Pakartotinė analizė',
    
    // Language Switcher
    'language' => 'Kalba',
    'switch_to_english' => 'Switch to English',
    'switch_to_lithuanian' => 'Perjungti į lietuvių',
    
    // Additional Navigation and Layout
    'system' => 'Sistema',
    'user' => 'Vartotojas',
    'contacts' => 'Kontaktai',
    'legal_info' => 'Teisinė info',
    'back' => 'Grįžti',
    'back_to_dashboard' => 'Grįžti į Dashboard',
    
    // Analysis List
    'analysis_list' => 'Analizių sąrašas',
    'new_analysis' => 'Nauja analizė',
    
    // System Settings
    'system_settings' => 'Sistemos nustatymai',
    
    // Dashboard Additional
    'average_time' => 'Vidutinis laikas',
    'analysis_duration' => 'Analizės trukmė',
    'model_performance_comparison' => 'Modelių našumo palyginimas',
    'propaganda_techniques_distribution' => 'Propagandos technikų pasiskirstymas',
    
    // Main Page
    'main_propaganda_analysis_system' => 'Pagrindinis - Propagandos analizės sistema',
    
    // Single Text Upload
    'single_text_analysis' => 'Vieno teksto analizė',
    'upload_single_text' => 'Įkelti vieną tekstą',
    'text_input' => 'Teksto įvestis',
    'paste_or_type_text' => 'Įklijuokite arba įveskite tekstą čia...',
    'analyze_text' => 'Analizuoti tekstą',
    'single_text_description' => 'Analizuoti vieną tekstą be ekspertų anotacijų',
    
    // Mission Control
    'mission_control_title' => 'Mission Control - Sistemos AI analizių monitoringas',
    'ai_analysis_mission_control' => 'AI ANALIZIŲ MISSION CONTROL',
    'system_wide_intelligence_status' => 'Sistemos intelekto apdorojimo būsena',
    'back_to_dashboard' => 'Grįžti į Dashboard',
    'view_analyses_list' => 'Peržiūrėti analizių sąrašą',
    'create_new_analysis' => 'Sukurti naują analizę',
    'filter_by_job_id' => 'Filtruoti pagal darbo ID (neprivaloma)',
    'filter' => 'Filtruoti',
    'show_all' => 'Rodyti visus',
    'force_refresh' => 'Priverstinis atnaujinimas',
    'initializing_systems' => 'INICIJUOJAMA SISTEMA',
    'loading_system_status' => 'Kraunama sistemos veiklos būsena...',
    'live' => 'TIESIOGIAI',
    'error' => 'KLAIDA',
    'total_jobs' => 'VISO DARBŲ',
    'active' => 'AKTYVUS',
    'inactive' => 'NEAKTYVUS',
    'queue' => 'EILĖ',
    'unique_texts' => 'UNIKALŪS TEKSTAI',
    'current_job_details' => 'Dabartinio darbo detalės',
    'job_id' => 'Darbo ID',
    'name' => 'Pavadinimas',
    'progress' => 'Progresas',
    'duration' => 'Trukmė',
    'queue_status' => 'Eilės būsena',
    'jobs_in_queue' => 'Darbai eilėje',
    'failed_jobs' => 'Nesėkmingi darbai',
    'workers' => 'Darbuotojai',
    'provider' => 'Tiekėjas',
    'total_analyses' => 'Viso analizių',
    'successful' => 'Sėkmingas',
    'pending' => 'Laukiantis',
    'system_logs' => 'Sistemos žurnalai',
    'filtered' => 'Filtruota',
    'system_wide' => 'Visos sistemos',
    'filtered_view' => 'FILTRUOTAS VAIZDAS',
    'showing_data_for_job' => 'Rodomi duomenys darbo ID',
    'copy_to_clipboard' => 'Kopijuoti į mainų sricitį',
    'log_message_copied' => 'ŽURNALO PRANEŠIMAS NUKOPIJUOTAS Į MAINŲ SRITĮ',
    
    // Dashboard Performance Metrics
    'f1_score' => 'F1 balas',
    'precision' => 'Tikslumas',
    'recall' => 'Atsaukimas',
    'speed' => 'Greitis',
    'propaganda_detection' => 'Propagandos aptikimas',
    'score' => 'Įvertis',
    'texts' => 'tekstų',
    'no_performance_data' => 'Nėra našumo duomenų',
    'run_analysis_to_start' => 'Paleiskite analizę, kad pamatytumėte metrikas',
    'start_analysis_button' => 'Pradėti analizę',
    'model_rating' => 'Modelių reitingas',
    'no_rating_data' => 'Nėra duomenų reitingui',
    'no_analyses' => 'Nėra analizių',
    
    // Analysis List Additional
    'all_completed_analyses' => 'Visos atliktos propagandos analizės',
    'link' => 'Nuoroda',
    'view' => 'Peržiūrėti',
    'status_label' => 'Statusas',
    'delete' => 'Ištrinti',
    'delete_analysis_confirm' => 'Ištrinti analizę',
    'analysis_cancelled' => 'Analizė atšaukta',
    'no_results_found' => 'Nerasta rezultatų',
    'try_changing_filters' => 'Pabandykite pakeisti filtrus...',
    
    // System Usage Instructions
    'how_to_use_system' => 'Kaip naudoti sistemą',
    'system_usage_instructions' => [
        'step1' => 'Paruoškite JSON failą su tekstais analizei. Failas gali turėti ekspertų anotacijas (AI tikslumo matavimui) arba būti be jų.',
        'step2' => 'Įkelkite failą ir pasirinkite AI modelius, kuriuos norite naudoti propagandos aptikimui.',
        'step3' => 'Sistema apdoros kiekvieną tekstą su pasirinktais modeliais ir identifikuos propagandos technikas.',
        'step4' => 'Baigus galėsite peržiūrėti detalius rezultatus, palyginti modelių našumą ir eksportuoti duomenis.',
    ],
    
    // RISEN Prompt
    'risen_prompt_editor' => 'RISEN Prompt Redaktorius',
    'modify_risen_prompt' => 'Modifikuokite RISEN prompt metodologijos sekcijas',
    'role' => 'Vaidmuo',
    'instructions' => 'Instrukcijos',
    'steps' => 'Žingsniai',
    'end_goal' => 'Galutinis tikslas',
    'narrowing' => 'Susiaurinimas',
    
    // Analysis Form
    'analysis_information' => 'Analizės informacija',
    'analysis_type' => 'Analizės tipas',
    'created_at' => 'Sukurta',
    'completed_at' => 'Užbaigta',
    'error_messages' => 'Klaidų pranešimai',
    'analysis_results' => 'Analizės rezultatai',
    
    // Tooltips and Help Text
    'f1_score_tooltip' => 'Tikslumo ir atsaukimo harmoninis vidurkis. Didesnė reikšmė rodo geresnį bendrą našumą.',
    'precision_tooltip' => 'Teisingai identifikuotos propagandos procentas tarp visų identifikuotų atvejų. Didelis tikslumas reiškia mažiau klaidingai teigiamų.',
    'recall_tooltip' => 'Faktinių propagandos atvejų, kurie buvo teisingai identifikuoti, procentas. Didelis atsaukimas reiškia mažiau klaidingai neigiamų.',
    'speed_tooltip' => 'Vidutinis laikas analizuoti vieną tekstą. Mažesnės reikšmės rodo greitesnį apdorojimą.',
    'propaganda_detection_tooltip' => 'Tekstų, kuriuose buvo aptikta propaganda, procentas. Skaičiuojama tik iš tekstų, turinčių propagandą.',
    'score_tooltip' => 'Bendras modelio įvertinimas pagrįstas F1 balu, tikslumu, atsaukimu ir apdorojimo greičiu.',
    
    // DataTable Localization
    'datatable' => [
        'search' => 'Ieškoti:',
        'lengthMenu' => 'Rodyti _MENU_ įrašų',
        'info' => 'Rodoma _START_ - _END_ iš _TOTAL_ įrašų',
        'infoEmpty' => 'Rodoma 0 - 0 iš 0 įrašų',
        'infoFiltered' => '(filtruota iš _MAX_ viso įrašų)',
        'loadingRecords' => 'Kraunama...',
        'processing' => 'Apdorojama...',
        'zeroRecords' => 'Nerasta atitinkančių įrašų',
        'emptyTable' => 'Nėra duomenų lentelėje',
        'paginate' => [
            'first' => 'Pirmas',
            'previous' => 'Ankstesnis',
            'next' => 'Kitas',
            'last' => 'Paskutinis'
        ],
        'aria' => [
            'sortAscending' => ': aktyvuoti rūšiavimui didėjančia tvarka',
            'sortDescending' => ': aktyvuoti rūšiavimui mažėjančia tvarka'
        ]
    ],
    
    // Chart Labels
    'average_f1_by_model' => 'Vidutinis F1 balas pagal modelį',
    'model_performance_radar' => 'Modelių našumo palyginimas',
    'techniques_by_frequency' => 'Propagandos technikos pagal dažnumą',
    'model_performance_metrics' => 'Modelių našumo metrikos',
    'most_detected_techniques' => 'Dažniausiai aptiktos technikos',
    'analyses_count_last_30_days' => 'Analizių skaičius per paskutines 30 dienų',
    
    // Progress Messages
    'initializing_analysis' => 'Inicijuojama analizė...',
    'processing_file' => 'Apdorojamas failas',
    'analyzing_with_model' => 'Analizuojama su :model',
    'analysis_complete' => 'Analizė baigta',
    'calculating_metrics' => 'Skaičiuojamos metrikos...',
    
    // File Upload
    'json_file_required' => 'JSON failas (privalomas)',
    'upload_instructions' => 'Įkelkite JSON failą su tekstais analizei',
    'file_format_info' => 'Failas gali turėti ekspertų anotacijas arba tik tekstus',
    
    // Actions
    'actions' => 'Veiksmai',
    'export' => 'Eksportuoti',
    'statistics' => 'Statistika',
    
    // Empty States
    'no_data' => 'Nėra duomenų',
    'no_analyses_yet' => 'Dar nėra analizių',
    'start_first_analysis' => 'Pradėkite pirmą analizę, kad matytumėte rezultatus',
    
    // Confirmation Messages
    'confirm_delete' => 'Ar tikrai norite ištrinti šią analizę?',
    'confirm_cancel' => 'Ar tikrai norite atšaukti šią analizę?',
    
    // Success Messages
    'analysis_started' => 'Analizė pradėta sėkmingai',
    'analysis_deleted' => 'Analizė ištrinta sėkmingai',
    'data_exported' => 'Duomenys eksportuoti sėkmingai',
    
    // Propaganda Detection Badges
    'true_positive_propaganda' => 'Teisingai rasta propaganda',
    'false_positive_propaganda' => 'Klaidingai rasta propaganda',
    'true_negative_propaganda' => 'Teisingai nerasta propaganda',
    'false_negative_propaganda' => 'Klaidingai nerasta propaganda',
    
    // Analysis Form Additional
    'data_types' => 'Duomenų tipai',
    'with_expert_annotations_desc' => 'Su ekspertų anotacijomis',
    'calculates_region_metrics' => 'skaičiuoja regionų lygio metrikas (precision, recall, F1)',
    'without_annotations' => 'Be anotacijų',
    'ai_analysis_only' => 'tik AI analizė',
    'results' => 'Rezultatai',
    '11_atspara_techniques' => '11 ATSPARA propagandos technikų',
    'csv_json_export' => 'CSV/JSON eksportas',
    'real_time_progress' => 'Realaus laiko progresas',
    'detailed_documentation' => 'Išsami dokumentacija',
    'upload_json_and_select_models' => 'Įkelkite JSON failą (su arba be ekspertų anotacijų) ir pasirinkite LLM modelius analizei.',
    'json_upload_tooltip' => 'Įkelkite JSON failą su tekstais. Jei turite ekspertų anotacijas (iš Label Studio), sistema apskaičiuos palyginimo metrikas. Jei ne - tik AI analizė.',
    'specific_json_format_required' => 'Reikalingas specifinis JSON formatas.',
    'view_format_specification' => 'Žiūrėti formato specifikaciją',
    'supported_formats_json' => 'Palaikomi formatai: .json (iki 100MB kiekvienam). Galite pasirinkti kelis failus.',
    'unknown_status' => 'Nežinomas statusas',
    'risen_prompt_configuration' => 'RISEN Prompt Konfigūracija',
    'risen_methodology_tooltip' => 'RISEN metodologija: Role, Instructions, Situation, Execution, Needle. Standartinis ATSPARA promptas optimizuotas lietuvių propagandos analizei.',
    'standard_atspara_risen_prompt' => 'Standartinis ATSPARA RISEN promptas',
    'professionally_created_risen' => 'Profesionaliai sukurtas pagal RISEN metodologiją su 21 propaganda technika',
    'custom_risen_prompt' => 'Pritaikytas RISEN promptas',
    'modify_any_risen_part' => 'Modifikuokite bet kurią RISEN dalį pagal poreikius',
    'risen_atspara_prompt' => 'RISEN ATSPARA Promptas',
    'view_full_prompt' => 'Peržiūrėti pilną prompt\'ą',
    'prompt_includes_11_techniques' => 'Šis promptas apima 11 ATSPARA propagandos technikas ir JSON formato specifikaciją',
    'what_is_ai_model_in_task' => 'Kas yra AI modelis šioje užduotyje?',
    'what_exactly_to_do' => 'Ką tiksliai daryti?',
    'in_what_context' => 'Kokiame kontekste?',
    'how_to_perform_task' => 'Kaip atlikti užduotį?',
    'what_format_response_needed' => 'Kokio formato atsakymo reikia?',
    'reset_to_default' => 'Atstatyti standartinį',
    'atspara_techniques_added_automatically' => 'Automatiškai pridedamos ATSPARA technikos ir JSON formatas',
    'analysis_name_placeholder' => 'Pvz.: Propagandos analizė 2025-01',
    'analysis_description_placeholder' => 'Trumpas analizės aprašymas',
    'standard_atspara_prompt' => 'Standartinis ATSPARA prompt\'as',
    'copy_to_custom_prompt' => 'Kopijuoti į custom prompt\'ą',
    'risen_prompt_builder' => 'RISEN Prompt\'o kūrėjas',
    'risen_methodology' => 'RISEN metodologija',
    'risen_methodology_description' => 'Role, Instructions, Situation, Execution, Needle - struktūrizuotas prompt\'o kūrimo metodas',
    'situation' => 'Situacija',
    'execution' => 'Vykdymas',
    'create_prompt' => 'Sukurti prompt\'ą',
    
    // Analysis List Additional
    'analysis_types' => 'Analizių tipai',
    'without_expert_annotations' => 'Be ekspertų anotacijų',
    'with_risen_prompt' => 'Su RISEN prompt\'u',
    'repeated' => 'Pakartotinės',
    'system_compares_ai_expert' => 'sistema palygina AI ir ekspertų rezultatus, apskaičiuoja P/R/F1 metrikas',
    'only_ai_analyzes' => 'tik AI modeliai analizuoja tekstus pagal ATSPARA metodologiją',
    'uses_modified_prompt' => 'naudoja modifikuotą prompt\'ą specifiniems poreikiams',
    'repeats_existing_analysis' => 'pakartoja esamą analizę su kitais modeliais ar prompt\'ais',
    'search' => 'Paieška',
    'search_by_name_or_id' => 'Ieškoti pagal pavadinimą arba ID...',
    'all_statuses' => 'Visi statusai',
    'type' => 'Tipas',
    'all_types' => 'Visi tipai',
    'standard' => 'Standartinė',
    'clear' => 'Valyti',
    'repeated_analysis' => 'Pakartotinė analizė',
    'analysis_completed_successfully' => 'Analizė baigta sėkmingai',
    'analysis_in_progress' => 'Analizė vykdoma...',
    'analysis_failed' => 'Analizė nepavyko',
    'experiment' => 'Eksperimentas',
    'confirm_delete_analysis' => 'Ar tikrai norite ištrinti šią analizę? Šis veiksmas negrįžtamas.',
    'no_analyses_performed_yet' => 'Dar nėra atlikta jokių propagandos analizių.',
    'start_new_analysis_by_uploading' => 'Pradėkite naują analizę įkeldami JSON failą su tekstais.',
];