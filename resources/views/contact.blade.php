@extends('layout')

@section('title', __('messages.contacts'))

@section('content')
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-envelope me-2"></i>{{ __('messages.contacts') }}</h1>
                    <p class="text-muted mb-0">{{ __('messages.contact_system_creators') }}</p>
                </div>
                <a href="{{ route('home') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> {{ __('messages.back') }}
                </a>
            </div>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-graduate me-2"></i>{{ __('messages.thesis_author') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Marijus Plančiūnas</h6>
                            <p class="text-muted">{{ __('messages.thesis_author_desc') }}</p>
                            
                            <div class="contact-info">
                                <div class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:marijus.planciunas@mif.stud.vu.lt">
                                        marijus.planciunas@mif.stud.vu.lt
                                    </a>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-university text-success me-2"></i>
                                    Vilniaus universitetas, MIF
                                </div>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <strong>{{ __('messages.responsible_for') }}:</strong><br>
                                • {{ __('messages.system_architecture_development') }}<br>
                                • {{ __('messages.ai_model_integration') }}<br>
                                • {{ __('messages.risen_methodology_implementation') }}<br>
                                • {{ __('messages.technical_issues_bug_fixes') }}
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chalkboard-teacher me-2"></i>{{ __('messages.scientific_supervisor') }}
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6>Prof. Dr. Darius Plikynas</h6>
                            <p class="text-muted">{{ __('messages.data_science_dept') }}</p>
                            
                            <div class="contact-info">
                                <div class="mb-2">
                                    <i class="fas fa-envelope text-primary me-2"></i>
                                    <a href="mailto:darius.plikynas@mif.vu.lt">
                                        darius.plikynas@mif.vu.lt
                                    </a>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-university text-success me-2"></i>
                                    Vilniaus universitetas, MIF
                                </div>
                            </div>
                            
                            <hr>
                            <small class="text-muted">
                                <strong>{{ __('messages.responsible_for') }}:</strong><br>
                                • {{ __('messages.scientific_methodology') }}<br>
                                • {{ __('messages.atspara_project_coordination') }}<br>
                                • {{ __('messages.academic_consultations') }}<br>
                                • {{ __('messages.results_evaluation') }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>{{ __('messages.atspara_project') }}
                    </h5>
                </div>
                <div class="card-body">
                    <p>
                        {{ __('messages.atspara_project_desc') }}
                    </p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="fas fa-globe text-primary me-2"></i>{{ __('messages.project_website') }}</h6>
                            <a href="https://www.atspara.mif.vu.lt/" target="_blank" class="btn btn-outline-primary mb-3">
                                <i class="fas fa-external-link-alt me-1"></i>atspara.mif.vu.lt
                            </a>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-database text-success me-2"></i>{{ __('messages.data_sources') }}</h6>
                            <p class="small text-muted">
                                {{ __('messages.annotation_methodology_desc') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>{{ __('messages.academic_research') }}
                    </h5>
                </div>
                <div class="card-body">
                    <p>{{ __('messages.academic_research_desc') }}</p>
                    
                    <ul>
                        <li><strong>{{ __('messages.research_methodology') }}</strong> {{ __('messages.research_methodology_desc') }}</li>
                        <li><strong>{{ __('messages.results_interpretation') }}</strong> {{ __('messages.results_interpretation_desc') }}</li>
                        <li><strong>{{ __('messages.technical_system_operation') }}</strong> {{ __('messages.technical_system_operation_desc') }}</li>
                        <li><strong>{{ __('messages.data_usage_research') }}</strong> {{ __('messages.data_usage_research_desc') }}</li>
                    </ul>
                    
                    <p>{{ __('messages.contact_email_detailed') }}</p>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection