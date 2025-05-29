<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prisijungimas - Propagandos analizės sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card login-card border-0">
                    <div class="card-header login-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>
                            Propagandos analizės sistema
                        </h3>
                        <p class="mb-0 mt-2">Saugus prisijungimas</p>
                    </div>
                    <div class="card-body p-5">
                        @if($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                @foreach($errors->all() as $error)
                                    {{ $error }}
                                @endforeach
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        @endif

                        <form method="POST" action="/login">
                            @csrf
                            <div class="mb-4">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-2"></i>Vartotojo vardas
                                </label>
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="username" 
                                       name="username" 
                                       value="{{ old('username') }}"
                                       required 
                                       autofocus>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-2"></i>Slaptažodis
                                </label>
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Prisijungti
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <h6 class="text-muted mb-3">Autorizuoti vartotojai</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <i class="fas fa-user-shield fa-2x text-primary mb-2"></i>
                                    <small class="d-block">Administratorius</small>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-user-graduate fa-2x text-success mb-2"></i>
                                    <small class="d-block">Magistrantas</small>
                                </div>
                                <div class="col-4">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-info mb-2"></i>
                                    <small class="d-block">Dėstytojas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">
                            <i class="fas fa-university me-1"></i>
                            Vilniaus universitetas | Kursinis darbas
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>