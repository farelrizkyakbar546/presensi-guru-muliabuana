<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();

$tanggalHariIni = date('Y-m-d');

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




$stmt = $pdo->prepare("
    SELECT
        id,
        nama_lengkap
    FROM guru
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    die('Data guru tidak ditemukan. Hubungi Admin TU.');
}

$guruId = (int) $guru['id'];




$notifCountFile = __DIR__ . '/partials/notif-count.php';

if (file_exists($notifCountFile)) {
    include $notifCountFile;
}




$stmt = $pdo->prepare("
    SELECT
        id,
        tipe,
        event_key,
        judul,
        pesan,
        link,
        dibaca,
        dibaca_at,
        created_at
    FROM notifikasi
    WHERE user_id = :user_id
    ORDER BY created_at DESC, id DESC
");

$stmt->execute([
    'user_id' => $userId
]);

$notifikasi = $stmt->fetchAll(PDO::FETCH_ASSOC);




foreach ($notifikasi as &$notif) {

    switch ($notif['tipe']) {

        case 'presensi_masuk':

            $notif['warna'] = 'green';
            $notif['ikon'] = 'check';

            break;


        case 'presensi_pulang':

            $notif['warna'] = 'green';
            $notif['ikon'] = 'arrow-down';

            break;


        case 'piket':

            $notif['warna'] = 'orange';
            $notif['ikon'] = 'piket';

            break;


        case 'pengampu':

            $notif['warna'] = 'blue';
            $notif['ikon'] = 'arrow-right';

            break;


        case 'perizinan':

            if (
                stripos(
                    (string) $notif['judul'],
                    'Disetujui'
                ) !== false
            ) {
                $notif['warna'] = 'green';
                $notif['ikon'] = 'check';

            } elseif (
                stripos(
                    (string) $notif['judul'],
                    'Ditolak'
                ) !== false
            ) {
                $notif['warna'] = 'red';
                $notif['ikon'] = 'alert';

            } else {
                $notif['warna'] = 'orange';
                $notif['ikon'] = 'alert';
            }

            break;


        default:

            $notif['warna'] = 'blue';
            $notif['ikon'] = 'bell';

            break;
    }
}

unset($notif);




foreach ($notifikasi as &$notif) {

    $notif['waktu'] = !empty($notif['created_at'])
        ? date(
            'H:i',
            strtotime($notif['created_at'])
        )
        : '--:--';

    $notif['sort_time'] = $notif['created_at']
        ?? '0000-00-00 00:00:00';
}

unset($notif);


$totalNotif = count($notifikasi);







$activeMenu = '';

$piketHariIni = false;

$notifCount = 0;

$pageTitle = 'Notifikasi';

$pageSubtitle = 'Info & aktivitas Anda hari ini';

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
        Notifikasi - SMKS Mulia Buana
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


        

        <?php include __DIR__ . '/partials/topbar.php'; ?>


        

        <main class="dashboard-content">

            <div class="dashboard-container">


                

                <div class="page-header">

                    <div class="breadcrumb">

                        <a href="dashboard.php">
                            Dashboard
                        </a>

                        <span>/</span>

                        <span>
                            Notifikasi
                        </span>

                    </div>


                    <h1 class="page-title">
                        Notifikasi
                    </h1>


                    <p class="page-description">

                        Info & aktivitas Anda hari ini —

                        <strong>
                            <?= e($hariIni) ?>
                        </strong>,

                        <?= e(formatTanggal($tanggalHariIni)) ?>

                    </p>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon('bell', '', 18) ?>
                            </span>

                            Daftar Notifikasi

                        </h2>


                        <div
                            class="dashboard-card-link"
                            style="cursor: default;"
                        >

                            <?= (int) $totalNotif ?>
                            notifikasi

                        </div>

                    </div>


                    <div class="dashboard-card-body">


                        <?php if (empty($notifikasi)): ?>


                            <div class="empty-state">

                                <div class="empty-state-icon">
                                    <?= appIcon('bell', '', 40) ?>
                                </div>

                                <h3>
                                    Belum Ada Notifikasi
                                </h3>

                                <p>
                                    Belum ada aktivitas atau info
                                    penting hari ini.
                                </p>

                            </div>


                        <?php else: ?>


                            <div class="notif-list">

                                <?php foreach ($notifikasi as $notif): ?>

                                    <?php
                                    $link = trim(
                                        (string) (
                                            $notif['link'] ?? ''
                                        )
                                    );
                                    ?>


                                    <?php if ($link !== ''): ?>

                                        <a
                                            href="<?= e($link) ?>"
                                            class="notif-item notif-item-<?= e($notif['warna']) ?>"
                                        >

                                    <?php else: ?>

                                        <div
                                            class="notif-item notif-item-<?= e($notif['warna']) ?>"
                                        >

                                    <?php endif; ?>


                                            <div class="notif-icon">

                                                <?= appIcon(
                                                    $notif['ikon'],
                                                    '',
                                                    18
                                                ) ?>

                                            </div>


                                            <div class="notif-content">

                                                <div class="notif-header">

                                                    <div class="notif-title">

                                                        <?= e(
                                                            $notif['judul']
                                                        ) ?>

                                                    </div>


                                                    <div class="notif-time">

                                                        <?= e(
                                                            $notif['waktu']
                                                        ) ?>

                                                    </div>

                                                </div>


                                                <div class="notif-description">

                                                    <?= e(
                                                        $notif['pesan']
                                                    ) ?>

                                                </div>

                                            </div>


                                            <?php if ($link !== ''): ?>

                                                <div class="notif-arrow">
                                                    ›
                                                </div>

                                            <?php endif; ?>


                                    <?php if ($link !== ''): ?>

                                        </a>

                                    <?php else: ?>

                                        </div>

                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </div>


                        <?php endif; ?>


                    </div>

                </div>


                

                <a
                    href="dashboard.php"
                    class="back-link"
                >
                    ← Kembali ke Dashboard
                </a>


            </div>

        </main>

    </div>

</div>

</body>

</html>