<?php
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid email or password. Please try again.";
    }
}
?>
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
        }

        /* ── Left Panel ── */
        .left-panel {
            width: 42%;
            background: var(--green);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            top: -90px; right: -90px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.07);
        }
        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -70px; left: -70px;
            width: 240px; height: 240px;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .brand-logo {
            width: 68px; height: 68px;
            background: rgba(255,255,255,0.15);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }
        .brand-logo i { font-size: 1.9rem; color: #fff; }
        .brand-name {
            font-size: 1.9rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 3px;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .left-divider {
            width: 40px; height: 3px;
            background: rgba(255,255,255,0.4);
            border-radius: 2px;
            margin: 14px 0 18px;
            position: relative; z-index: 1;
        }
        .brand-tagline {
            font-size: 0.86rem;
            color: rgba(255,255,255,0.8);
            text-align: center;
            line-height: 1.65;
            max-width: 270px;
            position: relative; z-index: 1;
        }
        .left-stats {
            display: flex;
            gap: 32px;
            margin-top: 32px;
            position: relative; z-index: 1;
        }
        .left-stat { text-align: center; }
        .left-stat .num {
            font-size: 1.5rem;
            font-weight: 800;
            color: #fff;
        }
        .left-stat .lbl {
            font-size: 0.68rem;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ── Right Panel ── */
        .right-panel {
            flex: 1;
            background: var(--green-light);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
        }
        .login-box { width: 100%; max-width: 390px; }
        .login-box h2 {
            font-size: 1.55rem;
            font-weight: 700;
            color: #1a2e22;
            margin-bottom: 5px;
        }
        .login-box .subtitle {
            font-size: 0.83rem;
            color: #6c7a6f;
            margin-bottom: 32px;
        }
        .form-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #1a2e22;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            display: block;
        }
        .input-wrap {
            position: relative;
            margin-bottom: 18px;
        }
        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: #b0c4ba;
            font-size: 0.82rem;
        }
        .form-control {
            width: 100%;
            border: 1.5px solid #cce0d6;
            border-radius: 9px;
            padding: 12px 14px 12px 38px;
            font-size: 0.875rem;
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1a2e22;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(34,169,110,0.12);
        }
        .form-control::placeholder { color: #b0c4ba; }
        .btn-login {
            width: 100%;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 13px;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 0.4px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            margin-top: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-login:hover { background: var(--green-dark); transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }
        .forgot {
            text-align: center;
            margin-top: 16px;
            font-size: 0.78rem;
            color: #6c7a6f;
        }
        .forgot a { color: var(--green); text-decoration: none; font-weight: 500; }
        .forgot a:hover { text-decoration: underline; }
        .footer-note {
            text-align: center;
            margin-top: 30px;
            font-size: 0.71rem;
            color: #b0c4ba;
        }
        .error-box {
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.82rem;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .left-panel { width: 100%; min-height: 180px; padding: 36px 24px; }
            .right-panel { padding: 36px 20px; }
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
    <div class="left-stats">
        <div class="left-stat">
            <div class="num">100%</div>
            <div class="lbl">Secure</div>
        </div>
        <div class="left-stat">
            <div class="num">24/7</div>
            <div class="lbl">Uptime</div>
        </div>
        <div class="left-stat">
            <div class="num">Live</div>
            <div class="lbl">Tracking</div>
        </div>
    </div>
</div>

<!-- Right Login Panel -->
<div class="right-panel">
    <div class="login-box">
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your Ratepoint account to continue</p>

        <?php if ($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <label class="form-label">Email Address</label>
            <div class="input-wrap">
                <i class="fas fa-envelope input-icon"></i>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    placeholder="you@assembly.gov.gh"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <label class="form-label">Password</label>
            <div class="input-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input
                    type="password"
                    name="password"
                    class="form-control"
                    placeholder="••••••••"
                    required
                >
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> SECURE LOGIN
            </button>
        </form>

        <div class="forgot">
            Forgot password? <a href="#">Contact Administrator</a>
        </div>
        <div class="footer-note">
            &copy; <?= date('Y') ?> Municipal Revenue Authority &bull; All rights reserved
            <br>
            <span style="display: inline-block; margin-top: 5px; font-weight: 600; opacity: 0.8;">Powered By 1st Son Technology</span>
        </div>
    </div>
</div>

</body>
</html>
