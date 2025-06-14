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
    
    // Additional Navigation and Layout
    'system' => 'System',
    'user' => 'User',
    'contacts' => 'Contacts',
    'legal_info' => 'Legal Info',
    'back' => 'Back',
    'back_to_dashboard' => 'Back to Dashboard',
    
    // Analysis List
    'analysis_list' => 'Analysis List',
    'new_analysis' => 'New Analysis',
    
    // System Settings
    'system_settings' => 'System Settings',
    
    // Dashboard Additional
    'average_time' => 'Average Time',
    'analysis_duration' => 'Analysis Duration',
    'model_performance_comparison' => 'Model Performance Comparison',
    'propaganda_techniques_distribution' => 'Propaganda Techniques Distribution',
    
    // Main Page
    'main_propaganda_analysis_system' => 'Main - Propaganda Analysis System',
    
    // Single Text Upload
    'single_text_analysis' => 'Single Text Analysis',
    'upload_single_text' => 'Upload Single Text',
    'text_input' => 'Text Input',
    'paste_or_type_text' => 'Paste or type your text here...',
    'analyze_text' => 'Analyze Text',
    'single_text_description' => 'Analyze a single text without expert annotations',
    
    // Mission Control
    'mission_control_title' => 'Mission Control - System-Wide AI Analysis Monitoring',
    'ai_analysis_mission_control' => 'AI ANALYSIS MISSION CONTROL',
    'system_wide_intelligence_status' => 'System-Wide Intelligence Processing Status',
    'back_to_dashboard' => 'Back to Dashboard',
    'view_analyses_list' => 'View analyses list',
    'create_new_analysis' => 'Create new analysis',
    'filter_by_job_id' => 'Filter by Job ID (optional)',
    'filter' => 'Filter',
    'show_all' => 'Show All',
    'force_refresh' => 'Force Refresh',
    'initializing_systems' => 'INITIALIZING SYSTEMS',
    'loading_system_status' => 'Loading system-wide operational status...',
    'live' => 'LIVE',
    'error' => 'ERROR',
    'total_jobs' => 'TOTAL JOBS',
    'active' => 'ACTIVE',
    'inactive' => 'INACTIVE',
    'queue' => 'QUEUE',
    'unique_texts' => 'UNIQUE TEXTS',
    'current_job_details' => 'Current Job Details',
    'job_id' => 'Job ID',
    'name' => 'Name',
    'progress' => 'Progress',
    'duration' => 'Duration',
    'queue_status' => 'Queue Status',
    'jobs_in_queue' => 'Jobs in Queue',
    'failed_jobs' => 'Failed Jobs',
    'workers' => 'Workers',
    'provider' => 'Provider',
    'total_analyses' => 'Total Analyses',
    'successful' => 'Successful',
    'pending' => 'Pending',
    'system_logs' => 'System Logs',
    'filtered' => 'Filtered',
    'system_wide' => 'System-wide',
    'filtered_view' => 'FILTERED VIEW',
    'showing_data_for_job' => 'Showing data for Job ID',
    'copy_to_clipboard' => 'Copy to clipboard',
    'log_message_copied' => 'LOG MESSAGE COPIED TO CLIPBOARD',
];