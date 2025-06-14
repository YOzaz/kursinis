@extends('layout')

@section('title', __('messages.legal_information'))

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-balance-scale me-2"></i>{{ __('messages.legal_information') }}</h1>
                    <p class="text-muted mb-0">{{ __('messages.data_usage_privacy_responsibility') }}</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('messages.back') }}
                </a>
            </div>

            <!-- Bendroji informacija -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle me-2"></i>{{ __('messages.general_information') }}</h3>
                </div>
                <div class="card-body">
                    <p>
                        {{ __('messages.legal_general_desc') }}
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-university text-primary me-2"></i>{{ __('messages.institution') }}</h6>
                            <p>{!! __('messages.vu_mif_address') !!}</p>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-calendar text-success me-2"></i>{{ __('messages.creation_date') }}</h6>
                            <p>2025 m.<br>
                            {{ __('messages.thesis_work') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duomenų naudojimas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-database me-2"></i>{{ __('messages.data_usage_security') }}</h3>
                </div>
                <div class="card-body">
                    <h6>{{ __('messages.what_data_processed') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.texts_for_analysis') }}:</strong> {{ __('messages.texts_sent_to_ai') }}</li>
                        <li><strong>{{ __('messages.analysis_results_legal') }}:</strong> {{ __('messages.ai_responses_saved') }}</li>
                        <li><strong>{{ __('messages.metadata') }}:</strong> {{ __('messages.analysis_time_models_stats') }}</li>
                        <li><strong>{{ __('messages.expert_annotations_legal') }}:</strong> {{ __('messages.if_provided_used_metrics') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('messages.how_data_protected') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.https_encryption') }}:</strong> {{ __('messages.all_data_transfers_ssl') }}</li>
                        <li><strong>{{ __('messages.university_infrastructure') }}:</strong> {{ __('messages.servers_under_vu_control') }}</li>
                        <li><strong>{{ __('messages.access_control') }}:</strong> {{ __('messages.limited_db_access') }}</li>
                        <li><strong>{{ __('messages.api_security') }}:</strong> {{ __('messages.official_api_keys_limited') }}</li>
                    </ul>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>{{ __('messages.important_to_know') }}:</h6>
                        <p class="mb-0">
                            {{ __('messages.texts_sent_to_third_party') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- GDPR ir privatumas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-shield-alt me-2"></i>{{ __('messages.gdpr_privacy_protection') }}</h3>
                </div>
                <div class="card-body">
                    <h6>{{ __('messages.your_rights') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.access_right') }}:</strong> {{ __('messages.request_info_stored_data') }}</li>
                        <li><strong>{{ __('messages.rectification_right') }}:</strong> {{ __('messages.request_correct_incorrect_data') }}</li>
                        <li><strong>{{ __('messages.erasure_right') }}:</strong> {{ __('messages.request_delete_data') }}</li>
                        <li><strong>{{ __('messages.portability_right') }}:</strong> {{ __('messages.get_data_structured_format') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('messages.data_retention_period') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.analysis_results_retention') }}:</strong> {{ __('messages.stored_until_system_end') }}</li>
                        <li><strong>{{ __('messages.log_records') }}:</strong> {{ __('messages.30_day_retention') }}</li>
                        <li><strong>{{ __('messages.error_information') }}:</strong> {{ __('messages.deleted_after_resolution') }}</li>
                    </ul>

                    <div class="alert alert-info">
                        <p class="mb-0">
                            <strong>{{ __('messages.data_controller') }}:</strong> {{ __('messages.data_controller_vu') }}<br>
                            <strong>{{ __('messages.contact_legal') }}:</strong> marijus.planciunas@mif.stud.vu.lt
                        </p>
                    </div>
                </div>
            </div>

            <!-- Atsakomybės apribojimas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-circle me-2"></i>{{ __('messages.liability_disclaimer') }}</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h6><strong>{{ __('messages.important_legal') }}:</strong></h6>
                        <p class="mb-0">
                            {{ __('messages.academic_product_not_commercial') }}
                        </p>
                    </div>

                    <h6>{{ __('messages.system_limitations') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.accuracy') }}:</strong> {{ __('messages.ai_not_100_accurate') }}</li>
                        <li><strong>{{ __('messages.bias') }}:</strong> {{ __('messages.models_may_have_bias') }}</li>
                        <li><strong>{{ __('messages.context') }}:</strong> {{ __('messages.ai_may_not_understand_context') }}</li>
                        <li><strong>{{ __('messages.stability') }}:</strong> {{ __('messages.prototype_may_be_unstable') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('messages.not_responsible_for') }}</h6>
                    <ul>
                        <li>{{ __('messages.incorrect_ai_results') }}</li>
                        <li>{{ __('messages.system_failure_data_loss') }}</li>
                        <li>{{ __('messages.third_party_actions') }}</li>
                        <li>{{ __('messages.decisions_based_on_results') }}</li>
                    </ul>
                </div>
            </div>

            <!-- Akademinės etikos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap me-2"></i>{{ __('messages.academic_ethics_principles') }}</h3>
                </div>
                <div class="card-body">
                    <h6>{{ __('messages.research_ethics') }}</h6>
                    <ul>
                        <li><strong>{{ __('messages.transparency') }}:</strong> {{ __('messages.methodology_code_open') }}</li>
                        <li><strong>{{ __('messages.objectivity') }}:</strong> {{ __('messages.effort_avoid_bias') }}</li>
                        <li><strong>{{ __('messages.adherence') }}:</strong> {{ __('messages.following_atspara_methodology') }}</li>
                        <li><strong>{{ __('messages.academic_freedom') }}:</strong> {{ __('messages.results_presented_unchanged') }}</li>
                    </ul>

                    <h6 class="mt-4">{{ __('messages.citation_usage') }}</h6>
                    <ul>
                        <li>{{ __('messages.can_use_for_academic') }}</li>
                        <li>{{ __('messages.please_cite_atspara') }}</li>
                        <li>{{ __('messages.commercial_use_permission') }}</li>
                    </ul>

                    <div class="alert alert-info">
                        <h6>{{ __('messages.recommended_citation') }}:</h6>
                        <p class="mb-0 font-monospace small">
                            {{ __('messages.citation_text') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Kontaktai -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-envelope me-2"></i>{{ __('messages.question_resolution') }}</h3>
                </div>
                <div class="card-body">
                    <p>{{ __('messages.questions_about_data') }}</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>{{ __('messages.technical_contact') }}</h6>
                            <p>
                                <strong>Marijus Plančiūnas</strong><br>
                                <a href="mailto:marijus.planciunas@mif.stud.vu.lt">
                                    marijus.planciunas@mif.stud.vu.lt
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>{{ __('messages.academic_supervisor') }}</h6>
                            <p>
                                <strong>Prof. Dr. Darius Plikynas</strong><br>
                                <a href="mailto:darius.plikynas@mif.vu.lt">
                                    darius.plikynas@mif.vu.lt
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paskutinis atnaujinimas -->
            <div class="text-center text-muted">
                <small>
                    <i class="fas fa-calendar-alt me-1"></i>
                    {{ __('messages.last_updated') }}: 2025-06-06
                </small>
            </div>
        </div>
    </div>
</div>
@endsection