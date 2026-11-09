<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Akses tidak valid.');
    redirect('../admintu/jadwal-piket.php');
}

$aksi = $_POST['aksi'] ?? '';
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$userId = currentUserId();

if ($aksi === 'tambah' || $aksi === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $guruId = (int) ($_POST['guru_id'] ?? 0);
    $hari = trim($_POST['hari'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($aksi === 'edit' && $id <= 0) {
        flash('error', 'ID jadwal piket tidak valid.');
        redirect('../admintu/jadwal-piket.php');
    }

    if ($guruId <= 0) {
        flash('error', 'Guru wajib dipilih.');
        redirect('../admintu/jadwal-piket.php');
    }

    if ($hari === '') {
        flash('error', 'Hari wajib dipilih.');
        redirect('../admintu/jadwal-piket.php');
    }

    if (!in_array($hari, $hariList, true)) {
        flash('error', 'Hari tidak valid.');
        redirect('../admintu/jadwal-piket.php');
    }

    if (strlen($keterangan) > 255) {
        flash('error', 'Keterangan maksimal 255 karakter.');
        redirect('../admintu/jadwal-piket.php');
    }

    $stmt = $pdo->prepare("SELECT id, nama_lengkap FROM guru WHERE id = ? AND status = 'aktif' LIMIT 1");
    $stmt->execute([$guruId]);
    if (!$stmt->fetch()) {
        flash('error', 'Guru tidak ditemukan atau status guru tidak aktif.');
        redirect('../admintu/jadwal-piket.php');
    }

    if ($aksi === 'edit') {
        $stmt = $pdo->prepare("SELECT id FROM jadwal_piket WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            flash('error', 'Jadwal piket tidak ditemukan.');
            redirect('../admintu/jadwal-piket.php');
        }
    }

    $sql = "SELECT id FROM jadwal_piket WHERE guru_id = ? AND hari = ?";
    $params = [$guruId, $hari];
    if ($aksi === 'edit') {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        flash('error', 'Guru tersebut sudah memiliki jadwal piket pada hari tersebut.');
        redirect('../admintu/jadwal-piket.php');
    }

    try {
        $pdo->beginTransaction();
        $keteranganDb = $keterangan !== '' ? $keterangan : null;

        if ($aksi === 'tambah') {
            $stmt = $pdo->prepare("INSERT INTO jadwal_piket (guru_id, hari, keterangan) VALUES (?, ?, ?)");
            $stmt->execute([$guruId, $hari, $keteranganDb]);
            $jadwalPiketId = (int) $pdo->lastInsertId();

            $dataSesudah = [
                'id' => $jadwalPiketId, 'guru_id' => $guruId,
                'hari' => $hari, 'keterangan' => $keteranganDb
            ];

            $stmt = $pdo->prepare("
                INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId, 'jadwal_piket', $jadwalPiketId, 'INSERT', null,
                json_encode($dataSesudah, JSON_UNESCAPED_UNICODE),
                'Menambahkan jadwal piket guru.'
            ]);

            $pdo->commit();
            flash('success', 'Jadwal piket berhasil ditambahkan.');
            redirect('../admintu/jadwal-piket.php');
        }

        $stmt = $pdo->prepare("SELECT id, guru_id, hari, keterangan FROM jadwal_piket WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $dataSebelum = $stmt->fetch();

        if (!$dataSebelum) {
            $pdo->rollBack();
            flash('error', 'Jadwal piket tidak ditemukan.');
            redirect('../admintu/jadwal-piket.php');
        }

        $stmt = $pdo->prepare("UPDATE jadwal_piket SET guru_id = ?, hari = ?, keterangan = ? WHERE id = ?");
        $stmt->execute([$guruId, $hari, $keteranganDb, $id]);

        $dataSesudah = [
            'id' => $id, 'guru_id' => $guruId,
            'hari' => $hari, 'keterangan' => $keteranganDb
        ];

        $stmt = $pdo->prepare("
                INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, 'jadwal_piket', $id, 'UPDATE',
            json_encode($dataSebelum, JSON_UNESCAPED_UNICODE),
            json_encode($dataSesudah, JSON_UNESCAPED_UNICODE),
            'Memperbarui jadwal piket guru.'
        ]);

        $pdo->commit();
        flash('success', 'Jadwal piket berhasil diperbarui.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Jadwal piket gagal disimpan.');
    }

    redirect('../admintu/jadwal-piket.php');
}

if ($aksi === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        flash('error', 'ID jadwal piket tidak valid.');
        redirect('../admintu/jadwal-piket.php');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id, guru_id, hari, keterangan FROM jadwal_piket WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $dataSebelum = $stmt->fetch();

        if (!$dataSebelum) {
            $pdo->rollBack();
            flash('error', 'Jadwal piket tidak ditemukan.');
            redirect('../admintu/jadwal-piket.php');
        }

        $stmt = $pdo->prepare("DELETE FROM jadwal_piket WHERE id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("
            INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, 'jadwal_piket', $id, 'DELETE',
            json_encode($dataSebelum, JSON_UNESCAPED_UNICODE), null,
            'Menghapus jadwal piket guru.'
        ]);

        $pdo->commit();
        flash('success', 'Jadwal piket berhasil dihapus.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Jadwal piket gagal dihapus.');
    }

    redirect('../admintu/jadwal-piket.php');
}

flash('error', 'Aksi tidak valid.');
redirect('../admintu/jadwal-piket.php');