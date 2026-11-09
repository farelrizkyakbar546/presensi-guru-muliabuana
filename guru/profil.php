<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();


$stmt = $pdo->prepare("
    SELECT
        u.username,
        u.nama_lengkap AS nama_akun,
        u.role,
        u.status AS status_akun,

        g.id AS guru_id,
        g.nip,
        g.nama_lengkap AS nama_guru,
        g.status AS status_guru,

        m.nama_mapel

    FROM users u

    LEFT JOIN guru g
        ON g.user_id = u.id

    LEFT JOIN mapel m
        ON m.id = g.mapel_utama_id

    WHERE u.id = :user_id

    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$profil = $stmt->fetch();

if (!$profil) {
    die('Data profil tidak ditemukan.');
}




$namaHari = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

$hariIni = $namaHari[date('l')];

$stmtPiket = $pdo->prepare("
    SELECT id
    FROM jadwal_piket
    WHERE guru_id = :guru_id
      AND hari = :hari
    LIMIT 1
");

$stmtPiket->execute([
    'guru_id' => $profil['guru_id'],
    'hari' => $hariIni
]);

$isPiket = $stmtPiket->fetch();




$guru = [
    'nama_lengkap' => $profil['nama_guru'] ?: $profil['nama_akun']
];

$activeMenu   = 'profil';
$piketHariIni = (bool) $isPiket;
$pageTitle    = 'Profil';
$pageSubtitle = 'Informasi akun & data pribadi';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Profil Guru - SMKS Mulia Buana</title>

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


                

                <div class="page-header">

                    <div class="breadcrumb">

                        <a href="dashboard.php">Dashboard</a>

                        <span>/</span>

                        <span>Profil</span>

                    </div>


                    <h1 class="page-title">
                        Profil Guru
                    </h1>


                    <p class="page-description">
                        Informasi akun dan data pribadi Guru.
                    </p>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("profil", "", 18) ?>
                            </span>

                            Informasi Guru

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <div class="info-grid">


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Nama Lengkap
                                </div>

                                <div class="info-value">
                                    <?= e(
                                        $profil['nama_guru']
                                        ?: $profil['nama_akun']
                                    ) ?>
                                </div>

                            </div>


                            

                            <div class="info-box">

                                <div class="info-label">
                                    NIP
                                </div>

                                <div class="info-value">
                                    <?= e($profil['nip'] ?: '-') ?>
                                </div>

                            </div>


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Mata Pelajaran
                                </div>

                                <div class="info-value">
                                    <?= e(
                                        $profil['nama_mapel']
                                        ?: 'Belum ditentukan'
                                    ) ?>
                                </div>

                            </div>


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Status Guru
                                </div>

                                <div class="info-value">
                                    <?= e(
                                        $profil['status_guru']
                                        ?: '-'
                                    ) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("info", "", 18) ?>
                            </span>

                            Informasi Akun

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <div class="info-grid">


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Username
                                </div>

                                <div class="info-value">
                                    <?= e($profil['username']) ?>
                                </div>

                            </div>


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Role
                                </div>

                                <div class="info-value">
                                    Guru
                                </div>

                            </div>


                            

                            <div class="info-box">

                                <div class="info-label">
                                    Status Akun
                                </div>

                                <div class="info-value">
                                    <?= e($profil['status_akun']) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                

                <a href="dashboard.php" class="back-link">
                    ← Kembali ke Dashboard
                </a>


            </div>

        </main>

    </div>

</div>

</body>

</html>