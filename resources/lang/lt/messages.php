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
];