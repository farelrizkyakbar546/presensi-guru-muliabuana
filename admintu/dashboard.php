<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('adminTu');



date_default_timezone_set('Asia/Jakarta');









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





$stmt = $pdo->query("

    SELECT COUNT(*)

    FROM mapel

");



$totalMapel = (int) $stmt->fetchColumn();





$stmt = $pdo->query("

    SELECT COUNT(*)

    FROM perizinan

    WHERE status = 'Menunggu'

");



$totalIzinMenunggu = (int) $stmt->fetchColumn();









$activeMenu   = 'dashboard';

$pageTitle    = '';

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

        Dashboard Admin TU - SMKS Mulia Buana

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





                



                <section class="welcome-hero">



                    <div class="welcome-hero-content">



                        <span class="welcome-hero-eyebrow">

                            Panel Admin TU

                        </span>





                        <h1 class="welcome-hero-title">



                            Selamat Datang,



                            <?= e(

                                currentUserName()

                            ) ?>



                        </h1>





                        <p class="welcome-hero-greeting">



                            Kelola data master, jadwal, perizinan,

                            dan akun pengguna sistem presensi

                            SMKS Mulia Buana.



                        </p>



                    </div>



                </section>





                



                <section class="stats-grid">





                    



                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("guru", "", 24) ?>

                            </div>





                            <div>



                                <div class="stat-label">

                                    Guru Aktif

                                </div>





                                <div class="stat-value">

                                    <?= $totalGuru ?>

                                </div>





                                <div class="stat-description">

                                    Total guru aktif

                                </div>



                            </div>



                        </div>



                    </div>





                    



                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("siswa", "", 24) ?> 

                            </div>





                            <div>



                                <div class="stat-label">

                                    Siswa Aktif

                                </div>





                                <div class="stat-value">

                                    <?= $totalSiswa ?>

                                </div>





                                <div class="stat-description">

                                    Total siswa aktif

                                </div>



                            </div>



                        </div>



                    </div>





                    



                    <div class="stat-card purple">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("kelas", "", 24) ?>

                            </div>





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



                            <div class="stat-icon">

                                <?= appIcon("perizinan", "", 24) ?>

                            </div>





                            <div>



                                <div class="stat-label">

                                    Izin Menunggu

                                </div>





                                <div class="stat-value">

                                    <?= $totalIzinMenunggu ?>

                                </div>





                                <div class="stat-description">

                                    Perlu persetujuan

                                </div>



                            </div>



                        </div>



                    </div>





                </section>





                



                <section class="stats-grid">





                    



                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("jurusan", "", 24) ?>

                            </div>





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





                    



                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("mapel", "", 24) ?>

                            </div>





                            <div>



                                <div class="stat-label">

                                    Mata Pelajaran

                                </div>





                                <div class="stat-value">

                                    <?= $totalMapel ?>

                                </div>





                                <div class="stat-description">

                                    Total mapel

                                </div>



                            </div>



                        </div>



                    </div>





                </section>





                



                <div class="dashboard-card">





                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("zap", "", 18) ?> 

                            </span>



                            Akses Cepat



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="quick-menu-grid">





                            



                            <a

                                href="guru.php"

                                class="quick-menu-card blue"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("guru", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Data Guru

                                </div>





                                <div class="quick-menu-description">

                                    Kelola data guru dan akun login.

                                </div>



                            </a>





                            



                            <a

                                href="siswa.php"

                                class="quick-menu-card green"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("siswa", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Data Siswa

                                </div>





                                <div class="quick-menu-description">

                                    Kelola data siswa per kelas.

                                </div>



                            </a>





                            



                            <a

                                href="jadwal.php"

                                class="quick-menu-card purple"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("jadwal", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Jadwal Mengajar

                                </div>





                                <div class="quick-menu-description">

                                    Atur jadwal mengajar guru.

                                </div>



                            </a>





                            



                            <a

                                href="jadwal-piket.php"

                                class="quick-menu-card orange"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("piket", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Jadwal Piket

                                </div>





                                <div class="quick-menu-description">

                                    Atur jadwal piket guru.

                                </div>



                            </a>





                            



                            <a

                                href="perizinan.php"

                                class="quick-menu-card blue"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("perizinan", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Approval Perizinan

                                </div>





                                <div class="quick-menu-description">

                                    Setujui atau tolak izin guru.

                                </div>



                            </a>





                            



                            <a

                                href="rekap.php"

                                class="quick-menu-card green"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("rekap", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Rekap Presensi

                                </div>





                                <div class="quick-menu-description">

                                    Lihat rekap kehadiran.

                                </div>



                            </a>





                        </div>



                    </div>



                </div>





                



                <div class="dashboard-card">





                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("mapel", "", 18) ?>

                            </span>



                            Master Data



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="quick-menu-grid">





                            



                            <a

                                href="kelas.php"

                                class="quick-menu-card purple"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("kelas", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Data Kelas

                                </div>





                                <div class="quick-menu-description">

                                    Kelola data kelas per jurusan.

                                </div>



                            </a>





                            



                            <a

                                href="jurusan.php"

                                class="quick-menu-card orange"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("jurusan", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Data Jurusan

                                </div>





                                <div class="quick-menu-description">

                                    Kelola data jurusan sekolah.

                                </div>



                            </a>





                            



                            <a

                                href="mapel.php"

                                class="quick-menu-card blue"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("mapel", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Mata Pelajaran

                                </div>





                                <div class="quick-menu-description">

                                    Kelola data mata pelajaran.

                                </div>



                            </a>





                        </div>



                    </div>



                </div>





                



                <div class="dashboard-card">





                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("profil", "", 18) ?>

                            </span>



                            Data & Akun



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="quick-menu-grid">





                            



                            <a

                                href="import.php"

                                class="quick-menu-card green"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("import", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Import Data

                                </div>





                                <div class="quick-menu-description">

                                    Import data dari file Excel/CSV.

                                </div>



                            </a>





                            



                            <a

                                href="export.php"

                                class="quick-menu-card blue"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("export", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Export Data

                                </div>





                                <div class="quick-menu-description">

                                    Export data ke file Excel/CSV.

                                </div>



                            </a>





                            



                            <a

                                href="users.php"

                                class="quick-menu-card purple"

                            >



                                <div class="quick-menu-icon">

                                    <?= appIcon("account", "", 20) ?>

                                </div>





                                <div class="quick-menu-title">

                                    Manajemen Akun

                                </div>





                                <div class="quick-menu-description">

                                    Kelola akun pengguna sistem.

                                </div>



                            </a>





                        </div>



                    </div>



                </div>





            </div>



        </main>





    </div>



</div>





</body>



</html>