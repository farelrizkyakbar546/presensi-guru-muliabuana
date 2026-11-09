<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT g.id, g.nama_lengkap, m.nama_mapel
    FROM guru g
    LEFT JOIN mapel m ON m.id = g.mapel_utama_id
    WHERE g.user_id = :user_id
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$guru = $stmt->fetch();

if (!$guru) {
    die('Data guru tidak ditemukan. Hubungi Admin TU.');
}

$guruId = $guru['id'];
$tanggalHariIni = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT id, tanggal, waktu_masuk, waktu_pulang, status, keterangan
    FROM presensi_guru
    WHERE guru_id = :guru_id AND tanggal = :tanggal
    LIMIT 1
");
$stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
$presensiHariIni = $stmt->fetch();

$stmtIzin = $pdo->prepare("
    SELECT id, jenis_izin, alasan
    FROM perizinan
    WHERE guru_id = :guru_id AND status = 'Disetujui' AND :tanggal BETWEEN tanggal_mulai AND tanggal_selesai
    ORDER BY id DESC
    LIMIT 1
");
$stmtIzin->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
$izinHariIni = $stmtIzin->fetch();

$success = flash('success');
$error = flash('error');

$sudahDatang = !empty($presensiHariIni['waktu_masuk']);
$sudahPulang = !empty($presensiHariIni['waktu_pulang']);

$hariInggris = date('l');
$namaHari = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
$hariIni = $namaHari[$hariInggris] ?? $hariInggris;

$stmtPiket = $pdo->prepare("
    SELECT id FROM jadwal_piket
    WHERE guru_id = :guru_id AND hari = :hari
    LIMIT 1
");
$stmtPiket->execute(['guru_id' => $guruId, 'hari' => $hariIni]);
$isPiketHariIni = (bool) $stmtPiket->fetch();

$activeMenu = 'presensi';
$piketHariIni = $isPiketHariIni;
$pageTitle = 'Presensi Guru';
$pageSubtitle = 'Presensi kehadiran harian';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Guru - SMKS Mulia Buana</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .qr-instruction { margin-bottom: 20px; padding: 16px; border-radius: 12px; background: #f3f4f6; color: #374151; line-height: 1.6; }
        .qr-scanner-box { width: 100%; max-width: 520px; margin: 0 auto; }
        #qr-reader { width: 100%; overflow: hidden; border-radius: 12px; background: #111; }
        .qr-status { margin-top: 14px; padding: 12px 14px; border-radius: 10px; background: #f3f4f6; color: #374151; line-height: 1.5; }
        .qr-status.success { background: #ecfdf5; color: #166534; }
        .qr-status.error { background: #fef2f2; color: #991b1b; }
        .qr-camera-note { margin-top: 10px; color: #6b7280; font-size: 14px; line-height: 1.5; }
        .qr-upload-box { margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
        .qr-upload-box label { display: block; margin-bottom: 12px; font-weight: 600; color: #374151; }
        .qr-camera-switch-btn { display: inline-block; padding: 8px 16px; font-size: 13px; font-weight: 600; color: #1e40af; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; cursor: pointer; transition: background 0.15s ease; }
        .qr-camera-switch-btn:hover { background: #dbeafe; }
        .qr-notice-box { margin-top: 12px; padding: 12px 14px; border-radius: 10px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px; line-height: 1.5; }
        .qr-action-buttons { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
        .qr-btn { padding: 10px 18px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: background 0.15s ease, border-color 0.15s ease; }
        .qr-btn-primary { background: #2563eb; color: #ffffff; border: 1px solid #2563eb; }
        .qr-btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .qr-btn-outline { background: #ffffff; color: #374151; border: 1px solid #d1d5db; }
        .qr-btn-outline:hover { background: #f9fafb; border-color: #9ca3af; }
        .qr-upload-note { margin-top: 8px; color: #6b7280; font-size: 14px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="app-shell">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php include __DIR__ . '/partials/notif-count.php'; ?>
        <?php include __DIR__ . '/partials/topbar.php'; ?>
        <main class="dashboard-content">
            <div class="dashboard-container">
                <div class="page-header">
                    <div class="breadcrumb">
                        <a href="dashboard.php">Dashboard</a>
                        <span>/</span>
                        <span>Presensi Guru</span>
                    </div>
                    <h1 class="page-title">Presensi Guru</h1>
                    <p class="page-description">Lakukan presensi datang dan pulang menggunakan QR sekolah.</p>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?= e($success) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <div class="dashboard-hero">
                    <div class="hero-date"><?= e(formatTanggal($tanggalHariIni)) ?></div>
                    <div class="dashboard-hero-content">
                        <div class="hero-eyebrow">PRESENSI HARI INI</div>
                        <h2 class="hero-title"><?= e($guru['nama_lengkap']) ?></h2>
                        <p class="hero-greeting"><?= e($guru['nama_mapel'] ?? 'Mata pelajaran belum ditentukan') ?></p>
                        <p class="hero-description">Scan QR yang ditampilkan pada layar sekolah untuk mencatat kehadiran Anda.</p>
                        <div class="hero-line"></div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-card-top">
                            <div class="stat-icon"><?= appIcon('arrow-up', '', 24) ?></div>
                            <div class="stat-label">Presensi Datang</div>
                        </div>
                        <div class="stat-value"><?= $sudahDatang ? e(formatJam($presensiHariIni['waktu_masuk'])) : '--' ?></div>
                        <div class="stat-description"><?= $sudahDatang ? 'Sudah tercatat' : 'Belum presensi datang' ?></div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-card-top">
                            <div class="stat-icon"><?= appIcon('arrow-down', '', 24) ?></div>
                            <div class="stat-label">Presensi Pulang</div>
                        </div>
                        <div class="stat-value"><?= $sudahPulang ? e(formatJam($presensiHariIni['waktu_pulang'])) : '--' ?></div>
                        <div class="stat-description"><?= $sudahPulang ? 'Sudah tercatat' : ($sudahDatang ? 'Menunggu presensi pulang' : 'Belum tersedia') ?></div>
                    </div>

                    <div class="stat-card purple">
                        <div class="stat-card-top">
                            <div class="stat-icon"><?= appIcon('check', '', 24) ?></div>
                            <div class="stat-label">Status Hari Ini</div>
                        </div>
                        <div class="stat-value"><?= $izinHariIni ? 'Izin' : ($presensiHariIni ? e($presensiHariIni['status']) : 'Belum') ?></div>
                        <div class="stat-description">Status presensi guru</div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="dashboard-card-title"><span class="dashboard-card-title-icon"><?= appIcon('check', '', 18) ?></span> Presensi Hari Ini</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <?php if ($izinHariIni): ?>
                            <div class="attendance-status warning">
                                <div class="attendance-icon"><?= appIcon('alert', '', 20) ?></div>
                                <div class="attendance-main">
                                    <div class="attendance-title">Izin Disetujui</div>
                                    <div class="attendance-time">
                                        Anda memiliki izin yang disetujui untuk hari ini.
                                        <?php if (!empty($izinHariIni['jenis_izin'])): ?>
                                            Jenis: <strong><?= e($izinHariIni['jenis_izin']) ?></strong>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($izinHariIni['alasan'])): ?>
                                <div class="attendance-status">
                                    <div class="attendance-icon"><?= appIcon('info', '', 20) ?></div>
                                    <div class="attendance-main">
                                        <div class="attendance-title">Alasan</div>
                                        <div class="attendance-time"><?= e($izinHariIni['alasan']) ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif (!$sudahDatang): ?>
                            <div class="attendance-status warning">
                                <div class="attendance-icon"><?= appIcon('alert', '', 20) ?></div>
                                <div class="attendance-main">
                                    <div class="attendance-title">Belum Presensi Datang</div>
                                    <div class="attendance-time">Scan QR sekolah untuk melakukan presensi datang.</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="attendance-status">
                                <div class="attendance-icon"><?= appIcon('check', '', 20) ?></div>
                                <div class="attendance-main">
                                    <div class="attendance-title">Presensi Hari Ini Tercatat</div>
                                    <div class="attendance-time">Status: <strong><?= e($presensiHariIni['status']) ?></strong></div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$izinHariIni && !$sudahPulang): ?>
                            <div class="dashboard-card" style="margin-top: 24px;">
                                <div class="dashboard-card-header">
                                    <h2 class="dashboard-card-title"><span class="dashboard-card-title-icon"><?= appIcon('qr', '', 18) ?></span> Scan QR Presensi</h2>
                                </div>
                                <div class="dashboard-card-body">
                                    <div class="qr-instruction">
                                        <ol class="qr-step-list">
                                            <li>Pastikan Anda sudah login menggunakan akun Guru sendiri.</li>
                                            <li>Buka kamera bawaan HP Anda.</li>
                                            <li>Arahkan kamera HP ke QR yang tampil pada layar sekolah.</li>
                                            <li>Buka tautan yang muncul setelah QR berhasil terbaca.</li>
                                            <li>Sistem akan mencatat presensi sesuai waktu server.</li>
                                        </ol>
                                    </div>

                                    <div class="qr-scanner-box">
                                        <div id="qr-reader"></div>
                                        <div id="qr-camera-controls" style="display: none; margin-top: 10px; text-align: center;">
                                            <button type="button" id="btn-switch-camera" class="qr-camera-switch-btn">Ganti Kamera (Belakang / Depan)</button>
                                        </div>
                                        <div id="qr-status" class="qr-status">Menyiapkan kamera...</div>
                                        <div id="qr-http-notice" class="qr-notice-box" style="display: none;">
                                            <div style="font-weight: 700; margin-bottom: 6px;">Kamera Live Memerlukan HTTPS</div>
                                            <p style="margin-bottom: 10px;">Browser memblokir streaming kamera live pada jaringan lokal tanpa HTTPS. Anda dapat mengaktifkan kamera live dengan beralih ke versi aman:</p>
                                            <a id="btn-switch-https" href="#" class="qr-btn qr-btn-primary" style="padding: 8px 16px; font-size: 13px; text-decoration: none; display: inline-flex;">Beralih ke HTTPS (Aktifkan Kamera Live)</a>
                                            <p style="margin-top: 8px; font-size: 12px; color: #78350f;"><em>*Jika browser menampilkan peringatan keamanan, klik <strong>Lanjutan (Advanced)</strong> lalu pilih <strong>Lanjutkan / Proceed</strong> satu kali.</em></p>
                                        </div>
                                        <div class="qr-camera-note">Kamera digunakan hanya untuk membaca QR presensi. Identitas Guru tetap diambil dari akun yang sedang login.</div>
                                    </div>

                                    <div class="qr-upload-box">
                                        <label>Opsi Alternatif Presensi (Kamera HP / Galeri)</label>
                                        <div class="qr-action-buttons">
                                            <input type="file" id="qr-camera-capture" accept="image/*" capture="environment" style="display: none;">
                                            <button type="button" id="btn-trigger-capture" class="qr-btn qr-btn-primary">Ambil Foto QR (Kamera HP)</button>
                                            <input type="file" id="qr-image" accept="image/*" style="display: none;">
                                            <button type="button" id="btn-trigger-gallery" class="qr-btn qr-btn-outline">Pilih Foto dari Galeri</button>
                                        </div>
                                        <div class="qr-upload-note">Tekan <strong>Ambil Foto QR</strong> untuk memotret QR pada layar sekolah, atau pilih tangkapan layar dari galeri.</div>
                                        <div id="qr-file-reader" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($sudahPulang): ?>
                            <div class="alert alert-success" style="margin-top: 24px;">Presensi datang dan pulang hari ini sudah tercatat.</div>
                        <?php endif; ?>

                        <?php if ($presensiHariIni && !empty($presensiHariIni['keterangan'])): ?>
                            <div class="attendance-status">
                                <div class="attendance-icon"><?= appIcon('info', '', 20) ?></div>
                                <div class="attendance-main">
                                    <div class="attendance-title">Keterangan</div>
                                    <div class="attendance-time"><?= e($presensiHariIni['keterangan']) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="dashboard-card-title"><span class="dashboard-card-title-icon"><?= appIcon('info', '', 18) ?></span> Detail Presensi</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="schedule-table-wrap">
                            <table class="schedule-table">
                                <thead>
                                    <tr>
                                        <th>Keterangan</th>
                                        <th>Waktu</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="schedule-subject">Presensi Datang</td>
                                        <td class="schedule-time"><?= $sudahDatang ? e(formatJam($presensiHariIni['waktu_masuk'])) : '-' ?></td>
                                        <td>
                                            <?php if ($sudahDatang): ?>
                                                <span class="status-badge success">Tercatat</span>
                                            <?php else: ?>
                                                <span class="status-badge warning">Belum</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="schedule-subject">Presensi Pulang</td>
                                        <td class="schedule-time"><?= $sudahPulang ? e(formatJam($presensiHariIni['waktu_pulang'])) : '-' ?></td>
                                        <td>
                                            <?php if ($sudahPulang): ?>
                                                <span class="status-badge success">Tercatat</span>
                                            <?php elseif ($sudahDatang): ?>
                                                <span class="status-badge warning">Menunggu</span>
                                            <?php else: ?>
                                                <span class="status-badge warning">Belum</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="dashboard-card-title"><span class="dashboard-card-title-icon"><?= appIcon('info', '', 18) ?></span> Informasi Presensi</h2>
                    </div>
                    <div class="dashboard-card-body">
                        <div class="notification-list">
                            <div class="notification-item">
                                <div class="notification-marker"></div>
                                <div class="notification-content">
                                    <div class="notification-title">Presensi menggunakan QR</div>
                                    <div class="notification-meta">Guru melakukan presensi dengan memindai QR yang ditampilkan pada layar sekolah.</div>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-marker green"></div>
                                <div class="notification-content">
                                    <div class="notification-title">Identitas berdasarkan akun</div>
                                    <div class="notification-meta">Sistem menggunakan akun Guru yang sedang login untuk menentukan identitas presensi.</div>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-marker orange"></div>
                                <div class="notification-content">
                                    <div class="notification-title">QR bersifat sementara</div>
                                    <div class="notification-meta">QR memiliki masa berlaku terbatas dan hanya dapat digunakan satu kali.</div>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-marker"></div>
                                <div class="notification-content">
                                    <div class="notification-title">Waktu server sekolah</div>
                                    <div class="notification-meta">Waktu presensi dicatat berdasarkan waktu server sekolah.</div>
                                </div>
                            </div>
                            <div class="notification-item">
                                <div class="notification-marker"></div>
                                <div class="notification-content">
                                    <div class="notification-title">Urutan presensi</div>
                                    <div class="notification-meta">Scan pertama mencatat waktu masuk. Scan berikutnya mencatat waktu pulang.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <a href="dashboard.php" class="back-link">&larr; Kembali ke Dashboard</a>
            </div>
        </main>
    </div>
</div>

<script src="../assets/js/html5-qrcode.min.js" type="text/javascript"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const readerElement = document.getElementById('qr-reader');
    const fileReaderElement = document.getElementById('qr-file-reader');
    const statusElement = document.getElementById('qr-status');
    const httpNoticeElement = document.getElementById('qr-http-notice');
    const btnSwitchHttps = document.getElementById('btn-switch-https');
    const cameraControlsElement = document.getElementById('qr-camera-controls');
    const switchCameraBtn = document.getElementById('btn-switch-camera');
    const cameraCaptureInput = document.getElementById('qr-camera-capture');
    const btnTriggerCapture = document.getElementById('btn-trigger-capture');
    const galleryInput = document.getElementById('qr-image');
    const btnTriggerGallery = document.getElementById('btn-trigger-gallery');

    if (btnSwitchHttps) {
        btnSwitchHttps.href = window.location.href.replace(/^http:/i, 'https:');
    }

    if (!readerElement || !fileReaderElement || !statusElement) return;

    if (typeof Html5Qrcode === 'undefined') {
        tampilStatus('Scanner QR tidak dapat dimuat. Periksa file assets/js/html5-qrcode.min.js.', 'error');
        return;
    }

    const cameraScanner = new Html5Qrcode('qr-reader');
    const fileScanner = new Html5Qrcode('qr-file-reader');
    let kameraAktif = false;
    let sudahScan = false;
    let daftarKamera = [];
    let indeksKameraAktif = 0;

    function tampilStatus(pesan, tipe = '') {
        statusElement.className = 'qr-status' + (tipe ? ' ' + tipe : '');
        statusElement.textContent = pesan;
    }

    function validasiDanDapatkanUrlScan(decodedText) {
        if (!decodedText || typeof decodedText !== 'string') return null;
        const text = decodedText.trim();
        let token = '';

        if (text.startsWith('http://') || text.startsWith('https://')) {
            try {
                const parsedUrl = new URL(text);
                token = parsedUrl.searchParams.get('token') || '';
            } catch (e) {
                token = '';
            }
        }

        if (!token) {
            const match = text.match(/[?&]token=([a-f0-9]{64})/i);
            if (match) token = match[1];
        }

        if (!token && /^[a-f0-9]{64}$/i.test(text)) {
            token = text;
        }

        if (token && /^[a-f0-9]{64}$/i.test(token)) {
            return '../qr/scan.php?token=' + encodeURIComponent(token);
        }
        return null;
    }

    function mainkanSuaraBeep() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;
            const ctx = new AudioContext();
            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            // Nada 1: Nada awal (900 Hz) - tegas dan jelas
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(900, ctx.currentTime);
            gain1.gain.setValueAtTime(0.55, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.10);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.10);

            // Nada 2: Nada konfirmasi sukses (1300 Hz) - jernih dan ramah
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(1300, ctx.currentTime + 0.08);
            gain2.gain.setValueAtTime(0.65, ctx.currentTime + 0.08);
            gain2.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.24);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(ctx.currentTime + 0.08);
            osc2.stop(ctx.currentTime + 0.24);
        } catch (e) {}
    }

    function getarHp() {
        try {
            if ('vibrate' in navigator) {
                navigator.vibrate([120, 60, 120]);
            }
        } catch (e) {}
    }

    function prosesHasilQr(decodedText) {
        if (sudahScan) return;
        const urlScan = validasiDanDapatkanUrlScan(decodedText);
        if (!urlScan) {
            tampilStatus('QR tidak dikenali sebagai QR Presensi Guru sekolah.', 'error');
            return;
        }

        sudahScan = true;
        mainkanSuaraBeep();
        getarHp();
        tampilStatus('QR berhasil dibaca. Memproses presensi...', 'success');

        if (kameraAktif) {
            cameraScanner.stop()
                .then(function () {
                    kameraAktif = false;
                    window.location.href = urlScan;
                })
                .catch(function () {
                    kameraAktif = false;
                    window.location.href = urlScan;
                });
        } else {
            window.location.href = urlScan;
        }
    }

    function pilihKameraBelakang(cameras) {
        const lowerLabels = ['back', 'rear', 'belakang', 'environment'];
        for (let i = 0; i < cameras.length; i++) {
            const label = (cameras[i].label || '').toLowerCase();
            if (lowerLabels.some(function (k) { return label.includes(k); })) {
                return i;
            }
        }
        return cameras.length > 1 ? cameras.length - 1 : 0;
    }

    function jalankanKamera(cameraId) {
        return cameraScanner.start(
            cameraId,
            { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1.0 },
            function (decodedText) { prosesHasilQr(decodedText); },
            function () {}
        );
    }

    function mulaiKamera() {
        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (window.location.protocol === 'http:' && !isLocal && httpNoticeElement) {
            httpNoticeElement.style.display = 'block';
        }

        tampilStatus('Menghubungkan kamera...');

        Html5Qrcode.getCameras()
            .then(function (cameras) {
                if (!cameras || cameras.length === 0) {
                    tampilStatus('Kamera tidak ditemukan. Gunakan tombol Ambil Foto QR.', 'error');
                    return;
                }
                daftarKamera = cameras;
                indeksKameraAktif = pilihKameraBelakang(cameras);

                if (daftarKamera.length > 1 && cameraControlsElement) {
                    cameraControlsElement.style.display = 'block';
                }

                const targetCameraId = daftarKamera[indeksKameraAktif].id;
                return jalankanKamera(targetCameraId);
            })
            .then(function () {
                if (!sudahScan) {
                    kameraAktif = true;
                    readerElement.style.display = 'block';
                    tampilStatus('Kamera aktif. Arahkan ke QR sekolah.', 'success');
                }
            })
            .catch(function (error) {
                console.warn('Akses kamera live terhambat:', error);
                kameraAktif = false;
                if (window.location.protocol === 'http:' && !isLocal) {
                    readerElement.style.display = 'none';
                    tampilStatus('Kamera live memerlukan koneksi HTTPS. Klik tombol "Beralih ke HTTPS" di atas atau gunakan tombol "Ambil Foto QR (Kamera HP)" di bawah.', 'error');
                } else {
                    tampilStatus('Kamera tidak dapat digunakan. Pastikan izin kamera telah diberikan pada browser.', 'error');
                }
            });
    }

    if (switchCameraBtn) {
        switchCameraBtn.addEventListener('click', function () {
            if (daftarKamera.length <= 1) return;
            indeksKameraAktif = (indeksKameraAktif + 1) % daftarKamera.length;
            tampilStatus('Mengalihkan kamera...');
            const targetCameraId = daftarKamera[indeksKameraAktif].id;

            if (kameraAktif) {
                cameraScanner.stop()
                    .then(function () { return jalankanKamera(targetCameraId); })
                    .then(function () {
                        kameraAktif = true;
                        tampilStatus('Kamera dialihkan. Arahkan ke QR sekolah.', 'success');
                    })
                    .catch(function (err) {
                        console.error('Gagal ganti kamera:', err);
                        tampilStatus('Gagal beralih kamera. Coba gunakan tombol Ambil Foto QR.', 'error');
                    });
            } else {
                jalankanKamera(targetCameraId)
                    .then(function () {
                        kameraAktif = true;
                        tampilStatus('Kamera aktif. Arahkan ke QR sekolah.', 'success');
                    })
                    .catch(function (err) {
                        console.error('Gagal membuka kamera:', err);
                    });
            }
        });
    }

    function muatGambarKeCanvas(file, maxDimension) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const img = new Image();
                img.onload = function () {
                    let width = img.naturalWidth || img.width;
                    let height = img.naturalHeight || img.height;
                    if (width <= maxDimension && height <= maxDimension) {
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        return resolve(canvas);
                    }
                    if (width > height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, width, height);
                    resolve(canvas);
                };
                img.onerror = function () { reject(new Error('Gagal memproses gambar.')); };
                img.src = e.target.result;
            };
            reader.onerror = function () { reject(new Error('Gagal membaca berkas.')); };
            reader.readAsDataURL(file);
        });
    }

    function canvasKeFile(canvas) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) {
                resolve(new File([blob], 'qr_processed.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.92);
        });
    }

    async function prosesFileGambar(file) {
        if (!file) return;
        sudahScan = false;
        tampilStatus('Membaca QR dari gambar...');
        try {
            const canvas1200 = await muatGambarKeCanvas(file, 1200);
            const file1200 = await canvasKeFile(canvas1200);
            try {
                const decoded1 = await fileScanner.scanFile(file1200, false);
                prosesHasilQr(decoded1);
                return;
            } catch (err1) {}

            const canvas800 = await muatGambarKeCanvas(file, 800);
            const file800 = await canvasKeFile(canvas800);
            try {
                const decoded2 = await fileScanner.scanFile(file800, false);
                prosesHasilQr(decoded2);
                return;
            } catch (err2) {}

            const decodedOriginal = await fileScanner.scanFile(file, false);
            prosesHasilQr(decodedOriginal);
        } catch (finalError) {
            console.error('Pemindaian file QR gagal:', finalError);
            tampilStatus('QR tidak ditemukan di gambar tersebut. Pastikan foto tegak, fokus, dan tidak terpotong.', 'error');
        }
    }

    if (btnTriggerCapture && cameraCaptureInput) {
        btnTriggerCapture.addEventListener('click', function () { cameraCaptureInput.click(); });
        cameraCaptureInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (file) prosesFileGambar(file);
            cameraCaptureInput.value = '';
        });
    }

    if (btnTriggerGallery && galleryInput) {
        btnTriggerGallery.addEventListener('click', function () { galleryInput.click(); });
        galleryInput.addEventListener('change', function (event) {
            const file = event.target.files && event.target.files[0];
            if (file) prosesFileGambar(file);
            galleryInput.value = '';
        });
    }

    mulaiKamera();
});
</script>
</body>
</html>