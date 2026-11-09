<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

$aksi = $_POST['aksi'] ?? '';
$hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

if ($aksi === 'tambah' || $aksi === 'edit') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $guruId = isset($_POST['guru_id']) ? (int) $_POST['guru_id'] : 0;
    $mapelId = isset($_POST['mapel_id']) ? (int) $_POST['mapel_id'] : 0;
    $kelasId = isset($_POST['kelas_id']) ? (int) $_POST['kelas_id'] : 0;
    $hari = trim($_POST['hari'] ?? '');
    $jamMulai = trim($_POST['jam_mulai'] ?? '');
    $jamSelesai = trim($_POST['jam_selesai'] ?? '');

    if ($guruId <= 0 || $mapelId <= 0 || $kelasId <= 0 || $hari === '' || $jamMulai === '' || $jamSelesai === '') {
        flash('error', 'Semua data jadwal wajib diisi.');
        redirect('../admintu/jadwal.php');
    }

    if (!in_array($hari, $hariList, true)) {
        flash('error', 'Hari yang dipilih tidak valid.');
        redirect('../admintu/jadwal.php');
    }

    // Normalisasi jika jam diketik 1 digit (misal 7:15 -> 07:15)
    if (preg_match('/^\d{1}:\d{2}$/', $jamMulai)) {
        $jamMulai = '0' . $jamMulai;
    }
    if (preg_match('/^\d{1}:\d{2}$/', $jamSelesai)) {
        $jamSelesai = '0' . $jamSelesai;
    }

    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $jamMulai) || !preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $jamSelesai)) {
        flash('error', 'Format jam tidak valid. Harap gunakan format 24 jam (00:00 - 23:59), contoh: 07:15 atau 13:00.');
        redirect('../admintu/jadwal.php');
    }

    if ($jamMulai >= $jamSelesai) {
        flash('error', 'Jam selesai harus lebih besar dari jam mulai.');
        redirect('../admintu/jadwal.php');
    }

    $dataLama = null;
    if ($aksi === 'edit') {
        if ($id <= 0) {
            flash('error', 'ID jadwal tidak valid.');
            redirect('../admintu/jadwal.php');
        }

        $stmt = $pdo->prepare("
            SELECT id, guru_id, kelas_id, mapel_id, hari, jam_mulai, jam_selesai
            FROM jadwal
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $dataLama = $stmt->fetch();

        if (!$dataLama) {
            flash('error', 'Jadwal tidak ditemukan.');
            redirect('../admintu/jadwal.php');
        }
    }

    $stmt = $pdo->prepare("SELECT id, nama_lengkap FROM guru WHERE id = ? AND status = 'aktif' LIMIT 1");
    $stmt->execute([$guruId]);
    if (!$stmt->fetch()) {
        flash('error', 'Guru tidak ditemukan atau tidak aktif.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("SELECT id, kode_mapel, nama_mapel FROM mapel WHERE id = ? LIMIT 1");
    $stmt->execute([$mapelId]);
    if (!$stmt->fetch()) {
        flash('error', 'Mata pelajaran tidak ditemukan.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("
        SELECT k.id, k.nama_kelas, k.tingkat, k.jurusan_id, j.nama_jurusan
        FROM kelas k
        INNER JOIN jurusan j ON j.id = k.jurusan_id
        WHERE k.id = ?
        LIMIT 1
    ");
    $stmt->execute([$kelasId]);
    if (!$stmt->fetch()) {
        flash('error', 'Kelas tidak ditemukan.');
        redirect('../admintu/jadwal.php');
    }

    $sql = "SELECT id FROM jadwal WHERE guru_id = ? AND kelas_id = ? AND mapel_id = ? AND hari = ? AND jam_mulai = ? AND jam_selesai = ?";
    $params = [$guruId, $kelasId, $mapelId, $hari, $jamMulai, $jamSelesai];
    if ($aksi === 'edit') {
        $sql .= " AND id != ?";
        $params[] = $id;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        flash('error', 'Jadwal yang sama sudah tersedia.');
        redirect('../admintu/jadwal.php');
    }

    $sql = "
        SELECT j.id, j.jam_mulai, j.jam_selesai, k.nama_kelas, m.nama_mapel
        FROM jadwal j
        INNER JOIN kelas k ON k.id = j.kelas_id
        INNER JOIN mapel m ON m.id = j.mapel_id
        WHERE j.guru_id = ? AND j.hari = ? AND j.jam_mulai < ? AND j.jam_selesai > ?
    ";
    $params = [$guruId, $hari, $jamSelesai, $jamMulai];
    if ($aksi === 'edit') {
        $sql .= " AND j.id != ?";
        $params[] = $id;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bentrokGuru = $stmt->fetch();
    if ($bentrokGuru) {
        flash('error', 'Jadwal bentrok. Guru tersebut sudah memiliki jadwal ' . substr($bentrokGuru['jam_mulai'], 0, 5) . ' - ' . substr($bentrokGuru['jam_selesai'], 0, 5) . ' pada hari tersebut.');
        redirect('../admintu/jadwal.php');
    }

    $sql = "
        SELECT j.id, j.jam_mulai, j.jam_selesai, g.nama_lengkap, m.nama_mapel
        FROM jadwal j
        INNER JOIN guru g ON g.id = j.guru_id
        INNER JOIN mapel m ON m.id = j.mapel_id
        WHERE j.kelas_id = ? AND j.hari = ? AND j.jam_mulai < ? AND j.jam_selesai > ?
    ";
    $params = [$kelasId, $hari, $jamSelesai, $jamMulai];
    if ($aksi === 'edit') {
        $sql .= " AND j.id != ?";
        $params[] = $id;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bentrokKelas = $stmt->fetch();
    if ($bentrokKelas) {
        flash('error', 'Jadwal bentrok. Kelas tersebut sudah memiliki jadwal ' . substr($bentrokKelas['jam_mulai'], 0, 5) . ' - ' . substr($bentrokKelas['jam_selesai'], 0, 5) . '.');
        redirect('../admintu/jadwal.php');
    }

    try {
        $pdo->beginTransaction();
        $userId = currentUserId();

        if ($aksi === 'tambah') {
            $stmt = $pdo->prepare("
                INSERT INTO jadwal (guru_id, kelas_id, mapel_id, hari, jam_mulai, jam_selesai)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$guruId, $kelasId, $mapelId, $hari, $jamMulai, $jamSelesai]);
            $jadwalId = (int) $pdo->lastInsertId();

            $dataSesudah = [
                'id' => $jadwalId, 'guru_id' => $guruId, 'kelas_id' => $kelasId,
                'mapel_id' => $mapelId, 'hari' => $hari, 'jam_mulai' => $jamMulai, 'jam_selesai' => $jamSelesai
            ];

            $stmtAudit = $pdo->prepare("
                INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtAudit->execute([
                $userId, 'jadwal', $jadwalId, 'INSERT', null,
                json_encode($dataSesudah, JSON_UNESCAPED_UNICODE),
                'Penambahan jadwal mengajar oleh Admin TU.'
            ]);

            $pdo->commit();
            flash('success', 'Jadwal berhasil ditambahkan.');
            redirect('../admintu/jadwal.php');
        }

        $stmt = $pdo->prepare("
            UPDATE jadwal
            SET guru_id = ?, kelas_id = ?, mapel_id = ?, hari = ?, jam_mulai = ?, jam_selesai = ?
            WHERE id = ?
        ");
        $stmt->execute([$guruId, $kelasId, $mapelId, $hari, $jamMulai, $jamSelesai, $id]);

        $dataSesudah = [
            'id' => $id, 'guru_id' => $guruId, 'kelas_id' => $kelasId,
            'mapel_id' => $mapelId, 'hari' => $hari, 'jam_mulai' => $jamMulai, 'jam_selesai' => $jamSelesai
        ];

        $stmtAudit = $pdo->prepare("
            INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtAudit->execute([
            $userId, 'jadwal', $id, 'UPDATE',
            json_encode($dataLama, JSON_UNESCAPED_UNICODE),
            json_encode($dataSesudah, JSON_UNESCAPED_UNICODE),
            'Perubahan jadwal mengajar oleh Admin TU.'
        ]);

        $pdo->commit();
        flash('success', 'Jadwal berhasil diperbarui.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Jadwal gagal disimpan. Silakan periksa kembali data yang dimasukkan.');
    }

    redirect('../admintu/jadwal.php');
}

if ($aksi === 'hapus') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        flash('error', 'ID jadwal tidak valid.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("
        SELECT j.id, j.guru_id, j.kelas_id, j.mapel_id, j.hari, j.jam_mulai, j.jam_selesai,
               g.nama_lengkap AS nama_guru, m.nama_mapel, k.nama_kelas
        FROM jadwal j
        INNER JOIN guru g ON g.id = j.guru_id
        INNER JOIN mapel m ON m.id = j.mapel_id
        INNER JOIN kelas k ON k.id = j.kelas_id
        WHERE j.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $dataLama = $stmt->fetch();

    if (!$dataLama) {
        flash('error', 'Jadwal tidak ditemukan.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM presensi_siswa WHERE jadwal_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Jadwal tidak dapat dihapus karena sudah digunakan dalam data presensi siswa.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM perizinan_jadwal WHERE jadwal_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Jadwal tidak dapat dihapus karena sudah terkait dengan data perizinan guru.');
        redirect('../admintu/jadwal.php');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pengampu WHERE jadwal_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('error', 'Jadwal tidak dapat dihapus karena sudah memiliki riwayat pengampu/pengganti.');
        redirect('../admintu/jadwal.php');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt->execute([$id]);

        $userId = currentUserId();
        $stmtAudit = $pdo->prepare("
            INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtAudit->execute([
            $userId, 'jadwal', $id, 'DELETE',
            json_encode($dataLama, JSON_UNESCAPED_UNICODE), null,
            'Penghapusan jadwal mengajar oleh Admin TU.'
        ]);

        $pdo->commit();
        flash('success', 'Jadwal berhasil dihapus.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', 'Jadwal gagal dihapus. Data mungkin masih digunakan oleh sistem.');
    }

    redirect('../admintu/jadwal.php');
}

flash('error', 'Aksi tidak valid.');
redirect('../admintu/jadwal.php');