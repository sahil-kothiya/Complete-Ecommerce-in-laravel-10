{{-- resources/views/auth/passwords/reset.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-SHOP || Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .reset-card { border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,.3); max-width: 960px; }
        .brand-title { color: #667eea !important; font-size: 2.4rem; letter-spacing: 1.5px; font-weight: 800; }
        .hero-image {
            background: url('https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=2070&q=80') center/cover;
            min-height: 520px; position: relative;
        }
        .hero-image::before {
            content: "New Password"; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(102,126,234,.92), rgba(118,75,162,.92));
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 3rem; font-weight: 900;
        }
        .form-control { border-radius: 10px; padding: 0.8rem 1.2rem; }
        .input-group-text { background: #f8f9fa; border-radius: 10px 0 0 10px; color: #667eea; }
        .btn-reset {
            background: linear-gradient(135deg, #667eea, #764ba2); border: none;
            border-radius: 10px; padding: 14px 40px; font-weight: 600; font-size: 1.1rem;
        }
        .btn-reset:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(102,126,234,.5); }
        .back-link { color: #667eea; font-weight: 600; }
        .back-link:hover { color: #5568d3; text-decoration: none; }

        /* Toaster */
        #laravelToast { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .toast-laravel {
            min-width: 340px; background: #fff; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,.2); overflow: hidden;
            transform: translateX(120%); transition: transform .5s ease;
        }
        .toast-laravel.show { transform: translateX(0); }
        .toast-header.success { background: linear-gradient(135deg, #27ae60, #1e8449); }
        .toast-header.error   { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .toast-body { padding: 16px; color: #2c3e50; display: flex; align-items: center; }
        .toast-body i { margin-right: 12px; font-size: 1.5rem; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-lg-10">
            <div class="card reset-card">
                <div class="row no-gutters">
                    <div class="col-lg-5 d-none d-lg-block hero-image"></div>
                    <div class="col-lg-7">
                        <div class="p-5">
                            <div class="text-center mb-5">
                                <h1 class="brand-title">E-SHOP</h1>
                                <p class="h4 text-gray-800 mt-3">Reset Your Password</p>
                                <p class="text-muted">Enter your new password below</p>
                            </div>

                            <form method="POST" action="{{ route('password.update') }}" id="resetForm">
                                @csrf
                                <input type="hidden" name="token" value="{{ $token }}">

                                <!-- Email -->
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        </div>
                                        <input type="email" name="email" class="form-control"
                                               placeholder="your@email.com"
                                               value="{{ $email ?? old('email') }}" required autofocus>
                                    </div>
                                </div>

                                <!-- Password -->
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" name="password" class="form-control"
                                               placeholder="New Password (8+ chars)" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text toggle-password" style="cursor:pointer">
                                                <i class="fas fa-eye"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirm -->
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        </div>
                                        <input type="password" name="password_confirmation" class="form-control"
                                               placeholder="Confirm New Password" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-reset btn-block text-white">
                                    <i class="fas fa-sync-alt mr-2"></i> Reset Password
                                </button>
                            </form>

                            <hr class="my-4">
                            <div class="text-center">
                                <a href="{{ route('login') }}" class="back-link">
                                    ← Back to Login
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toaster -->
<div id="laravelToast"></div>

<script>
$(function () {
    // Toaster Function
    function showToast(type, title, msg) {
        const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
        const html = `
            <div class="toast-laravel ${type}">
                <div class="toast-header ${type}">
                    <i class="fas fa-${icon} mr-2"></i>
                    <strong>${title}</strong>
                    <button type="button" class="ml-auto close text-white" onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
                <div class="toast-body">
                    <i class="fas fa-${icon}"></i> ${msg}
                </div>
            </div>`;
        $('#laravelToast').prepend(html);
        $('.toast-laravel').first().addClass('show');
        setTimeout(() => $('.toast-laravel').first().remove(), 5000);
    }

    // SUCCESS: Only show when password is updated
    @if(session('status'))
        showToast('success', 'Password Updated!', 'Your password has been changed successfully!');
    @endif

    // ERROR: Show Laravel validation errors
    @if($errors->any())
        showToast('error', 'Fix Errors', 'Please check the fields below.');
    @endif

    // Validation
    $("#resetForm").validate({
        rules: {
            password: { required: true, minlength: 8 },
            password_confirmation: { required: true, equalTo: "[name=password]" }
        },
        messages: {
            password_confirmation: { equalTo: "Passwords don't match!" }
        },
        errorPlacement: (error, el) => error.addClass("text-danger small mt-1 d-block").insertAfter(el.closest('.input-group')),
        highlight: el => $(el).closest('.input-group').find('.form-control').addClass('is-invalid'),
        unhighlight: el => $(el).closest('.input-group').find('.form-control').removeClass('is-invalid')
    });

    // Eye Toggle
    $('.toggle-password').on('click', function() {
        const input = $(this).closest('.input-group').find('input');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
});
</script>
</body>
</html>