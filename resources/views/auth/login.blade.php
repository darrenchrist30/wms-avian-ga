<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login – WMS Avian</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('adminlte/plugins/fontawesome-free/css/all.min.css') }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eef2f0;
        }

        /* ── Accent bar di atas card ─── */
        .login-card {
            width: 100%;
            max-width: 400px;
            margin: 24px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 32px rgba(0,0,0,.12), 0 1px 4px rgba(0,0,0,.06);
            overflow: hidden;
        }

        .card-top {
            background: #fff;
            padding: 32px 36px 20px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }

        /* Garis aksen hijau di paling atas card */
        .card-top::before {
            content: '';
            display: block;
            height: 4px;
            background: linear-gradient(90deg, #004230, #38c172);
            border-radius: 4px 4px 0 0;
            margin: -32px -36px 28px;
        }

        .card-top h1 {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            letter-spacing: -.1px;
            margin-top: 8px;
        }

        .card-top p {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* ── Form ─── */
        .card-body {
            padding: 32px 36px 28px;
        }

        .form-heading {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 4px;
        }

        .form-sub {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 24px;
        }

        /* Alert */
        .alert-box {
            border-radius: 8px;
            padding: 10px 13px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
            line-height: 1.5;
        }
        .alert-box i { flex-shrink: 0; margin-top: 1px; }
        .alert-box.error   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-box.success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        /* Field */
        .field-group { margin-bottom: 14px; }

        .field-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .field-wrap { position: relative; }

        .field-wrap .ico {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #d1d5db;
            font-size: 12px;
            pointer-events: none;
            transition: color .15s;
        }

        .field-wrap:focus-within .ico { color: #38c172; }

        .field-wrap input {
            width: 100%;
            padding: 10px 14px 10px 36px;
            border: 1.5px solid #e5e7eb;
            border-radius: 8px;
            font-size: 13.5px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: border-color .15s, box-shadow .15s, background .15s;
        }

        .field-wrap input::placeholder { color: #b0b7c3; }

        .field-wrap input:focus {
            background: #fff;
            border-color: #38c172;
            box-shadow: 0 0 0 3px rgba(56,193,114,.1);
        }

        .field-wrap input.is-invalid { border-color: #e3342f; }
        .field-wrap input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(227,52,47,.1); }

        .invalid-text { font-size: 12px; color: #e3342f; margin-top: 4px; }

        /* Toggle pw */
        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #d1d5db;
            cursor: pointer;
            font-size: 12px;
            transition: color .15s;
        }
        .toggle-pw:hover { color: #38c172; }

        /* Remember + Lupa password row */
        .row-remember {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 6px 0 20px;
        }
        .row-remember .left {
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .row-remember input[type=checkbox] {
            width: 14px; height: 14px;
            accent-color: #38c172;
            cursor: pointer;
        }
        .row-remember label { font-size: 12.5px; color: #6b7280; cursor: pointer; }
        .link-forgot {
            font-size: 12.5px;
            color: #38c172;
            text-decoration: none;
            font-weight: 500;
            transition: color .15s;
        }
        .link-forgot:hover { color: #2ea65f; text-decoration: underline; }

        /* Button */
        .btn-login {
            width: 100%;
            padding: 12px 16px;
            background: #38c172;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            line-height: 1;
            transition: background .15s, transform .1s, box-shadow .15s;
            letter-spacing: .1px;
        }
        .btn-login i { font-size: 13px; line-height: 1; position: relative; top: 0; }
        .btn-login:hover { background: #2ea65f; box-shadow: 0 4px 12px rgba(56,193,114,.3); }
        .btn-login:active { transform: scale(.98); }

        /* Footer */
        .card-foot {
            padding: 14px 36px;
            border-top: 1px solid #f3f4f6;
            text-align: center;
            font-size: 11.5px;
            color: #d1d5db;
        }
    </style>
</head>
<body>

<div class="login-card">

    {{-- Top brand bar --}}
    <div class="card-top">
        <img src="{{ asset('images/avian-logo-normal.png') }}" alt="Avian" style="height:60px; width:auto; margin-bottom:10px;">
        <h1>WMS Avian GA</h1>
        <p>Warehouse Management System</p>
    </div>

    {{-- Form --}}
    <div class="card-body">
        <div class="form-heading">Masuk</div>
        <div class="form-sub">Silakan masuk dengan akun Anda</div>

        @if (session('error'))
            <div class="alert-box error">
                <i class="fas fa-exclamation-circle"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if (session('success'))
            <div class="alert-box success">
                <i class="fas fa-check-circle"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <form action="{{ route('login.post') }}" method="POST">
            @csrf

            <div class="field-group">
                <label class="field-label" for="email">Email</label>
                <div class="field-wrap">
                    <i class="fas fa-envelope ico"></i>
                    <input type="email" id="email" name="email"
                        placeholder="nama@avianbrands.com"
                        value="{{ old('email') }}"
                        class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
                        autofocus autocomplete="username">
                </div>
                @error('email')<div class="invalid-text">{{ $message }}</div>@enderror
            </div>

            <div class="field-group">
                <label class="field-label" for="password">Password</label>
                <div class="field-wrap">
                    <i class="fas fa-lock ico"></i>
                    <input type="password" id="password" name="password"
                        placeholder="••••••••"
                        class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                        autocomplete="current-password">
                    <i class="fas fa-eye toggle-pw" id="togglePw"></i>
                </div>
                @error('password')<div class="invalid-text">{{ $message }}</div>@enderror
            </div>

            <div class="row-remember">
                <div class="left">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Ingat saya</label>
                </div>
                <a href="#" class="link-forgot">Lupa password?</a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk
            </button>
        </form>
    </div>

    {{-- Footer --}}
    <div class="card-foot">
        &copy; {{ date('Y') }} PT Avian Brands &nbsp;&middot;&nbsp; WMS v1.0
    </div>

</div>

<script>
document.getElementById('togglePw').addEventListener('click', function () {
    const pw = document.getElementById('password');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    this.className = 'toggle-pw fas ' + (show ? 'fa-eye-slash' : 'fa-eye');
});
</script>
</body>
</html>
