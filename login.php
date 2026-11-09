<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/helpers.php';

if (isLoggedIn()) {
    switch (currentRole()) {
        case 'guru':
            header('Location: guru/dashboard.php');
            exit;
        case 'adminTu':
            header('Location: admintu/dashboard.php');
            exit;
        case 'kepsek':
            header('Location: kepsek/dashboard.php');
            exit;
        default:
            header('Location: logout.php');
            exit;
    }
}

$error = flash('error');
$success = flash('success');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SMKS Mulia Buana</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; font-family: "Segoe UI", Arial, sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(115deg, rgba(15, 23, 42, 0.78), rgba(15, 23, 42, 0.48)),
                        url("assets/img/bgsmks.jpg") center center / cover no-repeat fixed;
            display: flex; align-items: center; justify-content: center;
            padding: 40px 20px; color: #172033;
        }
        .login-wrapper {
            width: 100%; max-width: 1080px; display: flex; align-items: stretch; justify-content: center;
            gap: 0; border-radius: 24px; overflow: hidden; box-shadow: 0 30px 80px rgba(0, 0, 0, 0.28);
        }
        .school-panel {
            width: 52%; min-height: 600px; position: relative; display: flex; flex-direction: column;
            justify-content: flex-end; padding: 55px; color: #ffffff;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.05) 0%, rgba(15, 23, 42, 0.25) 45%, rgba(15, 23, 42, 0.82) 100%);
        }
        .school-content { position: relative; z-index: 2; max-width: 480px; }
        .school-label {
            display: inline-flex; align-items: center; padding: 7px 12px; margin-bottom: 16px;
            border: 1px solid rgba(255, 255, 255, 0.30); border-radius: 999px;
            background: rgba(255, 255, 255, 0.12); backdrop-filter: blur(8px);
            font-size: 12px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase;
        }
        .school-content h1 { margin: 0 0 12px; font-size: 38px; line-height: 1.15; font-weight: 700; letter-spacing: -0.8px; }
        .school-content p { margin: 0; max-width: 440px; color: rgba(255, 255, 255, 0.86); font-size: 15px; line-height: 1.7; }
        .login-panel {
            width: 48%; min-height: 600px; background: rgba(255, 255, 255, 0.97);
            display: flex; align-items: center; justify-content: center; padding: 45px;
        }
        .login-card { width: 100%; max-width: 380px; text-align: center; }
        .logo-wrapper {
            width: 92px; height: 92px; margin: 0 auto 20px; display: flex; align-items: center;
            justify-content: center; background: #ffffff; border-radius: 20px; padding: 8px;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.10);
        }
        .logo-wrapper img { width: 100%; height: 100%; object-fit: contain; border-radius: 14px; }
        .login-card h2 { margin: 0; color: #172033; font-size: 25px; line-height: 1.3; font-weight: 700; }
        .login-subtitle { margin: 8px 0 30px; color: #64748b; font-size: 13px; line-height: 1.6; }
        .alert { padding: 12px 14px; margin-bottom: 18px; border-radius: 10px; text-align: left; font-size: 13px; line-height: 1.5; }
        .alert-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
        .form-group { margin-bottom: 18px; text-align: left; }
        .form-group label { display: block; margin-bottom: 7px; color: #334155; font-size: 13px; font-weight: 600; }
        .form-group input {
            width: 100%; height: 48px; padding: 0 14px; border: 1px solid #d7dee8; border-radius: 10px;
            outline: none; background: #f8fafc; color: #172033; font-family: inherit; font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .form-group input::placeholder { color: #94a3b8; }
        .form-group input:focus { background: #ffffff; border-color: #334155; box-shadow: 0 0 0 3px rgba(51, 65, 85, 0.10); }
        .btn-login {
            width: 100%; height: 48px; margin-top: 5px; border: 0; border-radius: 10px;
            background: #172033; color: #ffffff; font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .btn-login:hover { background: #253149; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18); transform: translateY(-1px); }
        .btn-login:active { transform: translateY(0); }
        .login-footer { margin-top: 30px; padding-top: 18px; border-top: 1px solid #e8edf3; color: #94a3b8; font-size: 11px; line-height: 1.6; }
        .login-footer strong { color: #64748b; }
        @media (max-width: 850px) {
            body { padding: 25px 15px; }
            .login-wrapper { max-width: 520px; display: block; border-radius: 20px; }
            .school-panel { width: 100%; min-height: 250px; padding: 35px; justify-content: flex-end; }
            .school-content h1 { font-size: 28px; }
            .school-content p { font-size: 13px; }
            .login-panel { width: 100%; min-height: auto; padding: 38px 30px; }
        }
        @media (max-width: 520px) {
            body { padding: 15px; background-position: center; }
            .login-wrapper { border-radius: 18px; }
            .school-panel { min-height: 190px; padding: 25px; }
            .school-label { font-size: 10px; padding: 6px 10px; }
            .school-content h1 { font-size: 24px; }
            .school-content p { display: none; }
            .login-panel { padding: 32px 24px; }
            .logo-wrapper { width: 78px; height: 78px; }
            .login-card h2 { font-size: 22px; }
            .login-subtitle { margin-bottom: 25px; }
        }
    </style>
</head>
<body>
    <main class="login-wrapper">
        <section class="school-panel">
            <div class="school-content">
                <div class="school-label">Sistem Informasi Sekolah</div>
                <h1>SMKS Mulia Buana</h1>
                <p>Sistem Digitalisasi Presensi dan Monitoring Kehadiran Guru berbasis web untuk mendukung pengelolaan kehadiran secara lebih tertib dan terintegrasi.</p>
            </div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="logo-wrapper">
                    <img src="assets/img/download-removebg-preview.png" alt="Logo SMKS Mulia Buana">
                </div>
                <h2>Selamat Datang</h2>
                <p class="login-subtitle">Silakan masuk untuk melanjutkan ke sistem presensi.</p>
                <?php if ($error): ?>
                    <div class="alert alert-error"><?= e($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                <form action="proses/login.php" method="POST" autocomplete="off">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Masukkan username" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-login">Masuk ke Sistem</button>
                </form>
                <div class="login-footer">
                    &copy; <?= date('Y') ?> <strong>SMKS Mulia Buana</strong><br>
                    Sistem Digitalisasi Presensi dan Monitoring Kehadiran Guru
                </div>
            </div>
        </section>
    </main>
</body>
</html>