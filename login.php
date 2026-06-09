<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$credentialError = false;
$maxLoginAttempts = 3;
$lockoutSeconds = 300;
if (isset($_GET['expired'])) {
    $error = 'Sesi berakhir. Silakan login ulang.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
    $_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

    if ((int)$_SESSION['login_locked_until'] > time()) {
        $error = 'Terlalu banyak percobaan login. Coba lagi beberapa menit.';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'unit' => $user['unit'],
            ];
            $_SESSION['last_activity'] = time();
            log_activity($pdo, $user['id'], 'Login', 'User login ke sistem');
            redirect('index.php');
        } else {
            $_SESSION['login_attempts'] = (int)$_SESSION['login_attempts'] + 1;
            if ($_SESSION['login_attempts'] >= $maxLoginAttempts) {
                $_SESSION['login_locked_until'] = time() + $lockoutSeconds;
                $_SESSION['login_attempts'] = 0;
                $error = 'Terlalu banyak percobaan login. Akun dikunci sementara 5 menit di browser ini.';
            } else {
                $error = 'Login gagal — email atau password salah';
                $credentialError = true;
            }
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ITI Project Manager</title>
    <style>
        :root {
            --navy: #071d42;
            --navy-light: #123f98;
            --orange: #ed790d;
            --slate: #64748b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--navy);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f8fafc;
        }

        .login-layout {
            min-height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 3fr) minmax(480px, 2fr);
        }

        .campus-panel {
            position: relative;
            isolation: isolate;
            display: flex;
            min-height: 100vh;
            overflow: hidden;
            color: #fff;
            background: url("assets/images/gedung-iti.jpg") center / cover no-repeat;
        }

        .campus-panel::before {
            position: absolute;
            z-index: -1;
            inset: 0;
            content: "";
            background:
                linear-gradient(180deg, rgba(3, 21, 49, .75) 0%, rgba(3, 23, 53, .83) 100%),
                linear-gradient(90deg, rgba(2, 17, 40, .2), transparent);
        }

        .campus-content {
            width: min(100%, 760px);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px clamp(40px, 8vw, 150px);
        }

        .brand-logo {
            width: 132px;
            height: 132px;
            margin-bottom: 22px;
            object-fit: contain;
            clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
        }

        .campus-content h1 {
            margin: 0;
            font-size: clamp(38px, 4.2vw, 64px);
            line-height: 1.08;
            letter-spacing: -.04em;
        }

        .tagline {
            margin: 12px 0 0;
            color: var(--orange);
            font-size: clamp(18px, 1.8vw, 25px);
            font-weight: 700;
        }

        .orange-line {
            width: 80px;
            height: 4px;
            margin: 30px 0 22px;
            border-radius: 999px;
            background: var(--orange);
        }

        .description {
            max-width: 560px;
            margin: 0;
            color: rgba(255, 255, 255, .88);
            font-size: 17px;
            line-height: 1.65;
        }

        .institution {
            display: flex;
            gap: 14px;
            align-items: center;
            margin-top: auto;
            padding-top: 60px;
            color: rgba(255, 255, 255, .78);
        }

        .institution svg {
            flex: 0 0 auto;
            color: var(--orange);
        }

        .institution strong,
        .institution span {
            display: block;
        }

        .institution strong {
            margin-bottom: 4px;
            color: #fff;
            font-size: 16px;
        }

        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 48px;
            background:
                radial-gradient(circle at 50% 45%, #fff 0%, #f8fafc 65%, #eef2f7 100%);
        }

        .login-card {
            width: min(100%, 520px);
            padding: 42px 44px 36px;
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 28px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 22px 60px rgba(15, 23, 42, .11);
        }

        .card-heading {
            margin-bottom: 30px;
            text-align: center;
        }

        .card-logo {
            width: 86px;
            height: 86px;
            margin-bottom: 12px;
            object-fit: contain;
            clip-path: polygon(50% 0, 100% 50%, 50% 100%, 0 50%);
        }

        .card-heading h2 {
            margin: 0 0 8px;
            font-size: 30px;
            letter-spacing: -.025em;
        }

        .card-heading p {
            margin: 0;
            color: var(--slate);
            font-size: 15px;
        }

        .alert {
            display: flex;
            gap: 11px;
            align-items: center;
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #f87171;
            border-radius: 10px;
            color: #dc2626;
            background: #fef2f2;
            font-size: 14px;
            font-weight: 700;
        }

        .alert svg {
            flex: 0 0 auto;
        }

        .field {
            display: block;
            margin-bottom: 20px;
        }

        .field-label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 14px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap svg {
            position: absolute;
            top: 50%;
            left: 16px;
            color: #64748b;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .input-wrap input {
            width: 100%;
            min-height: 54px;
            padding: 0 48px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            outline: none;
            color: #0f172a;
            background: #fff;
            font: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        .input-wrap input:focus {
            border-color: #2352b5;
            box-shadow: 0 0 0 4px rgba(35, 82, 181, .12);
        }

        .input-wrap input.input-error,
        .input-wrap input.input-error:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .08);
        }

        .field-error {
            display: block;
            margin-top: 5px;
            color: #ef4444;
            font-size: 12px;
            line-height: 1.35;
        }

        .input-wrap input::placeholder {
            color: #94a3b8;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 9px;
            display: grid;
            width: 38px;
            height: 38px;
            padding: 0;
            place-items: center;
            border: 0;
            border-radius: 8px;
            color: #475569;
            background: transparent;
            cursor: pointer;
            transform: translateY(-50%);
        }

        .password-toggle:hover {
            background: #f1f5f9;
        }

        .password-toggle svg {
            position: static;
            transform: none;
            pointer-events: auto;
        }

        .login-button {
            width: 100%;
            min-height: 52px;
            border: 0;
            border-radius: 9px;
            color: #fff;
            background: linear-gradient(135deg, #1746a2, #082f88);
            box-shadow: 0 8px 20px rgba(14, 61, 153, .22);
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition: transform .2s, box-shadow .2s;
        }

        .login-button:hover {
            box-shadow: 0 11px 24px rgba(14, 61, 153, .3);
            transform: translateY(-1px);
        }

        .login-button.is-loading {
            cursor: wait;
            opacity: .78;
            pointer-events: none;
        }

        .demo {
            margin-top: 24px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
            font-size: 12px;
        }

        .demo summary {
            padding-top: 18px;
            color: #3156a0;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 12px;
            margin-top: 14px;
            padding: 12px;
            border-radius: 10px;
            background: #f8fafc;
        }

        .demo-grid p {
            margin: 0;
        }

        /* Keep the interface compact on common 1920x1080 desktop displays. */
        @media (min-width: 1400px) {
            .login-layout {
                grid-template-columns: minmax(0, 59%) minmax(460px, 41%);
            }

            .campus-content {
                width: min(100%, 720px);
                padding: 52px clamp(70px, 7vw, 132px);
            }

            .brand-logo {
                width: 122px;
                height: 122px;
                margin-bottom: 26px;
            }

            .campus-content h1 {
                font-size: 46px;
                line-height: 1.1;
                letter-spacing: -.035em;
            }

            .tagline {
                margin-top: 8px;
                font-size: 18px;
                line-height: 1.35;
            }

            .orange-line {
                width: 50px;
                height: 3px;
                margin: 22px 0 18px;
            }

            .description {
                max-width: 455px;
                font-size: 14px;
                line-height: 1.55;
            }

            .institution {
                padding-top: 40px;
                font-size: 14px;
            }

            .form-panel {
                padding: 36px;
            }

            .login-card {
                width: min(100%, 450px);
                padding: 30px 34px 26px;
                border-radius: 22px;
            }

            .card-heading {
                margin-bottom: 22px;
            }

            .card-logo {
                width: 72px;
                height: 72px;
                margin-bottom: 8px;
            }

            .card-heading h2 {
                margin-bottom: 5px;
                font-size: 25px;
            }

            .card-heading p {
                font-size: 13px;
            }

            .field {
                margin-bottom: 16px;
            }

            .field-label {
                margin-bottom: 6px;
                font-size: 13px;
            }

            .input-wrap input,
            .login-button {
                min-height: 48px;
            }

            .input-wrap input {
                font-size: 14px;
            }

            .demo {
                margin-top: 18px;
            }

            .demo summary {
                padding-top: 14px;
            }
        }

        @media (max-width: 1000px) {
            .login-layout {
                display: block;
            }

            .campus-panel {
                min-height: 360px;
            }

            .campus-content {
                min-height: 360px;
                padding: 42px;
            }

            .brand-logo {
                width: 84px;
                height: 84px;
            }

            .institution {
                display: none;
            }

            .form-panel {
                min-height: auto;
                padding: 48px 24px;
            }
        }

        @media (max-width: 560px) {
            .campus-panel {
                min-height: 310px;
            }

            .campus-content {
                min-height: 310px;
                padding: 30px 24px;
            }

            .description {
                font-size: 14px;
            }

            .login-card {
                padding: 30px 22px;
                border-radius: 20px;
            }

            .form-panel {
                padding: 24px 14px;
            }

            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="login-layout">
        <section class="campus-panel" aria-label="Tentang ITI Project Manager">
            <div class="campus-content">
                <img class="brand-logo" src="assets/images/iti-logo.jpg" alt="Logo Institut Teknologi Indonesia">
                <h1>ITI Project Manager</h1>
                <p class="tagline">Sistem Informasi Manajemen Tugas &amp; Proyek</p>
                <span class="orange-line" aria-hidden="true"></span>
                <p class="description">Kelola proyek, tugas, deadline, dan kolaborasi internal dalam satu platform terintegrasi untuk mendukung produktivitas dan kinerja institusi.</p>

                <div class="institution">
                    <svg width="34" height="34" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M3 21h18M5 18h14M6 18V9m4 9V9m4 9V9m4 9V9M3 7l9-4 9 4H3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div>
                        <strong>Institut Teknologi Indonesia</strong>
                        <span>The Technology-based Entrepreneur University</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="form-panel">
            <div class="login-card">
                <div class="card-heading">
                    <img class="card-logo" src="assets/images/iti-logo.jpg" alt="Logo ITI">
                    <h2>Selamat Datang Kembali</h2>
                    <p>Masuk untuk melanjutkan ke ITI Project Manager</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert" role="alert">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
                            <path d="M12 7.5v5M12 16.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <span><?= e($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field() ?>
                    <label class="field">
                        <span class="field-label">Email</span>
                        <span class="input-wrap">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M20 21a8 8 0 0 0-16 0m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                            <input class="<?= $credentialError ? 'input-error' : '' ?>" name="email" type="email" required autocomplete="email" placeholder="Masukkan email Anda" value="<?= e($_POST['email'] ?? '') ?>" <?= $credentialError ? 'aria-invalid="true" aria-describedby="email-error"' : '' ?>>
                        </span>
                        <?php if ($credentialError): ?>
                            <span class="field-error" id="email-error">Email atau password yang Anda masukkan salah.</span>
                        <?php endif; ?>
                    </label>

                    <label class="field">
                        <span class="field-label">Password</span>
                        <span class="input-wrap">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M7 10V8a5 5 0 0 1 10 0v2m-9 0h8a2 2 0 0 1 2 2v7H6v-7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <input class="<?= $credentialError ? 'input-error' : '' ?>" id="password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan password Anda" <?= $credentialError ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                            <button class="password-toggle" id="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/>
                                </svg>
                            </button>
                        </span>
                        <?php if ($credentialError): ?>
                            <span class="field-error" id="password-error">Email atau password yang Anda masukkan salah.</span>
                        <?php endif; ?>
                    </label>

                    <button class="login-button" type="submit">Masuk</button>
                </form>

            </div>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('password-toggle');

        passwordToggle.addEventListener('click', () => {
            const showPassword = passwordInput.type === 'password';
            passwordInput.type = showPassword ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(showPassword));
            passwordToggle.setAttribute('aria-label', showPassword ? 'Sembunyikan password' : 'Tampilkan password');
        });
        document.querySelector('form').addEventListener('submit', event => {
            if (!event.target.checkValidity()) return;
            const button = event.target.querySelector('.login-button');
            button.textContent = 'Memproses...';
            button.classList.add('is-loading');
        });
    </script>
</body>
</html>
