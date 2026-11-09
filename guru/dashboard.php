<?php



date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();



$stmt = $pdo->prepare("
    SELECT
        g.id,
        g.nip,
        g.nama_lengkap,
        g.status,
        m.nama_mapel
    FROM guru g
    LEFT JOIN mapel m
        ON m.id = g.mapel_utama_id
    WHERE g.user_id = :user_id
      AND g.status = 'aktif'
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {

    flash(
        'error',
        'Data guru tidak ditemukan atau tidak aktif.'
    );

    redirect('../login.php');
}

$guruId = (int) $guru['id'];



$tanggalHariIni = date('Y-m');

$tanggalHariIni = date('Y-m-d');

$namaHari = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];

$hariIni = $namaHari[date('l')] ?? 'Senin';



$namaBulan = [
    1  => 'Januari',
    2  => 'Februari',
    3  => 'Maret',
    4  => 'April',
    5  => 'Mei',
    6  => 'Juni',
    7  => 'Juli',
    8  => 'Agustus',
    9  => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

$tanggalTampil =
    date('j') . ' ' .
    $namaBulan[(int) date('n')] . ' ' .
    date('Y');



$stmt = $pdo->prepare("
    SELECT
        id,
        status,
        waktu_masuk,
        waktu_pulang,
        keterangan
    FROM presensi_guru
    WHERE guru_id = :guru_id
      AND tanggal = :tanggal
    LIMIT 1
");

$stmt->execute([
    'guru_id' => $guruId,
    'tanggal' => $tanggalHariIni
]);

$presensiHariIni = $stmt->fetch(PDO::FETCH_ASSOC);



$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT mapel_id)
    FROM jadwal
    WHERE guru_id = :guru_id
");

$stmt->execute([
    'guru_id' => $guruId
]);

$totalMapel = (int) $stmt->fetchColumn();



$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT kelas_id)
    FROM jadwal
    WHERE guru_id = :guru_id
");

$stmt->execute([
    'guru_id' => $guruId
]);

$totalKelas = (int) $stmt->fetchColumn();



$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.jam_mulai,
        j.jam_selesai,
        m.nama_mapel,
        k.nama_kelas,
        jur.nama_jurusan
    FROM jadwal j

    INNER JOIN mapel m
        ON m.id = j.mapel_id

    INNER JOIN kelas k
        ON k.id = j.kelas_id

    LEFT JOIN jurusan jur
        ON jur.id = k.jurusan_id

    WHERE j.guru_id = :guru_id
      AND j.hari = :hari

    ORDER BY
        j.jam_mulai ASC
");

$stmt->execute([
    'guru_id' => $guruId,
    'hari' => $hariIni
]);

$jadwalHariIni = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalJadwalHariIni = count($jadwalHariIni);



$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.hari,
        j.jam_mulai,
        j.jam_selesai,
        m.nama_mapel,
        k.nama_kelas,
        jur.nama_jurusan
    FROM jadwal j

    INNER JOIN mapel m
        ON m.id = j.mapel_id

    INNER JOIN kelas k
        ON k.id = j.kelas_id

    LEFT JOIN jurusan jur
        ON jur.id = k.jurusan_id

    WHERE j.guru_id = :guru_id

    ORDER BY
        CASE j.hari
            WHEN 'Senin' THEN 1
            WHEN 'Selasa' THEN 2
            WHEN 'Rabu' THEN 3
            WHEN 'Kamis' THEN 4
            WHEN 'Jumat' THEN 5
            WHEN 'Sabtu' THEN 6
            ELSE 7
        END,
        j.jam_mulai ASC
");

$stmt->execute([
    'guru_id' => $guruId
]);

$jadwalMingguIni = $stmt->fetchAll(PDO::FETCH_ASSOC);



$jadwalPerHari = [];

foreach ($jadwalMingguIni as $jadwal) {

    $hari = $jadwal['hari'];

    if (!isset($jadwalPerHari[$hari])) {
        $jadwalPerHari[$hari] = [];
    }

    $jadwalPerHari[$hari][] = $jadwal;
}



$stmt = $pdo->prepare("
    SELECT
        id,
        hari,
        keterangan
    FROM jadwal_piket
    WHERE guru_id = :guru_id
      AND hari = :hari
    LIMIT 1
");

$stmt->execute([
    'guru_id' => $guruId,
    'hari' => $hariIni
]);

$piketHariIni = $stmt->fetch(PDO::FETCH_ASSOC);

$isPiketHariIni = !empty($piketHariIni);



$statusPresensi = 'Belum Presensi';
$statusPresensiClass = 'secondary';

if ($presensiHariIni) {

    if (!empty($presensiHariIni['status'])) {
        $statusPresensi = $presensiHariIni['status'];
    }

    $statusMap = [
        'Hadir' => 'success',
        'Izin' => 'warning',
        'Alfa' => 'danger'
    ];

    $statusPresensiClass =
        $statusMap[$statusPresensi]
        ?? 'secondary';
}



$hariSekolah = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu'
];



$activeMenu = 'dashboard';
$pageTitle = '';
$pageSubtitle = '';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard Guru - SMKS Mulia Buana
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>

<div class="app-shell">

    

    <?php include __DIR__ . '/partials/sidebar.php'; ?>


    

    <div class="app-main">

        

        <?php include __DIR__ . '/partials/notif-count.php'; ?>

        <?php include __DIR__ . '/partials/topbar.php'; ?>


        

        <main class="dashboard-content">

            <div class="dashboard-container">


                

                <section class="dashboard-hero">

                    <div class="hero-date"><?= appIcon("calendar", "", 14) ?> <?= e($hariIni) ?>,
                        <?= e($tanggalTampil) ?>
                    </div>


                    <div class="dashboard-hero-content">

                        <span class="hero-eyebrow">
                            Sistem Informasi Sekolah
                        </span>


                        <h1 class="hero-title">
                            Selamat Datang,
                        </h1>


                        <p class="hero-greeting">
                            <?= e($guru['nama_lengkap']) ?>
                        </p>


                        <p class="hero-description">
                            Sistem Digitalisasi Presensi dan Monitoring
                            Kehadiran Guru berbasis web untuk mendukung
                            pengelolaan kehadiran secara lebih tertib
                            dan terintegrasi.
                        </p>


                        <div class="hero-line"></div>

                    </div>

                </section>


                

                <section class="stats-grid">


                    <div class="stat-card blue">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('mapel', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Mapel
                                </div>

                                <div class="stat-value">
                                    <?= $totalMapel ?>
                                </div>

                                <div class="stat-description">
                                    Mata Pelajaran
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="stat-card green">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('kelas', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Kelas
                                </div>

                                <div class="stat-value">
                                    <?= $totalKelas ?>
                                </div>

                                <div class="stat-description">
                                    Kelas yang diajar
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="stat-card purple">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('jadwal', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Jadwal Hari Ini
                                </div>

                                <div class="stat-value">
                                    <?= $totalJadwalHariIni ?>
                                </div>

                                <div class="stat-description">
                                    Periode mengajar
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="stat-card orange">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('check', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Status Presensi
                                </div>

                                <div class="stat-value">
                                    <?= e($statusPresensi) ?>
                                </div>

                                <div class="stat-description">

                                    <?php if (
                                        $presensiHariIni &&
                                        !empty(
                                            $presensiHariIni['waktu_masuk']
                                        )
                                    ): ?>

                                        Pukul
                                        <?= e(
                                            formatJam(
                                                $presensiHariIni[
                                                    'waktu_masuk'
                                                ]
                                            )
                                        ) ?>
                                        WIB

                                    <?php else: ?>

                                        Belum melakukan presensi

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>

                    </div>

                </section>


                

                <?php if ($isPiketHariIni): ?>

                    <section class="dashboard-card">

                        <div class="dashboard-card-body">

                            <div class="piket-card">

                                <div class="piket-card-content">

                                    <span class="piket-card-label">
                                        Tugas Hari Ini
                                    </span>

                                    <h3 class="piket-card-title">
                                        Anda Bertugas Sebagai Guru Piket
                                    </h3>

                                    <p class="piket-card-description">

                                        <?= e(
                                            !empty(
                                                $piketHariIni['keterangan']
                                            )
                                                ? $piketHariIni['keterangan']
                                                : 'Anda bertugas sebagai Guru Piket hari ini.'
                                        ) ?>

                                    </p>

                                </div>


                                <a
                                    href="dashboard_piket.php"
                                    class="btn btn-primary"
                                >
                                    Buka Dashboard Piket →
                                </a>

                            </div>

                        </div>

                    </section>

                <?php endif; ?>


                

                <section class="dashboard-grid">


                    

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h2 class="dashboard-card-title">

                                <span class="dashboard-card-title-icon">
                                    <?= appIcon('jadwal', '', 18) ?>
                                </span>

                                Jadwal Mengajar Hari Ini

                            </h2>


                            <a
                                href="jadwal.php"
                                class="dashboard-card-link"
                            >
                                Lihat Semua →
                            </a>

                        </div>


                        <div class="dashboard-card-body">

                            <?php if (empty($jadwalHariIni)): ?>

                                <div class="empty">
                                    Tidak ada jadwal mengajar hari ini.
                                </div>

                            <?php else: ?>

                                <div class="schedule-table-wrap">

                                    <table class="schedule-table">

                                        <thead>

                                            <tr>

                                                <th>No</th>
                                                <th>Waktu</th>
                                                <th>Mata Pelajaran</th>
                                                <th>Kelas</th>
                                                <th>Jurusan</th>
                                                <th>Status</th>

                                            </tr>

                                        </thead>


                                        <tbody>

                                        <?php foreach (
                                            $jadwalHariIni
                                            as $i => $jadwal
                                        ): ?>

                                            <tr>

                                                <td>
                                                    <?= $i + 1 ?>
                                                </td>


                                                <td class="schedule-time">

                                                    <?= e(
                                                        formatJam(
                                                            $jadwal[
                                                                'jam_mulai'
                                                            ]
                                                        )
                                                    ) ?>

                                                    -

                                                    <?= e(
                                                        formatJam(
                                                            $jadwal[
                                                                'jam_selesai'
                                                            ]
                                                        )
                                                    ) ?>

                                                </td>


                                                <td class="schedule-subject">

                                                    <?= e(
                                                        $jadwal[
                                                            'nama_mapel'
                                                        ]
                                                    ) ?>

                                                </td>


                                                <td class="schedule-class">

                                                    <?= e(
                                                        $jadwal[
                                                            'nama_kelas'
                                                        ]
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <?= e(
                                                        $jadwal[
                                                            'nama_jurusan'
                                                        ]
                                                        ?? '-'
                                                    ) ?>

                                                </td>


                                                <td>

                                                    <span
                                                        class="status-badge success"
                                                    >
                                                        Aktif
                                                    </span>

                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                        </tbody>

                                    </table>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>


                    

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h2 class="dashboard-card-title">

                                <span class="dashboard-card-title-icon">
                                    <?= appIcon('presensi', '', 18) ?>
                                </span>

                                Presensi Guru Hari Ini

                            </h2>


                            <a
                                href="riwayat.php"
                                class="dashboard-card-link"
                            >
                                Riwayat →
                            </a>

                        </div>


                        <div class="dashboard-card-body">


                            <?php if ($presensiHariIni): ?>

                                <div class="attendance-status">

                                    <div class="attendance-icon">
                                        <?= appIcon('check', '', 20) ?>
                                    </div>


                                    <div class="attendance-main">

                                        <div class="attendance-title">
                                            Presensi hari ini tercatat
                                        </div>


                                        <div class="attendance-time">

                                            Datang:

                                            <strong>

                                                <?=
                                                    !empty(
                                                        $presensiHariIni[
                                                            'waktu_masuk'
                                                        ]
                                                    )
                                                    ? e(
                                                        formatJam(
                                                            $presensiHariIni[
                                                                'waktu_masuk'
                                                            ]
                                                        )
                                                    )
                                                    : '-'
                                                ?>

                                            </strong>

                                            WIB

                                            <br>


                                            Pulang:

                                            <strong>

                                                <?php if (
                                                    !empty(
                                                        $presensiHariIni[
                                                            'waktu_pulang'
                                                        ]
                                                    )
                                                ): ?>

                                                    <?= e(
                                                        formatJam(
                                                            $presensiHariIni[
                                                                'waktu_pulang'
                                                            ]
                                                        )
                                                    ) ?>

                                                <?php else: ?>

                                                    Belum

                                                <?php endif; ?>

                                            </strong>

                                        </div>

                                    </div>

                                </div>


                                <div class="attendance-actions">

                                    <?php if (
                                        empty(
                                            $presensiHariIni[
                                                'waktu_pulang'
                                            ]
                                        )
                                    ): ?>

                                        <a
                                            href="presensi.php"
                                            class="btn btn-primary"
                                        >
                                            <?= appIcon('arrow-down', '', 16) ?> Presensi Pulang
                                        </a>

                                    <?php else: ?>

                                        <div class="btn btn-outline">
                                            <?= appIcon('check', '', 16) ?> Lengkap
                                        </div>

                                    <?php endif; ?>


                                    <a
                                        href="riwayat.php"
                                        class="btn btn-outline"
                                    >
                                        <?= appIcon('riwayat', '', 16) ?> Riwayat
                                    </a>

                                </div>


                            <?php else: ?>

                                <div class="attendance-status warning">

                                    <div class="attendance-icon">
                                        <?= appIcon('alert', '', 20) ?>
                                    </div>


                                    <div class="attendance-main">

                                        <div class="attendance-title">
                                            Anda belum presensi hari ini
                                        </div>


                                        <div class="attendance-time">
                                            Silakan lakukan presensi datang
                                            sebelum memulai aktivitas mengajar.
                                        </div>

                                    </div>

                                </div>


                                <div class="attendance-actions">

                                    <a
                                        href="presensi.php"
                                        class="btn btn-success"
                                    >
                                        <?= appIcon('check', '', 16) ?> Presensi Sekarang
                                    </a>


                                    <a
                                        href="riwayat.php"
                                        class="btn btn-outline"
                                    >
                                        <?= appIcon('riwayat', '', 16) ?> Riwayat
                                    </a>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>


                

                <section class="dashboard-grid-bottom">


                    

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h2 class="dashboard-card-title">

                                <span class="dashboard-card-title-icon">
                                    <?= appIcon('info', '', 18) ?>
                                </span>

                                Informasi Sekolah

                            </h2>

                        </div>


                        <div class="dashboard-card-body">

                            <div class="school-info">

                                <img
                                    src="../assets/img/bgsmks.jpg"
                                    alt="SMKS Mulia Buana"
                                    class="school-image"
                                >


                                <div>

                                    <h3 class="school-info-title">
                                        SMKS Mulia Buana
                                    </h3>


                                    <p class="school-info-text">
                                        Sekolah Menengah Kejuruan yang
                                        berkomitmen mencetak generasi unggul,
                                        berkarakter, dan siap menghadapi
                                        dunia kerja.
                                    </p>


                                    <div class="school-meta">

                                        <div class="school-meta-item">

                                            <span class="school-meta-icon"><?= appIcon("piket", "", 14) ?></span>

                                            <span>
                                                Jl. Pendidikan No. 12, Bogor
                                            </span>

                                        </div>


                                        <div class="school-meta-item">

                                            <span class="school-meta-icon"><?= appIcon("kelas", "", 14) ?></span>

                                            <span>
                                                Teknologi Informasi •
                                                Rekayasa Perangkat Lunak •
                                                Teknik Komputer dan Jaringan
                                            </span>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    

                    <div class="dashboard-card">

                        <div class="dashboard-card-header">

                            <h2 class="dashboard-card-title">

                                <span class="dashboard-card-title-icon">
                                    <?= appIcon('bell', '', 18) ?>
                                </span>

                                Notifikasi Terbaru

                            </h2>

                        </div>


                        <div class="dashboard-card-body">

                            <div class="notification-list">


                                

                                <div class="notification-item">

                                    <span class="notification-marker"></span>

                                    <div class="notification-content">

                                        <p class="notification-title">
                                            Jadwal mengajar Anda hari ini
                                            telah tersedia.
                                        </p>

                                        <div class="notification-meta">
                                            Hari ini
                                        </div>

                                    </div>

                                    <span class="notification-arrow">
                                        ›
                                    </span>

                                </div>


                                

                                <?php if ($isPiketHariIni): ?>

                                    <div class="notification-item">

                                        <span class="notification-marker orange"></span>

                                        <div class="notification-content">

                                            <p class="notification-title">
                                                Anda bertugas sebagai Guru
                                                Piket hari ini.
                                            </p>

                                            <div class="notification-meta">
                                                Hari ini
                                            </div>

                                        </div>

                                        <span class="notification-arrow">
                                            ›
                                        </span>

                                    </div>

                                <?php elseif ($presensiHariIni): ?>

                                    <div class="notification-item">

                                        <span class="notification-marker green"></span>

                                        <div class="notification-content">

                                            <p class="notification-title">
                                                Presensi Guru hari ini sudah
                                                tercatat.
                                            </p>

                                            <div class="notification-meta">
                                                Hari ini
                                            </div>

                                        </div>

                                        <span class="notification-arrow">
                                            ›
                                        </span>

                                    </div>

                                <?php else: ?>

                                    <div class="notification-item">

                                        <span class="notification-marker orange"></span>

                                        <div class="notification-content">

                                            <p class="notification-title">
                                                Anda belum melakukan presensi
                                                hari ini.
                                            </p>

                                            <div class="notification-meta">
                                                Hari ini
                                            </div>

                                        </div>

                                        <span class="notification-arrow">
                                            ›
                                        </span>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <a
                                href="notifikasi.php"
                                class="notification-show-more"
                            >
                                <span>
                                    Lihat Semua Notifikasi
                                </span>

                                <span class="notification-show-more-arrow">
                                    →
                                </span>
                            </a>

                        </div>

                    </div>

                </section>


                

                <section class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon('jadwal', '', 18) ?>
                            </span>

                            Jadwal Mengajar Minggu Ini

                        </h2>


                        <a
                            href="jadwal.php"
                            class="dashboard-card-link"
                        >
                            Lihat Jadwal →
                        </a>

                    </div>


                    <div class="dashboard-card-body">

                        <?php foreach (
                            $hariSekolah
                            as $hari
                        ): ?>

                            <?php

                            $jadwalHari =
                                $jadwalPerHari[$hari]
                                ?? [];

                            $isHariIni =
                                ($hari === $hariIni);

                            ?>


                            <div
                                class="schedule-day <?= $isHariIni ? 'today' : '' ?>"
                            >

                                <div class="schedule-day-header">

                                    <div>

                                        <span class="schedule-day-label">
                                            Hari
                                        </span>

                                        <h3>
                                            <?= e($hari) ?>
                                        </h3>

                                    </div>


                                    <?php if ($isHariIni): ?>

                                        <span class="today-badge">
                                            Hari Ini
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <?php if (empty($jadwalHari)): ?>

                                    <div class="schedule-empty">

                                        Tidak ada jadwal mengajar pada hari
                                        <?= e(strtolower($hari)) ?>.

                                    </div>

                                <?php else: ?>

                                    <div class="schedule-table-wrap">

                                        <table class="schedule-table">

                                            <thead>

                                                <tr>

                                                    <th>No</th>
                                                    <th>Waktu</th>
                                                    <th>Mata Pelajaran</th>
                                                    <th>Kelas</th>
                                                    <th>Jurusan</th>

                                                </tr>

                                            </thead>


                                            <tbody>

                                            <?php $nomor = 1; ?>


                                            <?php foreach (
                                                $jadwalHari
                                                as $jadwal
                                            ): ?>

                                                <tr>

                                                    <td>
                                                        <?= $nomor++ ?>
                                                    </td>


                                                    <td class="schedule-time">

                                                        <?= e(
                                                            formatJam(
                                                                $jadwal[
                                                                    'jam_mulai'
                                                                ]
                                                            )
                                                        ) ?>

                                                        -

                                                        <?= e(
                                                            formatJam(
                                                                $jadwal[
                                                                    'jam_selesai'
                                                                ]
                                                            )
                                                        ) ?>

                                                    </td>


                                                    <td class="schedule-subject">

                                                        <?= e(
                                                            $jadwal[
                                                                'nama_mapel'
                                                            ]
                                                        ) ?>

                                                    </td>


                                                    <td class="schedule-class">

                                                        <?= e(
                                                            $jadwal[
                                                                'nama_kelas'
                                                            ]
                                                        ) ?>

                                                    </td>


                                                    <td>

                                                        <?= e(
                                                            $jadwal[
                                                                'nama_jurusan'
                                                            ]
                                                            ?? '-'
                                                        ) ?>

                                                    </td>

                                                </tr>

                                            <?php endforeach; ?>

                                            </tbody>

                                        </table>

                                    </div>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>


            </div>

        </main>

    </div>

</div>

</body>

</html>