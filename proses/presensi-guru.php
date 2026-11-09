<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../guru/presensi.php');
}

$stmt = $pdo->prepare("SELECT g.id, g.nama_lengkap, g.status FROM guru g WHERE g.user_id = :user_id LIMIT 1");
$stmt->execute(['user_id' => $userId]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru || $guru['status'] !== 'aktif') {
    flash('error', 'Data guru tidak ditemukan atau tidak aktif.');
    redirect('../guru/presensi.php');
}

$guruId = (int) $guru['id'];
$aksi = trim($_POST['aksi'] ?? '');

if (!in_array($aksi, ['datang', 'pulang'], true)) {
    flash('error', 'Aksi presensi tidak valid.');
    redirect('../guru/presensi.php');
}

$tanggalHariIni = date('Y-m-d');
$waktuSekarang = date('Y-m-d H:i:s');

$stmt = $pdo->prepare("SELECT id, waktu_masuk, waktu_pulang, status FROM presensi_guru WHERE guru_id = :guru_id AND tanggal = :tanggal LIMIT 1");
$stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
$presensi = $stmt->fetch(PDO::FETCH_ASSOC);

if ($aksi === 'datang') {
    if ($presensi && !empty($presensi['waktu_masuk'])) {
        flash('error', 'Presensi datang hari ini sudah tercatat pada pukul ' . formatJam($presensi['waktu_masuk']) . '.');
        redirect('../guru/presensi.php');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, waktu_masuk FROM presensi_guru WHERE guru_id = :guru_id AND tanggal = :tanggal LIMIT 1 FOR UPDATE");
        $stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
        $presensiLock = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($presensiLock && !empty($presensiLock['waktu_masuk'])) {
            $pdo->rollBack();
            flash('error', 'Presensi datang hari ini sudah tercatat pada pukul ' . formatJam($presensiLock['waktu_masuk']) . '.');
            redirect('../guru/presensi.php');
        }

        if (!$presensiLock) {
            $stmt = $pdo->prepare("INSERT INTO presensi_guru (guru_id, tanggal, waktu_masuk, status, keterangan) VALUES (:guru_id, :tanggal, :waktu_masuk, 'Hadir', NULL)");
            $stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni, 'waktu_masuk' => $waktuSekarang]);
        } else {
            $stmt = $pdo->prepare("UPDATE presensi_guru SET waktu_masuk = :waktu_masuk, status = 'Hadir' WHERE id = :id AND waktu_masuk IS NULL");
            $stmt->execute(['waktu_masuk' => $waktuSekarang, 'id' => $presensiLock['id']]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                flash('error', 'Presensi datang tidak dapat dicatat. Data mungkin sudah diperbarui.');
                redirect('../guru/presensi.php');
            }
        }

        $pdo->commit();
        flash('success', 'Presensi datang berhasil dicatat pada pukul ' . date('H:i', strtotime($waktuSekarang)) . ' WIB.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Presensi datang gagal dicatat. Silakan coba lagi.');
    }

    redirect('../guru/presensi.php');
}

if ($aksi === 'pulang') {
    if (!$presensi || empty($presensi['waktu_masuk'])) {
        flash('error', 'Anda belum melakukan presensi datang hari ini.');
        redirect('../guru/presensi.php');
    }

    if (!empty($presensi['waktu_pulang'])) {
        flash('error', 'Presensi pulang hari ini sudah tercatat pada pukul ' . formatJam($presensi['waktu_pulang']) . '.');
        redirect('../guru/presensi.php');
    }

    $waktuPulang = date('H:i:s');

    try {
        $stmt = $pdo->prepare("UPDATE presensi_guru SET waktu_pulang = :waktu_pulang WHERE id = :id AND guru_id = :guru_id AND tanggal = :tanggal AND waktu_masuk IS NOT NULL AND waktu_pulang IS NULL");
        $stmt->execute(['waktu_pulang' => $waktuPulang, 'id' => (int) $presensi['id'], 'guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);

        if ($stmt->rowCount() === 0) {
            flash('error', 'Presensi pulang tidak dapat dicatat. Data mungkin sudah diperbarui.');
        } else {
            flash('success', 'Presensi pulang berhasil dicatat pada pukul ' . date('H:i', strtotime($waktuPulang)) . ' WIB.');
        }
    } catch (PDOException $e) {
        flash('error', 'Presensi pulang gagal dicatat. Silakan coba lagi.');
    }

    redirect('../guru/presensi.php');
}

redirect('../guru/presensi.php');