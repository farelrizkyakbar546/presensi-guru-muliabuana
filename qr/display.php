<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

$_SESSION['display_screen_active'] = true;

function getDetectedLanIp(): string {
    exec('ipconfig', $lines);
    $currentAdapter = '';
    $ips = [];
    foreach ($lines as $line) {
        if (preg_match('/adapter (.*?):/i', $line, $m)) {
            $currentAdapter = trim($m[1]);
        }
        if (preg_match('/IPv4.*?:\s*([0-9\.]+)/i', $line, $m)) {
            $ip = trim($m[1]);
            if ($ip !== '127.0.0.1' && !str_starts_with($ip, '169.254')) {
                $ips[$currentAdapter] = $ip;
            }
        }
    }
    foreach ($ips as $adapter => $ip) {
        if (stripos($adapter, 'wi-fi') !== false || stripos($adapter, 'wireless') !== false) {
            return $ip;
        }
    }
    foreach ($ips as $adapter => $ip) {
        if (stripos($adapter, 'ethernet') !== false && !str_starts_with($ip, '192.168.56.')) {
            return $ip;
        }
    }
    return !empty($ips) ? reset($ips) : (gethostbyname(gethostname()) ?: '127.0.0.1');
}

$lanIp = getDetectedLanIp();
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
$currentProto = $isHttps ? 'https' : 'http';
$serverUrl = $currentProto . '://' . $lanIp . '/absensi-pkm/qr/display.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display QR Presensi - SMKS Mulia Buana</title>
    <style>
        * { box-sizing: border-box; }
        html, body { width: 100%; min-height: 100%; }
        body {
            margin: 0; min-height: 100vh; padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8; color: #1f2937;
            display: flex; align-items: center; justify-content: center;
        }
        .container {
            width: 100%; max-width: 620px; text-align: center;
            background: #ffffff; border-radius: 16px; padding: 24px 24px 32px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.07);
        }
        .display-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #e5e7eb;
            gap: 12px;
            flex-wrap: wrap;
        }
        .live-clock-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #4b5563;
        }
        .live-time {
            font-weight: 700;
            color: #111827;
            background: #eef2f7;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
        }
        .live-date {
            color: #6b7280;
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-topbar {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-topbar:hover {
            background: #e5e7eb;
            color: #111827;
        }
        .title { margin: 0 0 8px; font-size: 26px; line-height: 1.2; font-weight: 700; color: #111827; }
        .subtitle { margin: 0 auto 20px; max-width: 480px; color: #6b7280; font-size: 14px; line-height: 1.5; }
        .ip-info {
            background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px;
            padding: 12px 16px; margin: 0 auto 24px; max-width: 480px; text-align: left;
            font-size: 13px; color: #1e40af; line-height: 1.5;
        }
        .ip-info strong { color: #1e3a8a; }
        .ip-info code { background: #dbeafe; padding: 2px 6px; border-radius: 4px; font-size: 13px; font-weight: bold; }
        .generate-button {
            width: 100%; max-width: 320px; min-height: 50px; padding: 14px 24px;
            font-size: 16px; font-weight: 700; color: #ffffff; background: #111827;
            border: none; border-radius: 10px; cursor: pointer; transition: background 0.15s ease;
        }
        .generate-button:hover:not(:disabled) { background: #374151; }
        .generate-button:disabled { opacity: 0.6; cursor: not-allowed; }
        .auto-mode-toggle {
            margin: 18px auto 0;
            font-size: 13px;
            color: #4b5563;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auto-mode-toggle label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }
        .qr-card { display: none; }
        .status-box {
            min-height: 48px; display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px; padding: 10px 16px; border-radius: 8px;
            background: #eef2f7; color: #1f2937; font-size: 15px; font-weight: 600;
        }
        .status-box.error { background: #fee2e2; color: #991b1b; }
        .status-box.success { background: #dcfce7; color: #166534; }
        .qr-box {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 16px; background: #ffffff; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); margin-bottom: 18px;
        }
        .qr-box img { display: block; max-width: 100%; height: auto; }
        .timer-box { font-size: 14px; color: #6b7280; margin-bottom: 12px; }
        .timer-value { font-size: 22px; font-weight: 700; color: #111827; }
        .info { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
        .back-button {
            padding: 10px 20px; font-size: 14px; font-weight: 600; color: #374151;
            background: #f3f4f6; border: none; border-radius: 8px; cursor: pointer;
        }
        .back-button:hover { background: #e5e7eb; }
    </style>
</head>
<body>

<div class="container">
    <div class="display-topbar">
        <div class="live-clock-badge">
            <span id="liveDate" class="live-date">--</span>
            <span id="liveTime" class="live-time">00:00:00 WIB</span>
        </div>
        <div class="topbar-actions">
            <button type="button" id="btnFullscreen" class="btn-topbar" onclick="toggleFullscreen()" title="Layar Penuh (Kiosk)">
                <svg id="iconExpand" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                <span id="labelFullscreen">Layar Penuh</span>
            </button>
        </div>
    </div>

    <div id="homeContent">
        <h1 class="title">Presensi Guru</h1>
        <p class="subtitle">Layar Monitor Utama Sistem Presensi Sekolah</p>

        <div class="ip-info">
            <strong>Koneksi Jaringan Lokal Sekolah:</strong><br>
            Akses sistem melalui IP Server: <code><?= htmlspecialchars($serverUrl, ENT_QUOTES, 'UTF-8') ?></code><br>
            <span style="font-size: 12px; color: #4b5563;">Pastikan smartphone guru terhubung ke Wi-Fi / Hotspot yang sama.</span>
        </div>

        <button type="button" id="generateButton" class="generate-button" onclick="generateQRCode()">
            Tampilkan QR Code
        </button>

        <div class="auto-mode-toggle">
            <label>
                <input type="checkbox" id="autoModeCheck" checked>
                <span>Mode Kiosk (Otomatis perbarui QR setelah di-scan / habis waktu)</span>
            </label>
        </div>
    </div>

    <div id="qrContent" class="qr-card">
        <h1 class="title">Scan QR Presensi</h1>
        <div id="status" class="status-box">Menyiapkan QR...</div>
        <div class="qr-box">
            <div id="qrcode"></div>
        </div>
        <div class="timer-box">Sisa waktu aktif: <span id="timer" class="timer-value">10:00</span></div>
        <div class="info">QR hanya dapat digunakan satu kali dan berlaku selama 10 menit.</div>
        <button type="button" id="backButton" class="back-button" onclick="showHome()">Kembali</button>
    </div>
</div>

<script src="../assets/js/qrcode.min.js"></script>
<script>
    const homeContent = document.getElementById('homeContent');
    const qrContent = document.getElementById('qrContent');
    const generateButton = document.getElementById('generateButton');
    const qrContainer = document.getElementById('qrcode');
    const statusElement = document.getElementById('status');
    const timerElement = document.getElementById('timer');
    const autoModeCheck = document.getElementById('autoModeCheck');
    const liveTimeEl = document.getElementById('liveTime');
    const liveDateEl = document.getElementById('liveDate');
    const labelFullscreen = document.getElementById('labelFullscreen');
    const serverLanIp = <?= json_encode($lanIp) ?>;

    let currentToken = null;
    let expiresAt = null;
    let countdownInterval = null;
    let statusCheckInterval = null;
    let generating = false;

    // Format seconds into MM:SS
    function formatTime(totalSeconds) {
        const mins = Math.floor(totalSeconds / 60);
        const secs = totalSeconds % 60;
        return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    // Live Digital Clock
    function updateClock() {
        const now = new Date();
        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        
        const dayName = days[now.getDay()];
        const date = now.getDate();
        const monthName = months[now.getMonth()];
        const year = now.getFullYear();

        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        liveDateEl.textContent = `${dayName}, ${date} ${monthName} ${year}`;
        liveTimeEl.textContent = `${hours}:${minutes}:${seconds} WIB`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Fullscreen Toggle
    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().then(() => {
                labelFullscreen.textContent = 'Keluar Layar Penuh';
            }).catch(() => {});
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen().then(() => {
                    labelFullscreen.textContent = 'Layar Penuh';
                }).catch(() => {});
            }
        }
    }

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement) {
            labelFullscreen.textContent = 'Layar Penuh';
        } else {
            labelFullscreen.textContent = 'Keluar Layar Penuh';
        }
    });

    function getServerBaseUrl() {
        let origin = window.location.origin;
        const path = window.location.pathname;
        const marker = '/qr/display.php';
        const markerIndex = path.indexOf(marker);
        if (markerIndex === -1) {
            throw new Error('Lokasi display QR tidak dikenali.');
        }
        const basePath = path.substring(0, markerIndex);

        if ((window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') && serverLanIp && serverLanIp !== '127.0.0.1') {
            const proto = window.location.protocol;
            const port = window.location.port ? ':' + window.location.port : '';
            origin = proto + '//' + serverLanIp + port;
        }

        return origin + basePath;
    }

    function clearTimers() {
        if (countdownInterval !== null) { clearInterval(countdownInterval); countdownInterval = null; }
        if (statusCheckInterval !== null) { clearInterval(statusCheckInterval); statusCheckInterval = null; }
    }

    function showHome() {
        clearTimers();
        currentToken = null;
        expiresAt = null;
        qrContainer.innerHTML = '';
        timerElement.textContent = '10:00';
        statusElement.textContent = 'Menyiapkan QR...';
        statusElement.classList.remove('error', 'success');
        homeContent.style.display = 'block';
        qrContent.style.display = 'none';
        generateButton.disabled = false;
    }

    async function generateQRCode() {
        if (generating) return;
        generating = true;
        clearTimers();
        currentToken = null;
        expiresAt = null;
        generateButton.disabled = true;
        homeContent.style.display = 'none';
        qrContent.style.display = 'block';
        qrContainer.innerHTML = '';
        statusElement.classList.remove('error', 'success');
        statusElement.textContent = 'Membuat QR Code...';
        timerElement.textContent = '10:00';

        try {
            const response = await fetch('generate.php?_=' + Date.now(), { method: 'GET', cache: 'no-store' });
            if (!response.ok) throw new Error('Gagal menghubungi server.');
            const data = await response.json();
            if (!data.success || !data.token) throw new Error('Token QR tidak tersedia.');

            currentToken = data.token;
            expiresAt = new Date(data.expires_at);

            const serverBaseUrl = getServerBaseUrl();
            const scanUrl = serverBaseUrl + '/qr/scan.php?token=' + encodeURIComponent(data.token);

            new QRCode(qrContainer, {
                text: scanUrl,
                width: 360,
                height: 360,
                correctLevel: QRCode.CorrectLevel.M
            });

            statusElement.textContent = 'Silakan scan QR Code';
            startCountdown();
            startStatusCheck();
        } catch (error) {
            console.error(error);
            qrContainer.innerHTML = '';
            statusElement.textContent = 'Gagal membuat QR.';
            statusElement.classList.add('error');
            timerElement.textContent = '-';
            setTimeout(showHome, 2500);
        } finally {
            generating = false;
        }
    }

    function startCountdown() {
        if (countdownInterval !== null) clearInterval(countdownInterval);
        function updateTimer() {
            if (!expiresAt) return;
            const now = new Date();
            const diffMs = expiresAt.getTime() - now.getTime();
            const remaining = Math.max(0, Math.ceil(diffMs / 1000));
            timerElement.textContent = formatTime(remaining);
            if (remaining <= 0) {
                clearTimers();
                statusElement.textContent = 'QR kedaluwarsa.';
                statusElement.classList.add('error');
                if (autoModeCheck && autoModeCheck.checked) {
                    setTimeout(generateQRCode, 1500);
                } else {
                    setTimeout(showHome, 2000);
                }
            }
        }
        updateTimer();
        countdownInterval = setInterval(updateTimer, 500);
    }

    function startStatusCheck() {
        if (statusCheckInterval !== null) clearInterval(statusCheckInterval);
        statusCheckInterval = setInterval(async function () {
            if (!currentToken) return;
            try {
                const response = await fetch('status.php?token=' + encodeURIComponent(currentToken) + '&_=' + Date.now(), { method: 'GET', cache: 'no-store' });
                if (!response.ok) return;
                const result = await response.json();
                if (result.success && result.used) {
                    clearTimers();
                    statusElement.textContent = 'Presensi berhasil dicatat!';
                    statusElement.classList.remove('error');
                    statusElement.classList.add('success');
                    if (autoModeCheck && autoModeCheck.checked) {
                        setTimeout(generateQRCode, 2500);
                    } else {
                        setTimeout(showHome, 3000);
                    }
                }
            } catch (err) {
                console.error(err);
            }
        }, 1500);
    }
</script>
</body>
</html>
