<?php

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

date_default_timezone_set('Asia/Jakarta');

$aksi = trim($_POST['aksi'] ?? '');
$id = (int) ($_POST['id'] ?? 0);
$catatan = trim($_POST['catatan_admin'] ?? '');

if ($id <= 0) {
    flash('error', 'ID pengajuan tidak valid.');
    redirect('../admintu/perizinan.php');
}

$aksiValid = ['setujui', 'tolak'];
if (!in_array($aksi, $aksiValid, true)) {
    flash('error', 'Aksi tidak valid.');
    redirect('../admintu/perizinan.php');
}

if (mb_strlen($catatan) > 500) {
    flash('error', 'Catatan admin maksimal 500 karakter.');
    redirect('../admintu/perizinan.php');
}

$adminId = currentUserId();
if (!$adminId) {
    flash('error', 'Sesi pengguna tidak valid.');
    redirect('../login.php');
}

$stmt = $pdo->prepare("SELECT id, guru_id, tanggal_mulai, tanggal_selesai, jenis_izin, alasan, bukti, status, diproses_oleh, diproses_at, catatan_admin, created_at, updated_at FROM perizinan WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$izin = $stmt->fetch();

if (!$izin) {
    flash('error', 'Pengajuan izin tidak ditemukan.');
    redirect('../admintu/perizinan.php');
}

if ($izin['status'] !== 'Menunggu') {
    flash('error', 'Pengajuan ini sudah pernah diproses.');
    redirect('../admintu/perizinan.php');
}

$statusBaru = ($aksi === 'setujui') ? 'Disetujui' : 'Ditolak';
$catatanBaru = ($catatan !== '') ? $catatan : null;

$dataSebelum = json_encode([
    'id' => (int) $izin['id'],
    'guru_id' => (int) $izin['guru_id'],
    'tanggal_mulai' => $izin['tanggal_mulai'],
    'tanggal_selesai' => $izin['tanggal_selesai'],
    'jenis_izin' => $izin['jenis_izin'],
    'alasan' => $izin['alasan'],
    'bukti' => $izin['bukti'],
    'status' => $izin['status'],
    'diproses_oleh' => $izin['diproses_oleh'] !== null ? (int) $izin['diproses_oleh'] : null,
    'diproses_at' => $izin['diproses_at'],
    'catatan_admin' => $izin['catatan_admin'],
    'created_at' => $izin['created_at'],
    'updated_at' => $izin['updated_at']
], JSON_UNESCAPED_UNICODE);

$dataSesudah = json_encode([
    'id' => (int) $izin['id'],
    'guru_id' => (int) $izin['guru_id'],
    'tanggal_mulai' => $izin['tanggal_mulai'],
    'tanggal_selesai' => $izin['tanggal_selesai'],
    'jenis_izin' => $izin['jenis_izin'],
    'alasan' => $izin['alasan'],
    'bukti' => $izin['bukti'],
    'status' => $statusBaru,
    'diproses_oleh' => $adminId,
    'catatan_admin' => $catatanBaru
], JSON_UNESCAPED_UNICODE);

$alasanAudit = ($statusBaru === 'Disetujui') ? 'Persetujuan pengajuan perizinan guru oleh Admin TU.' : 'Penolakan pengajuan perizinan guru oleh Admin TU.';
if ($catatanBaru !== null) {
    $alasanAudit .= ' Catatan admin: ' . $catatanBaru;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE perizinan SET status = ?, diproses_oleh = ?, diproses_at = NOW(), catatan_admin = ? WHERE id = ? AND status = 'Menunggu'");
    $stmt->execute([$statusBaru, $adminId, $catatanBaru, $id]);

    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Pengajuan tidak dapat diproses karena statusnya sudah berubah.');
    }

    $stmtAudit = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (?, 'perizinan', ?, 'UPDATE', ?, ?, ?)");
    $stmtAudit->execute([$adminId, $id, $dataSebelum, $dataSesudah, $alasanAudit]);

    $stmtGuru = $pdo->prepare("SELECT u.id AS user_id, g.nama_lengkap FROM guru g INNER JOIN users u ON u.id = g.user_id WHERE g.id = ? AND u.status = 'aktif' LIMIT 1");
    $stmtGuru->execute([(int) $izin['guru_id']]);
    $guruPenerima = $stmtGuru->fetch(PDO::FETCH_ASSOC);

    if ($guruPenerima) {
        $judulNotifikasi = 'Perizinan ' . $statusBaru;
        $pesanNotifikasi = 'Pengajuan ' . $izin['jenis_izin'] . ' Anda untuk tanggal ' . formatTanggal($izin['tanggal_mulai']) . ' sampai ' . formatTanggal($izin['tanggal_selesai']) . ' telah ' . strtolower($statusBaru) . ' oleh Admin TU.';
        if ($catatanBaru !== null) {
            $pesanNotifikasi .= ' Catatan Admin: ' . $catatanBaru;
        }

        $stmtNotif = $pdo->prepare("INSERT IGNORE INTO notifikasi (user_id, tipe, event_key, judul, pesan, link, dibaca, created_at) VALUES (?, 'perizinan', ?, ?, ?, 'perizinan.php', 0, NOW())");
        $stmtNotif->execute([(int) $guruPenerima['user_id'], 'perizinan:' . $id . ':' . $statusBaru, $judulNotifikasi, $pesanNotifikasi]);
    }

    $pdo->commit();
    flash('success', 'Pengajuan izin berhasil ' . strtolower($statusBaru) . '.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Pengajuan izin gagal diproses.');
}

redirect('../admintu/perizinan.php');