<?php

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

$message = '';
$error = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $type = $_POST['type'] ?? '';

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {

        $error = 'Silakan pilih file CSV terlebih dahulu.';

    } else {

        $file = $_FILES['file'];

        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        if ($extension !== 'csv') {

            $error = 'File harus berformat CSV.';

        } else {

            $handle = fopen($file['tmp_name'], 'r');

            if ($handle === false) {

                $error = 'File CSV tidak dapat dibaca.';

            } else {

                

                $header = fgetcsv($handle);

                if (!$header) {

                    $error = 'File CSV kosong.';

                } else {

                    

                    if ($type === 'guru') {

                        $berhasil = 0;
                        $gagal = 0;

                        while (($row = fgetcsv($handle)) !== false) {

                            if (count($row) < 5) {
                                $gagal++;
                                continue;
                            }

                            $nip = trim($row[0]);
                            $nama = trim($row[1]);
                            $kodeMapel = trim($row[2]);
                            $status = strtolower(trim($row[4]));

                            if ($nama === '') {
                                $gagal++;
                                continue;
                            }

                            

                            if ($status === '') {
                                $status = 'aktif';
                            }

                            if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtUser = $pdo->prepare("
                                SELECT id
                                FROM users
                                WHERE nama_lengkap = ?
                                AND role = 'guru'
                                AND status = 'aktif'
                                LIMIT 1
                            ");

                            $stmtUser->execute([$nama]);

                            $userId = $stmtUser->fetchColumn();

                            if (!$userId) {
                                $gagal++;
                                continue;
                            }

                            

                            $mapelId = null;

                            if ($kodeMapel !== '') {

                                $stmtMapel = $pdo->prepare("
                                    SELECT id
                                    FROM mapel
                                    WHERE kode_mapel = ?
                                    LIMIT 1
                                ");

                                $stmtMapel->execute([$kodeMapel]);

                                $mapelId = $stmtMapel->fetchColumn();

                                if (!$mapelId) {
                                    $gagal++;
                                    continue;
                                }
                            }

                            

                            if ($nip !== '') {

                                $stmtCheckNip = $pdo->prepare("
                                    SELECT id
                                    FROM guru
                                    WHERE nip = ?
                                    LIMIT 1
                                ");

                                $stmtCheckNip->execute([$nip]);

                                if ($stmtCheckNip->fetchColumn()) {
                                    $gagal++;
                                    continue;
                                }
                            }

                            

                            $stmtCheckUser = $pdo->prepare("
                                SELECT id
                                FROM guru
                                WHERE user_id = ?
                                LIMIT 1
                            ");

                            $stmtCheckUser->execute([$userId]);

                            if ($stmtCheckUser->fetchColumn()) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtInsert = $pdo->prepare("
                                INSERT INTO guru
                                (
                                    user_id,
                                    nip,
                                    nama_lengkap,
                                    mapel_utama_id,
                                    status
                                )
                                VALUES (?, ?, ?, ?, ?)
                            ");

                            $stmtInsert->execute([
                                $userId,
                                $nip !== '' ? $nip : null,
                                $nama,
                                $mapelId ?: null,
                                $status
                            ]);

                            $berhasil++;
                        }

                        $message =
                            "Import guru selesai. " .
                            "Berhasil: {$berhasil}, " .
                            "Gagal/dilewati: {$gagal}.";
                    }


                    

                    elseif ($type === 'siswa') {

                        $berhasil = 0;
                        $gagal = 0;

                        while (($row = fgetcsv($handle)) !== false) {

                            if (count($row) < 7) {
                                $gagal++;
                                continue;
                            }

                            $nis = trim($row[0]);
                            $nama = trim($row[1]);
                            $namaKelas = trim($row[2]);
                            $jenisKelamin = strtoupper(trim($row[5]));
                            $status = strtolower(trim($row[6]));

                            if ($nis === '' || $nama === '') {
                                $gagal++;
                                continue;
                            }

                            

                            if (!in_array($jenisKelamin, ['L', 'P'], true)) {
                                $gagal++;
                                continue;
                            }

                            

                            if ($status === '') {
                                $status = 'aktif';
                            }

                            if (!in_array($status, ['aktif', 'nonaktif'], true)) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtKelas = $pdo->prepare("
                                SELECT id
                                FROM kelas
                                WHERE nama_kelas = ?
                                LIMIT 1
                            ");

                            $stmtKelas->execute([$namaKelas]);

                            $kelasId = $stmtKelas->fetchColumn();

                            if (!$kelasId) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtCheck = $pdo->prepare("
                                SELECT id
                                FROM siswa
                                WHERE nis = ?
                                LIMIT 1
                            ");

                            $stmtCheck->execute([$nis]);

                            if ($stmtCheck->fetchColumn()) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtInsert = $pdo->prepare("
                                INSERT INTO siswa
                                (
                                    nis,
                                    nama_lengkap,
                                    kelas_id,
                                    jenis_kelamin,
                                    status
                                )
                                VALUES (?, ?, ?, ?, ?)
                            ");

                            $stmtInsert->execute([
                                $nis,
                                $nama,
                                $kelasId,
                                $jenisKelamin,
                                $status
                            ]);

                            $berhasil++;
                        }

                        $message =
                            "Import siswa selesai. " .
                            "Berhasil: {$berhasil}, " .
                            "Gagal/dilewati: {$gagal}.";
                    }


                    

                    elseif ($type === 'kelas') {

                        $berhasil = 0;
                        $gagal = 0;

                        while (($row = fgetcsv($handle)) !== false) {

                            if (count($row) < 4) {
                                $gagal++;
                                continue;
                            }

                            $namaKelas = trim($row[0]);
                            $tingkat = strtoupper(trim($row[1]));
                            $kodeJurusan = trim($row[2]);

                            if ($namaKelas === '' || $tingkat === '') {
                                $gagal++;
                                continue;
                            }

                            

                            if (!in_array($tingkat, ['X', 'XI', 'XII'], true)) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtJurusan = $pdo->prepare("
                                SELECT id
                                FROM jurusan
                                WHERE kode_jurusan = ?
                                LIMIT 1
                            ");

                            $stmtJurusan->execute([$kodeJurusan]);

                            $jurusanId = $stmtJurusan->fetchColumn();

                            if (!$jurusanId) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtCheck = $pdo->prepare("
                                SELECT id
                                FROM kelas
                                WHERE nama_kelas = ?
                                LIMIT 1
                            ");

                            $stmtCheck->execute([$namaKelas]);

                            if ($stmtCheck->fetchColumn()) {
                                $gagal++;
                                continue;
                            }

                            

                            $stmtInsert = $pdo->prepare("
                                INSERT INTO kelas
                                (
                                    jurusan_id,
                                    tingkat,
                                    nama_kelas
                                )
                                VALUES (?, ?, ?)
                            ");

                            $stmtInsert->execute([
                                $jurusanId,
                                $tingkat,
                                $namaKelas
                            ]);

                            $berhasil++;
                        }

                        $message =
                            "Import kelas selesai. " .
                            "Berhasil: {$berhasil}, " .
                            "Gagal/dilewati: {$gagal}.";
                    }


                    

                    else {

                        $error = 'Jenis import tidak valid.';
                    }
                }

                fclose($handle);
            }
        }
    }
}




$activeMenu   = 'import';
$pageTitle    = 'Import Data';
$pageSubtitle = 'Import data master dari CSV';
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Import Data - Admin TU</title>

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

                        <span>Import Data</span>

                    </div>


                    <h1 class="page-title">
                        Import Data
                    </h1>


                    <p class="page-description">
                        Import data master menggunakan file CSV.
                    </p>

                </div>


                

                <?php if ($message): ?>

                    <div class="alert alert-success">
                        <?= e($message) ?>
                    </div>

                <?php endif; ?>


                <?php if ($error): ?>

                    <div class="alert alert-danger">
                        <?= e($error) ?>
                    </div>

                <?php endif; ?>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("guru", "", 18) ?>
                            </span>

                            Import Data Guru

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <form method="POST" enctype="multipart/form-data">

                            <input type="hidden" name="type" value="guru">


                            <div class="form-grid">

                                <div class="form-group full">

                                    <label for="file_guru" class="form-label">
                                        File CSV Guru
                                    </label>

                                    <input
                                        type="file"
                                        id="file_guru"
                                        name="file"
                                        accept=".csv"
                                        required
                                    >

                                    <div class="note">
                                        Format kolom:
                                        <strong>
                                            NIP, Nama Guru, Kode Mapel, Mapel Utama, Status
                                        </strong>
                                    </div>

                                    <div class="note">
                                        Nama Guru harus sudah memiliki akun
                                        <strong>Users</strong>
                                        dengan role
                                        <strong>guru</strong>.
                                    </div>

                                </div>

                            </div>


                            <div class="form-actions">

                                <button type="submit" class="btn btn-primary">
                                    Import Guru
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("siswa", "", 18) ?>
                            </span>

                            Import Data Siswa

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <form method="POST" enctype="multipart/form-data">

                            <input type="hidden" name="type" value="siswa">


                            <div class="form-grid">

                                <div class="form-group full">

                                    <label for="file_siswa" class="form-label">
                                        File CSV Siswa
                                    </label>

                                    <input
                                        type="file"
                                        id="file_siswa"
                                        name="file"
                                        accept=".csv"
                                        required
                                    >

                                    <div class="note">
                                        Format kolom:
                                        <strong>
                                            NIS, Nama Siswa, Kelas, Kode Jurusan,
                                            Jurusan, Jenis Kelamin, Status
                                        </strong>
                                    </div>

                                </div>

                            </div>


                            <div class="form-actions">

                                <button type="submit" class="btn btn-primary">
                                    Import Siswa
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("kelas", "", 18) ?>
                            </span>

                            Import Data Kelas

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <form method="POST" enctype="multipart/form-data">

                            <input type="hidden" name="type" value="kelas">


                            <div class="form-grid">

                                <div class="form-group full">

                                    <label for="file_kelas" class="form-label">
                                        File CSV Kelas
                                    </label>

                                    <input
                                        type="file"
                                        id="file_kelas"
                                        name="file"
                                        accept=".csv"
                                        required
                                    >

                                    <div class="note">
                                        Format kolom:
                                        <strong>
                                            Nama Kelas, Tingkat, Kode Jurusan, Jurusan
                                        </strong>
                                    </div>

                                </div>

                            </div>


                            <div class="form-actions">

                                <button type="submit" class="btn btn-primary">
                                    Import Kelas
                                </button>

                            </div>

                        </form>

                    </div>

                </div>


                

                <div class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon("info", "", 18) ?>
                            </span>

                            Catatan

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <div class="notification-list">


                            <div class="notification-item">

                                <div class="notification-marker"></div>

                                <div class="notification-content">

                                    <div class="notification-title">
                                        Format File
                                    </div>

                                    <div class="notification-meta">
                                        File harus menggunakan format CSV.
                                    </div>

                                </div>

                            </div>


                            <div class="notification-item">

                                <div class="notification-marker orange"></div>

                                <div class="notification-content">

                                    <div class="notification-title">
                                        Data Duplikat
                                    </div>

                                    <div class="notification-meta">
                                        Data dengan NIP/NIS yang sudah ada akan dilewati.
                                    </div>

                                </div>

                            </div>


                            <div class="notification-item">

                                <div class="notification-marker orange"></div>

                                <div class="notification-content">

                                    <div class="notification-title">
                                        Relasi Kelas
                                    </div>

                                    <div class="notification-meta">
                                        Kelas harus sudah tersedia sebelum mengimpor data siswa.
                                    </div>

                                </div>

                            </div>


                            <div class="notification-item">

                                <div class="notification-marker gray"></div>

                                <div class="notification-content">

                                    <div class="notification-title">
                                        Kode Relasi
                                    </div>

                                    <div class="notification-meta">
                                        Kode jurusan dan kode mapel harus sesuai dengan data yang ada di sistem.
                                    </div>

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