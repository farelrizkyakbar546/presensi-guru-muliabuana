<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('kepsek');



date_default_timezone_set('Asia/Jakarta');



$today = date('Y-m-d');







$stmt = $pdo->query("

    SELECT COUNT(*) 

    FROM guru 

    WHERE status = 'aktif'

");

$totalGuru = (int) $stmt->fetchColumn();



$stmt = $pdo->query("

    SELECT COUNT(*) 

    FROM siswa 

    WHERE status = 'aktif'

");

$totalSiswa = (int) $stmt->fetchColumn();



$stmt = $pdo->query("

    SELECT COUNT(*) 

    FROM kelas

");

$totalKelas = (int) $stmt->fetchColumn();



$stmt = $pdo->query("

    SELECT COUNT(*) 

    FROM jurusan

");

$totalJurusan = (int) $stmt->fetchColumn();













$stmt = $pdo->prepare("

    SELECT

        g.id,

        pg.status AS status_presensi,

        EXISTS (

            SELECT 1

            FROM perizinan p

            WHERE p.guru_id = g.id

              AND p.status = 'Disetujui'

              AND p.tanggal_mulai <= ?

              AND p.tanggal_selesai >= ?

        ) AS memiliki_izin

    FROM guru g

    LEFT JOIN presensi_guru pg

        ON pg.guru_id = g.id

        AND pg.tanggal = ?

    WHERE g.status = 'aktif'

");



$stmt->execute([

    $today,

    $today,

    $today

]);



$statusGuruHariIni = $stmt->fetchAll();



$guruHadir = 0;

$guruIzin = 0;

$guruAlfa = 0;



foreach ($statusGuruHariIni as $statusGuru) {



    if (!empty($statusGuru['status_presensi'])) {



        if ($statusGuru['status_presensi'] === 'Hadir') {

            $guruHadir++;

        } elseif ($statusGuru['status_presensi'] === 'Izin') {

            $guruIzin++;

        } elseif ($statusGuru['status_presensi'] === 'Alfa') {

            $guruAlfa++;

        }



        continue;

    }



    if ((int) $statusGuru['memiliki_izin'] === 1) {

        $guruIzin++;

    }

}



$totalPresensiGuru = $guruHadir + $guruIzin + $guruAlfa;









$stmt = $pdo->prepare("

    SELECT

        COUNT(*) AS total,

        SUM(status = 'Hadir') AS hadir,

        SUM(status = 'Izin') AS izin,

        SUM(status = 'Sakit') AS sakit,

        SUM(status = 'Alfa') AS alfa

    FROM presensi_siswa

    WHERE tanggal = :tanggal

");



$stmt->execute([

    ':tanggal' => $today

]);



$presensiSiswaHariIni = $stmt->fetch();



$totalPresensiSiswa = (int) ($presensiSiswaHariIni['total'] ?? 0);

$siswaHadir = (int) ($presensiSiswaHariIni['hadir'] ?? 0);

$siswaIzin = (int) ($presensiSiswaHariIni['izin'] ?? 0);

$siswaSakit = (int) ($presensiSiswaHariIni['sakit'] ?? 0);

$siswaAlfa = (int) ($presensiSiswaHariIni['alfa'] ?? 0);









$stmt = $pdo->query("

    SELECT

        SUM(status = 'Menunggu') AS menunggu,

        SUM(status = 'Disetujui') AS disetujui,

        SUM(status = 'Ditolak') AS ditolak

    FROM perizinan

");



$perizinan = $stmt->fetch();



$izinMenunggu = (int) ($perizinan['menunggu'] ?? 0);

$izinDisetujui = (int) ($perizinan['disetujui'] ?? 0);

$izinDitolak = (int) ($perizinan['ditolak'] ?? 0);









$stmt = $pdo->query("

    SELECT

        pg.tanggal,

        pg.waktu_masuk,

        pg.waktu_pulang,

        pg.status,

        pg.keterangan,

        g.nama_lengkap,

        g.nip

    FROM presensi_guru pg

    INNER JOIN guru g ON g.id = pg.guru_id

    ORDER BY pg.id DESC

    LIMIT 8

");



$presensiTerbaru = $stmt->fetchAll();









$stmt = $pdo->query("

    SELECT

        p.tanggal_mulai,

        p.tanggal_selesai,

        p.jenis_izin,

        p.status,

        g.nama_lengkap

    FROM perizinan p

    INNER JOIN guru g ON g.id = p.guru_id

    ORDER BY p.id DESC

    LIMIT 5

");



$perizinanTerbaru = $stmt->fetchAll();









$badgePresensiMap = [

    'Hadir' => 'success',

    'Izin'  => 'warning',

    'Alfa'  => 'danger',

];



$badgeIzinMap = [

    'Menunggu'  => 'warning',

    'Disetujui' => 'success',

    'Ditolak'   => 'danger',

];









$activeMenu   = 'dashboard';

$pageTitle    = '';

$pageSubtitle = '';

?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Dashboard Kepala Sekolah - SMKS Mulia Buana</title>



    <link rel="stylesheet" href="../assets/css/style.css">



</head>



<body>



<div class="app-shell">





    <?php include __DIR__ . '/partials/sidebar.php'; ?>





    <div class="app-main">





        <?php include __DIR__ . '/partials/notif-count.php'; ?>

        <?php include __DIR__ . '/partials/topbar.php'; ?>





        <main class="dashboard-content">



            <div class="dashboard-container">





                



                <section class="welcome-hero">



                    <div class="welcome-hero-content">



                        <span class="welcome-hero-eyebrow">

                            Panel Kepala Sekolah

                        </span>



                        <h1 class="welcome-hero-title">

                            Selamat Datang,

                            <?= e(currentUserName()) ?>

                        </h1>



                        <p class="welcome-hero-greeting">

                            Pantau kehadiran guru, siswa, dan perizinan

                            di SMKS Mulia Buana secara real-time.

                        </p>



                    </div>



                </section>





                



                <section class="stats-grid">





                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("guru", "", 24) ?></div>



                            <div>



                                <div class="stat-label">

                                    Guru Aktif

                                </div>



                                <div class="stat-value">

                                    <?= $totalGuru ?>

                                </div>



                                <div class="stat-description">

                                    Jumlah guru aktif

                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("siswa", "", 24) ?></div>



                            <div>



                                <div class="stat-label">

                                    Siswa Aktif

                                </div>



                                <div class="stat-value">

                                    <?= $totalSiswa ?>

                                </div>



                                <div class="stat-description">

                                    Jumlah siswa aktif

                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="stat-card purple">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("kelas", "", 24) ?></div>



                            <div>



                                <div class="stat-label">

                                    Kelas

                                </div>



                                <div class="stat-value">

                                    <?= $totalKelas ?>

                                </div>



                                <div class="stat-description">

                                    Total kelas

                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="stat-card orange">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("jurusan", "", 24) ?></div>



                            <div>



                                <div class="stat-label">

                                    Jurusan

                                </div>



                                <div class="stat-value">

                                    <?= $totalJurusan ?>

                                </div>



                                <div class="stat-description">

                                    Total jurusan

                                </div>



                            </div>



                        </div>



                    </div>



                </section>





                



                <section class="dashboard-grid">





                    <div class="dashboard-card">



                        <div class="dashboard-card-header">



                            <h2 class="dashboard-card-title">



                                <span class="dashboard-card-title-icon"><?= appIcon("guru", "", 18) ?></span>



                                Presensi Guru Hari Ini



                            </h2>



                        </div>





                        <div class="dashboard-card-body">



                            <div class="info-grid">



                                <div class="info-box">



                                    <div class="info-label">

                                        Hadir

                                    </div>



                                    <div class="info-value" style="color: #047857;">

                                        <?= $guruHadir ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Izin

                                    </div>



                                    <div class="info-value" style="color: #b45309;">

                                        <?= $guruIzin ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Alfa

                                    </div>



                                    <div class="info-value" style="color: #b91c1c;">

                                        <?= $guruAlfa ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Guru Aktif

                                    </div>



                                    <div class="info-value">

                                        <?= $totalGuru ?>

                                    </div>



                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="dashboard-card">



                        <div class="dashboard-card-header">



                            <h2 class="dashboard-card-title">



                                <span class="dashboard-card-title-icon"><?= appIcon("siswa", "", 18) ?></span>



                                Presensi Siswa Hari Ini



                            </h2>



                        </div>





                        <div class="dashboard-card-body">



                            <div class="info-grid">



                                <div class="info-box">



                                    <div class="info-label">

                                        Hadir

                                    </div>



                                    <div class="info-value" style="color: #047857;">

                                        <?= $siswaHadir ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Izin

                                    </div>



                                    <div class="info-value" style="color: #b45309;">

                                        <?= $siswaIzin ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Sakit

                                    </div>



                                    <div class="info-value" style="color: #1d4ed8;">

                                        <?= $siswaSakit ?>

                                    </div>



                                </div>





                                <div class="info-box">



                                    <div class="info-label">

                                        Alfa

                                    </div>



                                    <div class="info-value" style="color: #b91c1c;">

                                        <?= $siswaAlfa ?>

                                    </div>



                                </div>



                            </div>



                        </div>



                    </div>



                </section>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("perizinan", "", 18) ?>

                            </span>



                            Ringkasan Perizinan Guru



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="info-grid">



                            <div class="info-box">



                                <div class="info-label">

                                    Menunggu

                                </div>



                                <div class="info-value" style="color: #b45309;">

                                    <?= $izinMenunggu ?>

                                </div>



                            </div>





                            <div class="info-box">



                                <div class="info-label">

                                    Disetujui

                                </div>



                                <div class="info-value" style="color: #047857;">

                                    <?= $izinDisetujui ?>

                                </div>



                            </div>





                            <div class="info-box">



                                <div class="info-label">

                                    Ditolak

                                </div>



                                <div class="info-value" style="color: #b91c1c;">

                                    <?= $izinDitolak ?>

                                </div>



                            </div>





                            <div class="info-box">



                                <div class="info-label">

                                    Total Pengajuan

                                </div>



                                <div class="info-value">

                                    <?= $izinMenunggu + $izinDisetujui + $izinDitolak ?>

                                </div>



                            </div>



                        </div>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("zap", "", 18) ?>

                            </span>



                            Menu Monitoring



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="quick-menu-grid">





                            <a href="monitoring-guru.php" class="quick-menu-card blue">



                                <div class="quick-menu-icon"><?= appIcon("guru", "", 20) ?></div>



                                <div class="quick-menu-title">

                                    Monitoring Guru

                                </div>



                                <div class="quick-menu-description">

                                    Lihat kehadiran & status presensi guru.

                                </div>



                            </a>





                            <a href="monitoring-siswa.php" class="quick-menu-card green">



                                <div class="quick-menu-icon"><?= appIcon("siswa", "", 20) ?></div>



                                <div class="quick-menu-title">

                                    Monitoring Siswa

                                </div>



                                <div class="quick-menu-description">

                                    Lihat rekap kehadiran siswa.

                                </div>



                            </a>





                            <a href="perizinan.php" class="quick-menu-card orange">



                                <div class="quick-menu-icon"><?= appIcon("perizinan", "", 20) ?></div>



                                <div class="quick-menu-title">

                                    Monitoring Perizinan

                                </div>



                                <div class="quick-menu-description">

                                    Lihat status pengajuan izin guru.

                                </div>



                            </a>





                            <a href="rekap.php" class="quick-menu-card purple">



                                <div class="quick-menu-icon"><?= appIcon("rekap", "", 20) ?></div>



                                <div class="quick-menu-title">

                                    Rekap Presensi

                                </div>



                                <div class="quick-menu-description">

                                    Ringkasan presensi sekolah.

                                </div>



                            </a>



                        </div>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("rekap", "", 18) ?>

                            </span>



                            Presensi Guru Terbaru



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>

                                        <th>Guru</th>

                                        <th>Tanggal</th>

                                        <th>Waktu</th>

                                        <th>Status</th>

                                        <th>Keterangan</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$presensiTerbaru): ?>



                                    <tr>



                                        <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-500);">



                                            Belum ada data presensi guru.



                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($presensiTerbaru as $index => $data): ?>



                                        <?php



                                        $statusClass =

                                            $badgePresensiMap[$data['status']]

                                            ?? 'secondary';



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>



                                            <td>



                                                <span class="schedule-subject">

                                                    <?= e($data['nama_lengkap']) ?>

                                                </span>



                                                <div class="schedule-code">

                                                    NIP: <?= e($data['nip'] ?: '-') ?>

                                                </div>



                                            </td>



                                            <td>

                                                <?= e(formatTanggal($data['tanggal'])) ?>

                                            </td>



                                            <td class="schedule-time">

                                                <?= e(formatJam($data['waktu_masuk'])) ?>

                                            </td>



                                            <td>



                                                <span class="status-badge <?= e($statusClass) ?>">

                                                    <?= e($data['status']) ?>

                                                </span>



                                            </td>



                                            <td>

                                                <?= e($data['keterangan'] ?: '-') ?>

                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                <?php endif; ?>



                                </tbody>



                            </table>



                        </div>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("clock", "", 18) ?>

                            </span>



                            Perizinan Guru Terbaru



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>

                                        <th>Guru</th>

                                        <th>Periode</th>

                                        <th>Jenis</th>

                                        <th>Status</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$perizinanTerbaru): ?>



                                    <tr>



                                        <td colspan="5" style="text-align:center; padding: 30px; color: var(--text-500);">



                                            Belum ada data perizinan.



                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($perizinanTerbaru as $index => $izin): ?>



                                        <?php



                                        $badgeClass =

                                            $badgeIzinMap[$izin['status']]

                                            ?? 'secondary';



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>



                                            <td class="schedule-subject">

                                                <?= e($izin['nama_lengkap']) ?>

                                            </td>



                                            <td>



                                                <?= e(formatTanggal($izin['tanggal_mulai'])) ?>



                                                <br>



                                                <span style="color: var(--text-400); font-size: 10px;">

                                                    s/d

                                                </span>



                                                <br>



                                                <?= e(formatTanggal($izin['tanggal_selesai'])) ?>



                                            </td>



                                            <td>

                                                <?= e($izin['jenis_izin']) ?>

                                            </td>



                                            <td>



                                                <span class="status-badge <?= e($badgeClass) ?>">

                                                    <?= e($izin['status']) ?>

                                                </span>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                <?php endif; ?>



                                </tbody>



                            </table>



                        </div>



                    </div>



                </div>





            </div>



        </main>



    </div>



</div>



</body>



</html>