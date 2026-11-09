<?php

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

$type = $_GET['type'] ?? '';



if ($type === 'guru') {

    $stmt = $pdo->query("
        SELECT
            g.nip,
            g.nama_lengkap,
            m.kode_mapel,
            m.nama_mapel,
            g.status
        FROM guru g
        LEFT JOIN mapel m
            ON m.id = g.mapel_utama_id
        ORDER BY g.nama_lengkap ASC
    ");

    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=data-guru.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'NIP',
        'Nama Guru',
        'Kode Mapel',
        'Mapel Utama',
        'Status'
    ]);

    foreach ($data as $row) {

        fputcsv($output, [
            $row['nip'],
            $row['nama_lengkap'],
            $row['kode_mapel'],
            $row['nama_mapel'],
            $row['status']
        ]);

    }

    fclose($output);
    exit;
}




if ($type === 'siswa') {

    $stmt = $pdo->query("
        SELECT
            s.nis,
            s.nama_lengkap,
            k.nama_kelas,
            j.kode_jurusan,
            j.nama_jurusan,
            s.jenis_kelamin,
            s.status
        FROM siswa s

        INNER JOIN kelas k
            ON k.id = s.kelas_id

        INNER JOIN jurusan j
            ON j.id = k.jurusan_id

        ORDER BY
            k.nama_kelas ASC,
            s.nama_lengkap ASC
    ");

    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=data-siswa.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'NIS',
        'Nama Siswa',
        'Kelas',
        'Kode Jurusan',
        'Jurusan',
        'Jenis Kelamin',
        'Status'
    ]);

    foreach ($data as $row) {

        fputcsv($output, [
            $row['nis'],
            $row['nama_lengkap'],
            $row['nama_kelas'],
            $row['kode_jurusan'],
            $row['nama_jurusan'],
            $row['jenis_kelamin'],
            $row['status']
        ]);

    }

    fclose($output);
    exit;
}




if ($type === 'kelas') {

    $stmt = $pdo->query("
        SELECT
            k.nama_kelas,
            k.tingkat,
            j.kode_jurusan,
            j.nama_jurusan
        FROM kelas k

        INNER JOIN jurusan j
            ON j.id = k.jurusan_id

        ORDER BY
            k.tingkat ASC,
            k.nama_kelas ASC
    ");

    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=data-kelas.csv');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Nama Kelas',
        'Tingkat',
        'Kode Jurusan',
        'Jurusan'
    ]);

    foreach ($data as $row) {

        fputcsv($output, [
            $row['nama_kelas'],
            $row['tingkat'],
            $row['kode_jurusan'],
            $row['nama_jurusan']
        ]);

    }

    fclose($output);
    exit;
}




if ($type === 'presensi_guru') {

    $tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
    $tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
            pg.tanggal,
            pg.waktu_masuk,
            pg.waktu_pulang,
            g.nip,
            g.nama_lengkap,
            m.nama_mapel,
            pg.status,
            pg.keterangan

        FROM presensi_guru pg

        INNER JOIN guru g
            ON g.id = pg.guru_id

        LEFT JOIN mapel m
            ON m.id = g.mapel_utama_id

        WHERE pg.tanggal BETWEEN :tanggal_mulai
        AND :tanggal_selesai

        ORDER BY
            pg.tanggal DESC,
            g.nama_lengkap ASC
    ");

    $stmt->execute([
        ':tanggal_mulai' => $tanggalMulai,
        ':tanggal_selesai' => $tanggalSelesai
    ]);

    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename=rekap-presensi-guru.csv'
    );

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Tanggal',
        'Waktu Masuk',
        'Waktu Pulang',
        'NIP',
        'Nama Guru',
        'Mapel Utama',
        'Status',
        'Keterangan'
    ]);

    foreach ($data as $row) {

        fputcsv($output, [
            $row['tanggal'],
            $row['waktu_masuk'],
            $row['waktu_pulang'],
            $row['nip'],
            $row['nama_lengkap'],
            $row['nama_mapel'],
            $row['status'],
            $row['keterangan']
        ]);

    }

    fclose($output);
    exit;
}




if ($type === 'presensi_siswa') {

    $tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
    $tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT
            ps.tanggal,
            ps.waktu,
            s.nis,
            s.nama_lengkap,
            k.nama_kelas,
            j.nama_jurusan,
            ps.status,
            ps.keterangan

        FROM presensi_siswa ps

        INNER JOIN siswa s
            ON s.id = ps.siswa_id

        INNER JOIN kelas k
            ON k.id = s.kelas_id

        INNER JOIN jurusan j
            ON j.id = k.jurusan_id

        WHERE ps.tanggal BETWEEN :tanggal_mulai
        AND :tanggal_selesai

        ORDER BY
            ps.tanggal DESC,
            k.nama_kelas ASC,
            s.nama_lengkap ASC
    ");

    $stmt->execute([
        ':tanggal_mulai' => $tanggalMulai,
        ':tanggal_selesai' => $tanggalSelesai
    ]);

    $data = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header(
        'Content-Disposition: attachment; filename=rekap-presensi-siswa.csv'
    );

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($output, [
        'Tanggal',
        'Waktu',
        'NIS',
        'Nama Siswa',
        'Kelas',
        'Jurusan',
        'Status',
        'Keterangan'
    ]);

    foreach ($data as $row) {

        fputcsv($output, [
            $row['tanggal'],
            $row['waktu'],
            $row['nis'],
            $row['nama_lengkap'],
            $row['nama_kelas'],
            $row['nama_jurusan'],
            $row['status'],
            $row['keterangan']
        ]);

    }

    fclose($output);
    exit;
}




$activeMenu   = 'export';
$pageTitle    = 'Export Data';
$pageSubtitle = 'Download data dalam format CSV';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Export Data - Admin TU</title>

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

                        <span>Export Data</span>

                    </div>


                    <h1 class="page-title">
                        Export Data
                    </h1>


                    <p class="page-description">
                        Download data dan rekap presensi dalam format CSV.
                    </p>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("export", "", 18) ?>
                            </span>

                            Master Data

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <div class="form-action-bar">

                            <a href="export.php?type=guru" class="btn btn-primary">
                                <?= appIcon("guru", "", 16) ?> Export Guru
                            </a>

                            <a href="export.php?type=siswa" class="btn btn-primary">
                                <?= appIcon("siswa", "", 16) ?> Export Siswa
                            </a>

                            <a href="export.php?type=kelas" class="btn btn-primary">
                                <?= appIcon("kelas", "", 16) ?> Export Kelas
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

                            Export Rekap Presensi

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <form method="GET">

                            <input
                                type="hidden"
                                name="type"
                                id="exportType"
                                value="presensi_guru"
                            >


                            <div class="form-grid">

                                <div class="form-group">

                                    <label for="jenis_data" class="form-label">
                                        Jenis Data
                                    </label>

                                    <select
                                        id="jenis_data"
                                        onchange="document.getElementById('exportType').value=this.value"
                                    >

                                        <option value="presensi_guru">
                                            Presensi Guru
                                        </option>

                                        <option value="presensi_siswa">
                                            Presensi Siswa
                                        </option>

                                    </select>

                                </div>


                                <div class="form-group">

                                    <label for="tanggal_mulai" class="form-label">
                                        Tanggal Mulai
                                    </label>

                                    <input
                                        type="date"
                                        id="tanggal_mulai"
                                        name="tanggal_mulai"
                                        value="<?= e(date('Y-m-01')) ?>"
                                        required
                                    >

                                </div>


                                <div class="form-group">

                                    <label for="tanggal_selesai" class="form-label">
                                        Tanggal Selesai
                                    </label>

                                    <input
                                        type="date"
                                        id="tanggal_selesai"
                                        name="tanggal_selesai"
                                        value="<?= e(date('Y-m-d')) ?>"
                                        required
                                    >

                                </div>

                            </div>


                            <div class="form-actions">

                                <button type="submit" class="btn btn-success">
                                    <?= appIcon("export", "", 16) ?> Export CSV
                                </button>

                            </div>

                        </form>

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