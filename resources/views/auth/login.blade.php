{{-- resources/views/auth/login.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-SHOP || Login</title>

    <!-- Bootstrap 4 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- jQuery Validation -->
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .login-card { border-radius: 16px; overflow: hidden; background: #fff; }
        .brand-title { color: #667eea !important; font-size: 2rem; letter-spacing: 1px; }
        .bg-login-image {
            background: url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=2070&q=80') center/cover;
            min-height: 550px;
            position: relative;
        }
        .bg-login-image::before {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(102,126,234,.85), rgba(118,75,162,.85));
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none; border-radius: 8px; padding: 12px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5568d3, #653a8a);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102,126,234,.3);
        }

        /* Validation */
        .input-group.error .form-control,
        .input-group.error .input-group-text { border-color: #e74c3c; }
        .input-group.success .form-control,
        .input-group.success .input-group-text { border-color: #27ae60; }
        label.error { color: #e74c3c; font-size: .875rem; margin-top: .5rem; display: block; }

        .toast-laravel {
            min-width: 320px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,.2);
            overflow: hidden;
            transform: translateX(120%);
            transition: transform .5s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            margin-bottom: 12px;
        }
        .toast-laravel.show { transform: translateX(0); }
        .toast-header {
            padding: 12px 16px;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
        }
        .toast-header.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
        .toast-header.error   { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .toast-header.info    { background: linear-gradient(135deg, #3498db, #2980b9); }
        .toast-header.warning { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .toast-icon { font-size: 1.4rem; margin-right: 10px; }
        .toast-body {
            padding: 16px;
            display: flex;
            align-items: center;
            color: #2c3e50;
        }
        .toast-body i { margin-right: 12px; font-size: 1.5rem; }
        .close-toast {
            margin-left: auto;
            background: none;
            border: none;
            color: #fff;
            font-size: 1.4rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-lg-9 col-md-10">
            <div class="card login-card shadow-lg border-0">
                <div class="row no-gutters">
                    <div class="col-lg-5 d-none d-lg-block bg-login-image"></div>
                    <div class="col-lg-7">
                        <div class="card-body p-4 p-sm-5">
                            <div class="text-center mb-4">
                                <div class="brand-title font-weight-bold">E-SHOP</div>
                                <p class="text-muted small">Sign in to continue to your account</p>
                            </div>

                            <form id="loginForm" method="POST" action="{{ route('login') }}" novalidate>
                                @csrf
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" name="email" class="form-control"
                                               placeholder="Email address" value="{{ old('email') }}" autocomplete="email" autofocus>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" name="password" class="form-control"
                                               placeholder="Password" autocomplete="current-password">
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                        <label class="form-check-label small" for="remember">Remember me</label>
                                    </div>
                                    <a class="small" href="{{ route('password.request') }}">Forgot password?</a>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">Login</button>
                            </form>

                            <!-- <hr class="my-4">
                            <div class="text-center">
                                <p class="small text-muted mb-3">Or sign in with</p>
                                <a href="#" class="btn btn-outline-primary btn-sm mr-2"><i class="fab fa-google"></i> Google</a>
                                <a href="#" class="btn btn-outline-primary btn-sm"><i class="fab fa-facebook-f"></i> Facebook</a>
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    // jQuery Validation (optional enhancement)
    $("#loginForm").validate({
        rules: {
            email: { required: true, email: true },
            password: { required: true, minlength: 3 }
        },
        messages: {
            email: { required: "Email is required", email: "Enter a valid email" },
            password: { required: "Password is required", minlength: "At least 3 characters" }
        },
        errorPlacement: (error, el) => error.addClass("mt-2 text-danger").insertAfter(el.closest(".input-group")),
        highlight: el => $(el).closest(".input-group").addClass("error"),
        unhighlight: el => $(el).closest(".input-group").removeClass("error").addClass("success")
    });
});
</script>
</body>
</html>