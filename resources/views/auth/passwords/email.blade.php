{{-- resources/views/auth/passwords/email.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-SHOP || Forgot Password</title>

    <!-- Bootstrap + Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            align-items: center;
        }
        .forgot-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,.3);
            max-width: 900px;
            margin: auto;
        }
        .brand-title {
            color: #667eea !important;
            font-size: 2.4rem;
            letter-spacing: 1.5px;
            font-weight: 800;
        }
        .hero-image {
            background: url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=2070&q=80') center/cover;
            min-height: 500px;
            position: relative;
        }
        .hero-image::before {
            content: "Reset Password";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.8rem;
            font-weight: 900;
            text-shadow: 0 4px 15px rgba(0,0,0,.4);
        }
        .form-control {
            border-radius: 10px;
            padding: 0.8rem 1.2rem;
            border: 1px solid #ddd;
            font-size: 1rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,.25);
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: 0;
            border-radius: 10px 0 0 10px;
            color: #667eea;
        }
        .btn-reset {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all .3s;
        }
        .btn-reset:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px rgba(102,126,234,.4);
        }
        .back-link {
            color: #667eea;
            font-weight: 600;
            transition: color .2s;
        }
        .back-link:hover {
            color: #5568d3;
            text-decoration: none;
        }
        .alert-success {
            border-radius: 10px;
            background: rgba(39, 174, 96, 0.15);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        @media (max-width: 991px) {
            .hero-image { display: none; }
            .forgot-card { margin: 20px; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card forgot-card">
                <div class="row no-gutters">
                    <!-- Left: Hero -->
                    <div class="col-lg-5 d-none d-lg-block hero-image"></div>

                    <!-- Right: Form -->
                    <div class="col-lg-7">
                        <div class="p-5">
                            <div class="text-center mb-5">
                                <h1 class="brand-title">E-SHOP</h1>
                                <p class="h4 text-gray-800 mt-3">Forgot Your Password?</p>
                                <p class="text-muted">Enter your email and we'll send you a reset link instantly.</p>
                            </div>

                            <!-- Success Message -->
                            @if (session('status'))
                                <div class="alert alert-success text-center p-3">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    {{ session('status') }}
                                </div>
                            @endif

                            <form method="POST" action="{{ route('password.email') }}">
                                @csrf
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="fas fa-envelope"></i>
                                            </span>
                                        </div>
                                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                               placeholder="your@email.com" value="{{ old('email') }}" required autocomplete="email" autofocus>
                                        @error('email')
                                            <span class="invalid-feedback">
                                                <strong>{{ $message }}</strong>
                                            </span>
                                        @enderror
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-reset btn-block text-white">
                                    <i class="fas fa-paper-plane mr-2"></i>
                                    Send Reset Link
                                </button>
                            </form>

                            <hr class="my-4">
                            <div class="text-center">
                                <a href="{{ route('login') }}" class="back-link">
                                    <i class="fas fa-arrow-left mr-1"></i>
                                    Back to Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>