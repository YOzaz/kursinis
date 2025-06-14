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
    'analysis' => 'Analysis',
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
    
    // Dashboard Performance Metrics
    'f1_score' => 'F1 Score',
    'precision' => 'Precision',
    'recall' => 'Recall',
    'speed' => 'Speed',
    'propaganda_detection' => 'Propaganda Detection',
    'score' => 'Score',
    'texts' => 'texts',
    'no_performance_data' => 'No performance data',
    'run_analysis_to_start' => 'Run analysis to see metrics',
    'start_analysis_button' => 'Start Analysis',
    'model_rating' => 'Model Rating',
    'no_rating_data' => 'No rating data',
    'no_analyses' => 'No analyses',
    
    // Analysis List Additional
    'all_completed_analyses' => 'All completed propaganda analyses',
    'link' => 'Link',
    'view' => 'View',
    'status_label' => 'Status',
    'delete' => 'Delete',
    'delete_analysis_confirm' => 'Delete analysis',
    'analysis_cancelled' => 'Analysis cancelled',
    'no_results_found' => 'No results found',
    'try_changing_filters' => 'Try changing your filters...',
    
    // System Usage Instructions
    'how_to_use_system' => 'How to use the system',
    'system_usage_instructions' => [
        'step1' => 'Prepare a JSON file with texts for analysis. The file can contain expert annotations (for measuring AI accuracy) or be without them.',
        'step2' => 'Upload the file and select AI models you want to use for propaganda detection.',
        'step3' => 'The system will process each text with selected models and identify propaganda techniques.',
        'step4' => 'After completion, you can view detailed results, compare model performance, and export data.',
    ],
    
    // RISEN Prompt
    'risen_prompt_editor' => 'RISEN Prompt Editor',
    'modify_risen_prompt' => 'Modify RISEN prompt methodology sections',
    'role' => 'Role',
    'instructions' => 'Instructions',
    'steps' => 'Steps',
    'end_goal' => 'End Goal',
    'narrowing' => 'Narrowing',
    
    // Analysis Form
    'analysis_information' => 'Analysis Information',
    'analysis_type' => 'Analysis Type',
    'created_at' => 'Created At',
    'completed_at' => 'Completed At',
    'error_messages' => 'Error Messages',
    'analysis_results' => 'Analysis Results',
    
    // Tooltips and Help Text
    'f1_score_tooltip' => 'Harmonic mean of precision and recall. Higher value indicates better overall performance.',
    'precision_tooltip' => 'Percentage of correctly identified propaganda among all identified cases. High precision means fewer false positives.',
    'recall_tooltip' => 'Percentage of actual propaganda cases that were correctly identified. High recall means fewer false negatives.',
    'speed_tooltip' => 'Average time to analyze one text. Lower values indicate faster processing.',
    'propaganda_detection_tooltip' => 'Percentage of texts where propaganda was detected. Calculated only from texts containing propaganda.',
    'score_tooltip' => 'Overall model rating based on F1 score, precision, recall, and processing speed.',
    
    // DataTable Localization
    'datatable' => [
        'search' => 'Search:',
        'lengthMenu' => 'Show _MENU_ entries',
        'info' => 'Showing _START_ to _END_ of _TOTAL_ entries',
        'infoEmpty' => 'Showing 0 to 0 of 0 entries',
        'infoFiltered' => '(filtered from _MAX_ total entries)',
        'loadingRecords' => 'Loading...',
        'processing' => 'Processing...',
        'zeroRecords' => 'No matching records found',
        'emptyTable' => 'No data available in table',
        'paginate' => [
            'first' => 'First',
            'previous' => 'Previous',
            'next' => 'Next',
            'last' => 'Last'
        ],
        'aria' => [
            'sortAscending' => ': activate to sort column ascending',
            'sortDescending' => ': activate to sort column descending'
        ]
    ],
    
    // Chart Labels
    'average_f1_by_model' => 'Average F1 Score by Model',
    'model_performance_radar' => 'Model Performance Comparison',
    'techniques_by_frequency' => 'Propaganda Techniques by Frequency',
    'model_performance_metrics' => 'Model Performance Metrics',
    'most_detected_techniques' => 'Most Frequently Detected Techniques',
    'analyses_count_last_30_days' => 'Analyses Count in Last 30 Days',
    
    // Progress Messages
    'initializing_analysis' => 'Initializing analysis...',
    'processing_file' => 'Processing file',
    'analyzing_with_model' => 'Analyzing with :model',
    'analysis_complete' => 'Analysis complete',
    'calculating_metrics' => 'Calculating metrics...',
    
    // File Upload
    'json_file_required' => 'JSON file (required)',
    'upload_instructions' => 'Upload a JSON file containing texts for analysis',
    'file_format_info' => 'File can include expert annotations or contain only texts',
    
    // Actions
    'actions' => 'Actions',
    'export' => 'Export',
    'statistics' => 'Statistics',
    
    // Empty States
    'no_data' => 'No data',
    'no_analyses_yet' => 'No analyses yet',
    'start_first_analysis' => 'Start your first analysis to see results here',
    
    // Confirmation Messages
    'confirm_delete' => 'Are you sure you want to delete this analysis?',
    'confirm_cancel' => 'Are you sure you want to cancel this analysis?',
    
    // Success Messages
    'analysis_started' => 'Analysis started successfully',
    'analysis_deleted' => 'Analysis deleted successfully',
    'data_exported' => 'Data exported successfully',
    
    // Propaganda Detection Badges
    'true_positive_propaganda' => 'Correctly detected propaganda',
    'false_positive_propaganda' => 'Incorrectly detected propaganda',
    'true_negative_propaganda' => 'Correctly not detected propaganda',
    'false_negative_propaganda' => 'Incorrectly not detected propaganda',
    
    // Analysis Form Additional
    'data_types' => 'Data Types',
    'with_expert_annotations_desc' => 'With expert annotations',
    'calculates_region_metrics' => 'calculates region-level metrics (precision, recall, F1)',
    'without_annotations' => 'Without annotations',
    'ai_analysis_only' => 'AI analysis only',
    'results' => 'Results',
    '11_atspara_techniques' => '11 ATSPARA propaganda techniques',
    'csv_json_export' => 'CSV/JSON export',
    'real_time_progress' => 'Real-time progress',
    'detailed_documentation' => 'Detailed documentation',
    'upload_json_and_select_models' => 'Upload JSON file (with or without expert annotations) and select LLM models for analysis.',
    'json_upload_tooltip' => 'Upload a JSON file with texts. If you have expert annotations (from Label Studio), the system will calculate comparison metrics. If not - AI analysis only.',
    'specific_json_format_required' => 'Specific JSON format required.',
    'view_format_specification' => 'View format specification',
    'supported_formats_json' => 'Supported formats: .json (up to 100MB each). You can select multiple files.',
    'unknown_status' => 'Unknown status',
    'risen_prompt_configuration' => 'RISEN Prompt Configuration',
    'risen_methodology_tooltip' => 'RISEN methodology: Role, Instructions, Situation, Execution, Narrowing. Standard ATSPARA prompt optimized for Lithuanian propaganda analysis.',
    'standard_atspara_risen_prompt' => 'Standard ATSPARA RISEN prompt',
    'professionally_created_risen' => 'Professionally created according to RISEN methodology with 21 propaganda techniques',
    'custom_risen_prompt' => 'Custom RISEN prompt',
    'modify_any_risen_part' => 'Modify any RISEN part according to your needs',
    'risen_atspara_prompt' => 'RISEN ATSPARA Prompt',
    'view_full_prompt' => 'View full prompt',
    'prompt_includes_11_techniques' => 'This prompt includes 11 ATSPARA propaganda techniques and JSON format specification',
    'what_is_ai_model_in_task' => 'What is the AI model in this task?',
    'what_exactly_to_do' => 'What exactly to do?',
    'in_what_context' => 'In what context?',
    'how_to_perform_task' => 'How to perform the task?',
    'what_format_response_needed' => 'What format response is needed?',
    'reset_to_default' => 'Reset to default',
    'atspara_techniques_added_automatically' => 'ATSPARA techniques and JSON format are added automatically',
    'analysis_name_placeholder' => 'E.g.: Propaganda analysis 2025-01',
    'analysis_description_placeholder' => 'Short analysis description',
    'standard_atspara_prompt' => 'Standard ATSPARA prompt',
    'copy_to_custom_prompt' => 'Copy to custom prompt',
    'risen_prompt_builder' => 'RISEN Prompt Builder',
    'risen_methodology' => 'RISEN methodology',
    'risen_methodology_description' => 'Role, Instructions, Situation, Execution, Narrowing - structured prompt creation method',
    'situation' => 'Situation',
    'execution' => 'Execution',
    'create_prompt' => 'Create prompt',
    
    // Analysis List Additional
    'analysis_types' => 'Analysis Types',
    'without_expert_annotations' => 'Without expert annotations',
    'with_risen_prompt' => 'With RISEN prompt',
    'repeated' => 'Repeated',
    'system_compares_ai_expert' => 'system compares AI and expert results, calculates P/R/F1 metrics',
    'only_ai_analyzes' => 'only AI models analyze texts according to ATSPARA methodology',
    'uses_modified_prompt' => 'uses modified prompt for specific needs',
    'repeats_existing_analysis' => 'repeats existing analysis with different models or prompts',
    'search' => 'Search',
    'search_by_name_or_id' => 'Search by name or ID...',
    'all_statuses' => 'All statuses',
    'type' => 'Type',
    'all_types' => 'All types',
    'standard' => 'Standard',
    'clear' => 'Clear',
    'repeated_analysis' => 'Repeated Analysis',
    'analysis_completed_successfully' => 'Analysis completed successfully',
    'analysis_in_progress' => 'Analysis in progress...',
    'analysis_failed' => 'Analysis failed',
    'experiment' => 'Experiment',
    'confirm_delete_analysis' => 'Are you sure you want to delete this analysis? This action cannot be undone.',
    'no_analyses_performed_yet' => 'No propaganda analyses have been performed yet.',
    'start_new_analysis_by_uploading' => 'Start a new analysis by uploading a JSON file with texts.',
];