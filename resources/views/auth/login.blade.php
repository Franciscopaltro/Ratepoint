<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ratepoint Revenue System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: #22a96e;
            --green-dark: #1a8555;
            --green-light: #eef7f2;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background-color: var(--green-light);
        }

        /* ── Left Panel ── */
        .left-panel {
            width: 45%;
            background: var(--green);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
            position: relative;
            overflow: hidden;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }

        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -60px; left: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            backdrop-filter: blur(4px);
        }

        .brand-logo i {
            font-size: 1.8rem;
            color: #fff;
        }

        .brand-name {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 3px;
            margin-bottom: 12px;
        }

        .brand-tagline {
            font-size: 0.88rem;
            color: rgba(255,255,255,0.8);
            text-align: center;
            line-height: 1.6;
            max-width: 280px;
        }

        .left-divider {
            width: 40px;
            height: 3px;
            background: rgba(255,255,255,0.4);
            border-radius: 2px;
            margin: 20px auto;
        }

        .left-stat {
            text-align: center;
            margin-top: 10px;
        }

        .left-stat .stat-num {
            font-size: 1.6rem;
            font-weight: 800;
            color: #fff;
        }

        .left-stat .stat-label {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Right Panel ── */
        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 50px;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
        }

        .login-box h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #1a2e22;
            margin-bottom: 6px;
        }

        .login-box .subtitle {
            font-size: 0.85rem;
            color: #6c7a6f;
            margin-bottom: 36px;
        }

        .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #1a2e22;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #aac2b4;
            font-size: 0.85rem;
        }

        .form-control {
            border: 1.5px solid #d8e8e0;
            border-radius: 10px;
            padding: 12px 14px 12px 40px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1a2e22;
            transition: border-color 0.2s, box-shadow 0.2s;
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(34, 169, 110, 0.12);
            outline: none;
        }

        .form-control::placeholder { color: #aac2b4; }

        .btn-login {
            width: 100%;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 8px;
        }

        .btn-login:hover {
            background: var(--green-dark);
            transform: translateY(-1px);
        }

        .btn-login:active { transform: translateY(0); }

        .btn-login i { margin-right: 8px; }

        .forgot-link {
            text-align: center;
            margin-top: 16px;
            font-size: 0.8rem;
            color: #6c7a6f;
        }

        .forgot-link a {
            color: var(--green);
            text-decoration: none;
            font-weight: 500;
        }

        .forgot-link a:hover { text-decoration: underline; }

        .footer-note {
            text-align: center;
            margin-top: 32px;
            font-size: 0.73rem;
            color: #aac2b4;
        }

        .alert-danger {
            background: #fee2e2;
            border: none;
            color: #991b1b;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.82rem;
            margin-bottom: 20px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .left-panel {
                width: 100%;
                padding: 40px 30px;
                min-height: 200px;
            }
            .right-panel { padding: 40px 24px; }
        }
    </style>
</head>
<body>

    <!-- Left Branding Panel -->
    <div class="left-panel">
        <div class="brand-logo">
            <i class="fas fa-landmark"></i>
        </div>
        <div class="brand-name">RATEPOINT</div>
        <div class="left-divider"></div>
        <p class="brand-tagline">Digital Revenue Collection &amp; Accountability Management System</p>
        <div class="d-flex gap-4 mt-4">
            <div class="left-stat">
                <div class="stat-num">100%</div>
                <div class="stat-label">Secure</div>
            </div>
            <div class="left-stat">
                <div class="stat-num">24/7</div>
                <div class="stat-label">Uptime</div>
            </div>
            <div class="left-stat">
                <div class="stat-num">Live</div>
                <div class="stat-label">Tracking</div>
            </div>
        </div>
    </div>

    <!-- Right Login Panel -->
    <div class="right-panel">
        <div class="login-box">
            <h2>Welcome back</h2>
            <p class="subtitle">Sign in to your Ratepoint account to continue</p>

            @if(session('error') || isset($error))
                <div class="alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') ?? $error ?? 'Invalid credentials.' }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST">
                @csrf

                <div>
                    <label class="form-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            placeholder="you@assembly.gov.gh"
                            value="{{ old('email') }}"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div>
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="••••••••"
                            required
                        >
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> SECURE LOGIN
                </button>
            </form>

            <div class="forgot-link">
                Forgot password? <a href="#">Contact Administrator</a>
            </div>

            <div class="footer-note">
                &copy; {{ date('Y') }} Municipal Revenue Authority &bull; All rights reserved
            </div>
        </div>
    </div>

</body>
</html>
