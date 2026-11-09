<?php

date_default_timezone_set('Asia/Jakarta');

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('guru');

$userId = currentUserId();
$jadwalId = (int) ($_POST['jadwal_id'] ?? 0);
$statusData = $_POST['status'] ?? [];

$hariIndonesia = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

$tanggal = date('Y-m-d');
$waktuSekarang = date('Y-m-d H:i:s');
$jamSekarang = date('H:i:s');
$hari = $hariIndonesia[date('l')] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $jadwalId <= 0 || !is_array($statusData)) {
    flash('error', 'Data presensi tidak valid.');
    redirect('../guru/presensi-siswa.php');
}

$stmt = $pdo->prepare("SELECT g.id, g.nama_lengkap FROM guru g INNER JOIN users u ON u.id = g.user_id WHERE g.user_id = ? AND g.status = 'aktif' AND u.status = 'aktif' LIMIT 1");
$stmt->execute([$userId]);
$guru = $stmt->fetch();

if (!$guru) {
    flash('error', 'Data guru tidak ditemukan atau akun guru tidak aktif.');
    redirect('../guru/presensi-siswa.php');
}

$guruId = (int) $guru['id'];

$stmt = $pdo->prepare("SELECT j.id, j.guru_id, j.kelas_id, j.mapel_id, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas, 'Normal' AS jenis_jadwal, NULL AS pengampu_id, NULL AS guru_asal_id FROM jadwal j INNER JOIN guru g ON g.id = j.guru_id INNER JOIN mapel m ON m.id = j.mapel_id INNER JOIN kelas k ON k.id = j.kelas_id WHERE j.id = ? AND j.guru_id = ? AND g.status = 'aktif' AND j.hari = ? LIMIT 1");
$stmt->execute([$jadwalId, $guruId, $hari]);
$jadwal = $stmt->fetch();

if (!$jadwal) {
    $stmt = $pdo->prepare("SELECT j.id, j.guru_id, j.kelas_id, j.mapel_id, j.hari, j.jam_mulai, j.jam_selesai, m.nama_mapel, k.nama_kelas, 'Pengganti' AS jenis_jadwal, p.id AS pengampu_id, p.guru_asal_id FROM pengampu p INNER JOIN jadwal j ON j.id = p.jadwal_id INNER JOIN guru gp ON gp.id = p.guru_pengganti_id INNER JOIN mapel m ON m.id = j.mapel_id INNER JOIN kelas k ON k.id = j.kelas_id WHERE p.jadwal_id = ? AND p.guru_pengganti_id = ? AND p.tanggal = ? AND p.status = 'Aktif' AND gp.status = 'aktif' AND j.hari = ? LIMIT 1");
    $stmt->execute([$jadwalId, $guruId, $tanggal, $hari]);
    $jadwal = $stmt->fetch();
}

if (!$jadwal) {
    flash('error', 'Jadwal tidak valid atau Anda tidak memiliki hak untuk melakukan presensi pada jadwal tersebut.');
    redirect('../guru/presensi-siswa.php');
}

if ($jamSekarang < $jadwal['jam_mulai']) {
    flash('error', 'Presensi siswa belum dibuka. Presensi baru dapat dilakukan saat KBM dimulai.');
    redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
}

if ($jamSekarang >= $jadwal['jam_selesai']) {
    flash('error', 'Sesi KBM sudah selesai. Presensi siswa tidak dapat dilakukan lagi.');
    redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
}

if ($jadwal['jenis_jadwal'] === 'Normal') {
    $stmt = $pdo->prepare("SELECT p.id FROM perizinan_jadwal pj INNER JOIN perizinan p ON p.id = pj.perizinan_id WHERE pj.jadwal_id = ? AND p.guru_id = ? AND p.status = 'Disetujui' AND ? BETWEEN p.tanggal_mulai AND p.tanggal_selesai LIMIT 1");
    $stmt->execute([$jadwalId, $guruId, $tanggal]);
    if ($stmt->fetch()) {
        flash('error', 'Anda memiliki perizinan yang telah disetujui untuk jadwal ini hari ini. Presensi siswa tidak dapat dilakukan.');
        redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
    }
}

$stmt = $pdo->prepare("SELECT id FROM siswa WHERE kelas_id = ? AND status = 'aktif' ORDER BY id");
$stmt->execute([$jadwal['kelas_id']]);
$siswaAktif = $stmt->fetchAll();

if (!$siswaAktif) {
    flash('error', 'Belum ada siswa aktif pada kelas tersebut.');
    redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
}

$statusValid = ['Hadir', 'Izin', 'Sakit', 'Alfa'];

foreach ($siswaAktif as $siswa) {
    $siswaId = (int) $siswa['id'];
    if (!array_key_exists($siswaId, $statusData) || !in_array($statusData[$siswaId], $statusValid, true)) {
        flash('error', 'Status presensi seluruh siswa harus dipilih secara valid.');
        redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
    }
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, guru_id, kelas_id, hari, jam_mulai, jam_selesai FROM jadwal WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$jadwalId]);
    $jadwalLock = $stmt->fetch();

    if (!$jadwalLock) {
        $pdo->rollBack();
        flash('error', 'Jadwal tidak ditemukan.');
        redirect('../guru/presensi-siswa.php');
    }

    $jamSekarang = date('H:i:s');
    if ($jamSekarang < $jadwalLock['jam_mulai'] || $jamSekarang >= $jadwalLock['jam_selesai']) {
        $pdo->rollBack();
        flash('error', 'Waktu presensi berada di luar jam operasional sesi KBM.');
        redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
    }

    $stmt = $pdo->prepare("SELECT id FROM presensi_siswa WHERE jadwal_id = ? AND tanggal = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$jadwalId, $tanggal]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        flash('error', 'Presensi siswa untuk sesi ini sudah tersimpan. Pengisian kedua diblokir. Jika ada perubahan, gunakan proses koreksi.');
        redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);
    }

    $stmtInsert = $pdo->prepare("INSERT INTO presensi_siswa (siswa_id, jadwal_id, tanggal, waktu, status, keterangan, dicatat_oleh) VALUES (?, ?, ?, ?, ?, NULL, ?)");
    $stmtAudit = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (?, 'presensi_siswa', ?, 'INSERT', NULL, ?, 'Pencatatan awal presensi siswa sesi KBM.')");

    foreach ($siswaAktif as $siswa) {
        $siswaId = (int) $siswa['id'];
        $statusBaru = $statusData[$siswaId];

        $stmtInsert->execute([$siswaId, $jadwalId, $tanggal, $waktuSekarang, $statusBaru, $userId]);
        $newPresensiId = (int) $pdo->lastInsertId();

        $auditPayload = json_encode([
            'id' => $newPresensiId,
            'siswa_id' => $siswaId,
            'jadwal_id' => $jadwalId,
            'tanggal' => $tanggal,
            'waktu' => $waktuSekarang,
            'status' => $statusBaru,
            'dicatat_oleh' => $userId
        ], JSON_UNESCAPED_UNICODE);

        $stmtAudit->execute([$userId, $newPresensiId, $auditPayload]);
    }

    $pdo->commit();
    flash('success', 'Presensi siswa berhasil disimpan. Sesi ini telah tercatat.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        flash('error', 'Presensi siswa untuk sesi ini sudah tersimpan. Pengisian kedua diblokir.');
    } else {
        flash('error', 'Presensi siswa gagal disimpan. Silakan coba kembali.');
    }
}

redirect('../guru/presensi-siswa.php?jadwal_id=' . $jadwalId);