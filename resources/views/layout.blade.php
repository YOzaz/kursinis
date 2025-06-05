<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Propagandos analizės sistema')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: #0056b3;
            background-color: #e9ecef;
        }
        
        .upload-area.dragover {
            border-color: #28a745;
            background-color: #d4edda;
        }
        
        .progress-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 8px solid #e9ecef;
            border-top: 8px solid #007bff;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-badge {
            font-size: 1.1em;
            padding: 8px 16px;
        }
        
        .model-checkbox {
            margin: 10px 0;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .model-checkbox:hover {
            background-color: #f8f9fa;
        }
        
        .model-checkbox input:checked + label {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-shield-alt me-2"></i>
                Propagandos analizės sistema
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}" title="Sistemos pagrindinis puslapis ir statistikos">
                            <i class="fas fa-home me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('create') ? 'active' : '' }}" href="{{ route('create') }}" title="Sukurti naują analizę">
                            <i class="fas fa-plus-circle me-1"></i>Nauja analizė
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('analyses.*') ? 'active' : '' }}" href="{{ route('analyses.index') }}" title="Visų analizių sąrašas ir rezultatai">
                            <i class="fas fa-list me-1"></i>Analizės
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('mission-control') ? 'active' : '' }}" href="{{ route('mission-control') }}" title="Sistemos monitoringas ir log'ai">
                            <i class="fas fa-satellite-dish me-1"></i>Mission Control
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-1"></i>Sistema
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="settingsDropdown">
                            <li><a class="dropdown-item" href="{{ route('settings.index') }}">
                                <i class="fas fa-sliders-h me-2"></i>Nustatymai
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('help.index') }}">
                                <i class="fas fa-question-circle me-2"></i>Pagalba
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('help.faq') }}">
                                <i class="fas fa-info-circle me-2"></i>FAQ
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="{{ route('contact') }}">
                                <i class="fas fa-envelope me-2"></i>Kontaktai
                            </a></li>
                            <li><a class="dropdown-item" href="{{ route('legal') }}">
                                <i class="fas fa-balance-scale me-2"></i>Teisinė info
                            </a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user me-1"></i>{{ session('username', 'Vartotojas') }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger" title="Atsijungti iš sistemos">
                                        <i class="fas fa-sign-out-alt me-2"></i>Atsijungti
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-8">
                    <p class="mb-1">
                        <i class="fas fa-university me-2"></i>
                        <strong>Vilniaus universitetas</strong> - Propagandos ir dezinformacijos analizės sistema
                    </p>
                    <small class="text-muted">
                        Kursinis darbas | Paremtas ATSPARA projektu, 2025
                    </small>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="footer-links">
                        <a href="{{ route('contact') }}" class="text-light me-3">
                            <i class="fas fa-envelope me-1"></i>Kontaktai
                        </a>
                        <a href="{{ route('legal') }}" class="text-light">
                            <i class="fas fa-balance-scale me-1"></i>Teisinė info
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <!-- Global tooltip initialization -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips globally
        function initializeTooltips() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
        
        // Initialize on page load
        initializeTooltips();
        
        // Re-initialize tooltips when content is dynamically added
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if any added nodes contain tooltip triggers
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            const tooltipTriggers = node.querySelectorAll('[data-bs-toggle="tooltip"]');
                            tooltipTriggers.forEach(function(trigger) {
                                if (!trigger.hasAttribute('data-tooltip-initialized')) {
                                    new bootstrap.Tooltip(trigger);
                                    trigger.setAttribute('data-tooltip-initialized', 'true');
                                }
                            });
                        }
                    });
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });
    </script>
    
    @yield('scripts')
</body>
</html>