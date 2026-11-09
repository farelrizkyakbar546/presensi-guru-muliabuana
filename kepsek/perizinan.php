<?php

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('kepsek');




$stmt = $pdo->query("
    SELECT
        p.id,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.jenis_izin,
        p.alasan,
        p.bukti,
        p.status,
        p.diproses_at,
        p.catatan_admin,
        g.nama_lengkap,
        g.nip
    FROM perizinan p
    INNER JOIN guru g ON g.id = p.guru_id
    ORDER BY p.id DESC
");

$izinList = $stmt->fetchAll();




$stmt = $pdo->query("
    SELECT
        SUM(status = 'Menunggu') AS menunggu,
        SUM(status = 'Disetujui') AS disetujui,
        SUM(status = 'Ditolak') AS ditolak
    FROM perizinan
");

$stat = $stmt->fetch();

$izinMenunggu  = (int) ($stat['menunggu'] ?? 0);
$izinDisetujui = (int) ($stat['disetujui'] ?? 0);
$izinDitolak   = (int) ($stat['ditolak'] ?? 0);




$badgeMap = [
    'Menunggu'  => 'warning',
    'Disetujui' => 'success',
    'Ditolak'   => 'danger',
];




$activeMenu   = 'perizinan';
$pageTitle    = 'Monitoring Perizinan';
$pageSubtitle = 'Pantau pengajuan izin guru';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Monitoring Perizinan - Kepala Sekolah</title>

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


                

                <div class="page-header">

                    <div class="breadcrumb">

                        <a href="dashboard.php">Dashboard</a>

                        <span>/</span>

                        <span>Monitoring Perizinan</span>

                    </div>


                    <h1 class="page-title">
                        Monitoring Perizinan Guru
                    </h1>


                    <p class="page-description">
                        Informasi pengajuan perizinan guru.
                    </p>

                </div>


                

                <section class="stats-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">


                    <div class="stat-card orange">

                        <div class="stat-card-top">

                            <div class="stat-icon"><?= appIcon("clock", "", 24) ?></div>

                            <div>

                                <div class="stat-label">
                                    Menunggu
                                </div>

                                <div class="stat-value">
                                    <?= $izinMenunggu ?>
                                </div>

                                <div class="stat-description">
                                    Belum diproses
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="stat-card green">

                        <div class="stat-card-top">

                            <div class="stat-icon"><?= appIcon("check", "", 24) ?></div>

                            <div>

                                <div class="stat-label">
                                    Disetujui
                                </div>

                                <div class="stat-value">
                                    <?= $izinDisetujui ?>
                                </div>

                                <div class="stat-description">
                                    Pengajuan disetujui
                                </div>

                            </div>

                        </div>

                    </div>


                    <div class="stat-card blue">

                        <div class="stat-card-top">

                            <div class="stat-icon"><?= appIcon("x", "", 24) ?></div>

                            <div>

                                <div class="stat-label">
                                    Ditolak
                                </div>

                                <div class="stat-value">
                                    <?= $izinDitolak ?>
                                </div>

                                <div class="stat-description">
                                    Pengajuan ditolak
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

                            Daftar Perizinan

                        </h2>


                        <div class="dashboard-card-link" style="cursor: default;">
                            Total: <?= count($izinList) ?> pengajuan
                        </div>

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
                                        <th>Alasan</th>
                                        <th>Status</th>
                                        <th>Catatan Admin</th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php if (!$izinList): ?>

                                    <tr>

                                        <td colspan="7" style="text-align:center; padding: 30px; color: var(--text-500);">

                                            Belum ada data perizinan.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($izinList as $index => $izin): ?>

                                        <?php

                                        $badgeClass = $badgeMap[$izin['status']] ?? 'secondary';

                                        ?>

                                        <tr>

                                            <td>
                                                <?= $index + 1 ?>
                                            </td>

                                            <td>

                                                <span class="schedule-subject">
                                                    <?= e($izin['nama_lengkap']) ?>
                                                </span>

                                                <div class="schedule-code">
                                                    NIP: <?= e($izin['nip'] ?: '-') ?>
                                                </div>

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

                                            <td style="white-space: normal; max-width: 220px;">
                                                <?= nl2br(e($izin['alasan'])) ?>
                                            </td>

                                            <td>

                                                <span class="status-badge <?= e($badgeClass) ?>">
                                                    <?= e($izin['status']) ?>
                                                </span>

                                            </td>

                                            <td style="white-space: normal; max-width: 200px;">

                                                <?= !empty($izin['catatan_admin'])
                                                    ? nl2br(e($izin['catatan_admin']))
                                                    : '<span style="color: var(--text-400);">-</span>'
                                                ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                                </tbody>

                            </table>

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