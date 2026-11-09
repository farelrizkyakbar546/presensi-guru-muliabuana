<?php

date_default_timezone_set('Asia/Jakarta');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('guru');

$userId = currentUserId();

$tanggalMulai  = trim($_POST['tanggal_mulai'] ?? '');
$tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');
$jenisIzin     = trim($_POST['jenis_izin'] ?? '');
$alasan        = trim($_POST['alasan'] ?? '');
$jadwalDipilih = $_POST['jadwal_id'] ?? [];

$jenisIzinValid = ['Izin', 'Sakit', 'Keperluan Pribadi', 'Lainnya'];
if (!in_array($jenisIzin, $jenisIzinValid, true)) {
    flash('error', 'Jenis izin tidak valid.');
    redirect('../guru/perizinan.php');
}

if ($tanggalMulai === '' || $tanggalSelesai === '') {
    flash('error', 'Tanggal mulai dan tanggal selesai wajib diisi.');
    redirect('../guru/perizinan.php');
}

$startDate = DateTime::createFromFormat('Y-m-d', $tanggalMulai);
$endDate = DateTime::createFromFormat('Y-m-d', $tanggalSelesai);

if (!$startDate || !$endDate || $startDate->format('Y-m-d') !== $tanggalMulai || $endDate->format('Y-m-d') !== $tanggalSelesai) {
    flash('error', 'Format tanggal tidak valid.');
    redirect('../guru/perizinan.php');
}

if ($tanggalSelesai < $tanggalMulai) {
    flash('error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
    redirect('../guru/perizinan.php');
}

if ($alasan === '' || mb_strlen($alasan) > 5000) {
    flash('error', 'Alasan perizinan wajib diisi (maksimal 5000 karakter).');
    redirect('../guru/perizinan.php');
}

if (!is_array($jadwalDipilih)) {
    $jadwalDipilih = [];
}

$jadwalDipilih = array_values(array_unique(array_filter(array_map('intval', $jadwalDipilih), fn($id) => $id > 0)));

$stmt = $pdo->prepare("SELECT id, nama_lengkap, status FROM guru WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$guru = $stmt->fetch();

if (!$guru || $guru['status'] !== 'aktif') {
    flash('error', 'Data guru tidak ditemukan atau akun guru tidak aktif.');
    redirect('../guru/perizinan.php');
}

$guruId = (int) $guru['id'];
$hariIni = date('Y-m-d');

if ($tanggalMulai < $hariIni) {
    flash('error', 'Tanggal mulai perizinan tidak boleh lebih awal dari hari ini.');
    redirect('../guru/perizinan.php');
}

$stmt = $pdo->prepare("SELECT id FROM perizinan WHERE guru_id = ? AND status IN ('Menunggu', 'Disetujui') AND tanggal_mulai <= ? AND tanggal_selesai >= ? LIMIT 1");
$stmt->execute([$guruId, $tanggalSelesai, $tanggalMulai]);
if ($stmt->fetch()) {
    flash('error', 'Sudah terdapat pengajuan perizinan pada rentang tanggal tersebut.');
    redirect('../guru/perizinan.php');
}

$jadwalValid = [];
if (!empty($jadwalDipilih)) {
    $placeholders = implode(',', array_fill(0, count($jadwalDipilih), '?'));
    $params = array_merge($jadwalDipilih, [$guruId]);
    $stmt = $pdo->prepare("SELECT id, hari, jam_mulai, jam_selesai, kelas_id, mapel_id FROM jadwal WHERE id IN ($placeholders) AND guru_id = ?");
    $stmt->execute($params);
    $jadwalValid = $stmt->fetchAll();

    if (count($jadwalValid) !== count($jadwalDipilih)) {
        flash('error', 'Terdapat jadwal yang tidak valid atau bukan milik Anda.');
        redirect('../guru/perizinan.php');
    }
}

$namaBukti = null;
if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'File bukti gagal diunggah.');
        redirect('../guru/perizinan.php');
    }

    if ($_FILES['bukti']['size'] > 2 * 1024 * 1024) {
        flash('error', 'Ukuran file bukti maksimal 2 MB.');
        redirect('../guru/perizinan.php');
    }

    $tmpFile = $_FILES['bukti']['tmp_name'];
    $extension = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
    $extensionValid = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extension, $extensionValid, true)) {
        flash('error', 'Format file bukti harus JPG, JPEG, PNG, atau PDF.');
        redirect('../guru/perizinan.php');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpFile);
    finfo_close($finfo);

    $mimeValid = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mimeType, $mimeValid, true)) {
        flash('error', 'Tipe file bukti tidak valid.');
        redirect('../guru/perizinan.php');
    }

    $uploadDir = '../uploads/perizinan/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileName = 'izin_' . $guruId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $targetFile = $uploadDir . $fileName;

    if (!move_uploaded_file($tmpFile, $targetFile)) {
        flash('error', 'File bukti gagal disimpan.');
        redirect('../guru/perizinan.php');
    }

    $namaBukti = 'uploads/perizinan/' . $fileName;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO perizinan (guru_id, tanggal_mulai, tanggal_selesai, jenis_izin, alasan, bukti, status) VALUES (?, ?, ?, ?, ?, ?, 'Menunggu')");
    $stmt->execute([$guruId, $tanggalMulai, $tanggalSelesai, $jenisIzin, $alasan, $namaBukti]);
    $perizinanId = (int) $pdo->lastInsertId();

    if (!empty($jadwalValid)) {
        $stmtJadwal = $pdo->prepare("INSERT INTO perizinan_jadwal (perizinan_id, jadwal_id) VALUES (?, ?)");
        foreach ($jadwalValid as $jadwal) {
            $stmtJadwal->execute([$perizinanId, $jadwal['id']]);
        }
    }

    $dataSesudah = json_encode([
        'id' => $perizinanId,
        'guru_id' => $guruId,
        'tanggal_mulai' => $tanggalMulai,
        'tanggal_selesai' => $tanggalSelesai,
        'jenis_izin' => $jenisIzin,
        'alasan' => $alasan,
        'bukti' => $namaBukti,
        'status' => 'Menunggu'
    ], JSON_UNESCAPED_UNICODE);

    $stmtAudit = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (?, 'perizinan', ?, 'INSERT', NULL, ?, 'Pengajuan perizinan guru')");
    $stmtAudit->execute([$userId, $perizinanId, $dataSesudah]);

    $stmtPenerima = $pdo->query("SELECT id FROM users WHERE role IN ('adminTu', 'kepsek') AND status = 'aktif'");
    $penerimaList = $stmtPenerima->fetchAll(PDO::FETCH_COLUMN);

    $eventKey = 'perizinan:diajukan:' . $perizinanId;
    $judulNotifikasi = 'Pengajuan Perizinan Baru';
    $pesanNotifikasi = $guru['nama_lengkap'] . ' mengajukan ' . $jenisIzin . ' untuk tanggal ' . formatTanggal($tanggalMulai) . ' sampai ' . formatTanggal($tanggalSelesai) . '. Menunggu proses Admin TU.';

    $stmtNotif = $pdo->prepare("INSERT IGNORE INTO notifikasi (user_id, tipe, event_key, judul, pesan, link, dibaca, created_at) VALUES (?, 'perizinan', ?, ?, ?, 'perizinan.php', 0, NOW())");
    foreach ($penerimaList as $penerimaId) {
        $stmtNotif->execute([(int) $penerimaId, $eventKey, $judulNotifikasi, $pesanNotifikasi]);
    }

    $pdo->commit();
    flash('success', 'Pengajuan perizinan berhasil dikirim dan menunggu persetujuan AdminTU.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($namaBukti && file_exists('../' . $namaBukti)) {
        @unlink('../' . $namaBukti);
    }
    flash('error', 'Pengajuan perizinan gagal disimpan.');
}

redirect('../guru/perizinan.php');