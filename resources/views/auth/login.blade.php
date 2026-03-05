<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login – WMS Avian GA</title>
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('adminlte/css/adminlte.min.css') }}">
    <style>
        body {
            background: linear-gradient(135deg, #004230 0%, #0d8564 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Roboto', sans-serif;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 16px;
        }

        .login-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #004230 0%, #0d8564 100%);
            padding: 32px 30px 28px;
            text-align: center;
        }

        .login-header img {
            height: 50px;
            margin-bottom: 12px;
        }

        .login-header h4 {
            color: #fff;
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .login-header p {
            color: rgba(255, 255, 255, .75);
            font-size: 13px;
            margin: 0;
        }

        .login-body {
            padding: 30px;
        }

        .form-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #e5e7eb;
            font-size: 14px;
            padding: 10px 14px;
            transition: border-color .2s;
        }

        .form-control:focus {
            border-color: #0d8564;
            box-shadow: 0 0 0 3px rgba(13, 133, 100, .12);
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
            border: 1.5px solid #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
        }

        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }

        .btn-login {
            background: linear-gradient(135deg, #0d8564 0%, #004230 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            width: 100%;
            transition: opacity .2s;
        }

        .btn-login:hover {
            opacity: .9;
            color: #fff;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }

        .login-footer {
            text-align: center;
            padding: 0 30px 24px;
            font-size: 12px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="login-card">
            <!-- Header -->
            <div class="login-header">
                <img src="{{ asset('images/avian-logo-icon.png') }}" alt="Avian" onerror="this.style.display='none'">
                <h4>WMS Avian GA</h4>
                <p>Warehouse Management System</p>
            </div>

            <!-- Body -->
            <div class="login-body">
                @if (session('error'))
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('login.post') }}" method="POST">
                    @csrf

                    <div class="form-group mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <div class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input type="email" name="email"
                                class="form-control @error('email') is-invalid @enderror"
                                placeholder="email@avianbrands.com" value="{{ old('email') }}" autofocus>
                        </div>
                        @error('email')
                            <div class="text-danger" style="font-size:12px;margin-top:4px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <div class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input type="password" name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Masukkan password">
                        </div>
                        @error('password')
                            <div class="text-danger" style="font-size:12px;margin-top:4px;">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group mb-4">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="remember" name="remember">
                            <label class="custom-control-label" for="remember" style="font-size:13px;color:#6b7280;">
                                Ingat saya
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt mr-2"></i> Masuk
                    </button>
                </form>
            </div>

            <div class="login-footer">
                &copy; {{ date('Y') }} PT Avian Brands &nbsp;·&nbsp; WMS v1.0.0
            </div>
        </div>
    </div>

    <script src="{{ asset('adminlte/plugins/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>

</html>
