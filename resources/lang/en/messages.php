<?php

return [
    // Navigation and Layout
    'title' => 'Propaganda Analysis System',
    'home' => 'Home',
    'analyses' => 'Analyses',
    'help' => 'Help',
    'settings' => 'Settings',
    'login' => 'Login',
    'logout' => 'Logout',
    'dashboard' => 'Dashboard',
    'mission_control' => 'Mission Control',

    // Analysis Interface
    'analysis_details' => 'Analysis Details',
    'analysis_id' => 'Analysis ID',
    'text_upload' => 'Text Analysis Launch',
    'upload_json' => 'JSON file (with or without expert annotations)',
    'drag_drop_files' => 'Drag files here or click to select',
    'select_models' => 'LLM models for analysis',
    'start_analysis' => 'New Analysis',
    'analysis_name' => 'Analysis name (optional)',
    'analysis_description' => 'Description (optional)',
    
    // Model Selection
    'all_models' => 'All models (combined)',
    'all_models_explanation' => 'How "All models" mode works',
    'all_models_description' => 'Combines annotations from all models. Highlighted fragments show locations where at least one model detected propaganda techniques.',
    'consensus_info' => 'Annotations agreed upon by most models are marked with special indicators.',
    'consensus' => 'Consensus',
    'consensus_desc' => 'Majority of models agree',
    'partial_agreement' => 'Partial agreement',
    'partial_agreement_desc' => 'Multiple models agree',
    'single_model' => 'Single model',
    'single_model_desc' => 'Only one model detected',
    
    // Annotations
    'expert_annotations' => 'Expert annotations',
    'ai_annotations' => 'AI annotations',
    'show_annotations' => 'Show annotations',
    'model_agreement' => 'Model agreement',
    'models' => 'Models',
    'majority_agrees' => 'Majority of models agree',
    'only_one_model' => 'Only one model detected',
    
    // Status and Results
    'status' => 'Status',
    'completed' => 'Completed',
    'failed' => 'Failed',
    'processing' => 'Processing',
    'pending' => 'Pending',
    'cancelled' => 'Cancelled',
    
    // Actions
    'download_json' => 'Download JSON',
    'download_csv' => 'Download CSV',
    'repeat_analysis' => 'Repeat Analysis',
    'back_to_list' => 'Back to List',
    'close' => 'Close',
    'cancel' => 'Cancel',
    'save' => 'Save',
    'loading' => 'Loading...',
    'refresh' => 'Refresh',
    
    // Statistics
    'analysis_statistics' => 'Analysis Statistics',
    'total_texts' => 'Total texts',
    'with_expert_annotations' => 'With expert annotations',
    'successful_analyses' => 'Successful analyses',
    'failed_analyses' => 'Failed analyses',
    
    // Errors and Messages
    'error_loading_annotations' => 'Error loading annotations',
    'no_annotations_found' => 'No expert annotations found for this text',
    'analysis_not_found' => 'Analysis not found',
    'file_upload_error' => 'File upload error',
    'no_models_selected' => 'Please select at least one model for analysis.',
    'no_files_selected' => 'Please select at least one JSON file for analysis.',
    
    // Model Status
    'model_status' => 'AI model connection status',
    'all_models_working' => 'All models working',
    'some_models_offline' => 'Some models unavailable',
    'no_models_available' => 'No models available',
    'models_online' => 'models online',
    'last_checked' => 'Last checked',
    
    // Dashboard
    'export_data' => 'Export Data',
    'dashboard_statistics' => 'Dashboard Statistics',
    'json_format' => 'JSON Format',
    'csv_format' => 'CSV Format',
    'structured_data' => 'Structured data for programs',
    'excel_spreadsheets' => 'Excel, spreadsheets',
    'export_info' => 'Exported: global statistics, model performance, propaganda techniques, chronological data',
    'total_analyses' => 'Total Analyses',
    'with_texts' => 'With :count texts',
    'completed_analyses' => 'Completed Analyses',
    'success_rate' => 'Success Rate',
    'avg_execution_time' => 'Average Execution Time',
    'minutes' => 'min',
    'texts_with_propaganda' => 'Texts with Propaganda',
    'detection_rate' => 'Detection Rate',
    'avg_f1_score' => 'Average F1 Score',
    'propaganda_texts_only' => 'Propaganda texts only',
    'configured_models' => 'Configured Models',
    'claude_gpt_gemini' => 'Claude Opus/Sonnet 4, GPT-4o/4.1, Gemini Pro/Flash',
    
    // Model Performance
    'model_performance' => 'Model Performance',
    'model' => 'Model',
    'analyses_count' => 'Analyses',
    'success_rate_percent' => 'Success Rate',
    'avg_time' => 'Avg. Time',
    'last_used' => 'Last Used',
    'never' => 'Never',
    
    // Propaganda Techniques
    'propaganda_techniques' => 'Propaganda Techniques',
    'technique' => 'Technique',
    'frequency' => 'Frequency',
    'in_texts' => 'in texts',
    
    // Recent Activity
    'recent_activity' => 'Recent Activity',
    'recent_analyses' => 'Recent Analyses',
    'view_all' => 'View All',
    'view_details' => 'View Details',
    'ago' => 'ago',
    'just_now' => 'just now',
    
    // Time periods
    'seconds' => 'sec.',
    'minutes_short' => 'min.',
    'hours' => 'hrs.',
    'days' => 'd.',
    'weeks' => 'wks.',
    'months' => 'mo.',
    'years' => 'y.',
    
    // Analysis Types
    'standard_analysis' => 'Standard Analysis',
    'custom_prompt' => 'Custom Prompt',
    'repeated_analysis' => 'Repeated Analysis',
    
    // Language Switcher
    'language' => 'Language',
    'switch_to_english' => 'Switch to English',
    'switch_to_lithuanian' => 'Perjungti į lietuvių',
];