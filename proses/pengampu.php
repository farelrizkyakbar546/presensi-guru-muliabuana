<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();

$stmt = $pdo->prepare("SELECT g.id, g.nama_lengkap FROM guru g INNER JOIN users u ON u.id = g.user_id WHERE g.user_id = ? AND g.status = 'aktif' AND u.status = 'aktif' LIMIT 1");
$stmt->execute([$userId]);
$guru = $stmt->fetch();

if (!$guru) {
    flash('error', 'Data guru aktif tidak ditemukan.');
    redirect('../guru/dashboard.php');
}

$guruPiketId = (int) $guru['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Akses tidak valid.');
    redirect('../guru/dashboard_piket.php');
}

$aksi = $_POST['aksi'] ?? '';
$jadwalId = (int) ($_POST['jadwal_id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$sumberInput = trim($_POST['sumber'] ?? '');

if ($aksi !== 'ambil_alih' || $jadwalId <= 0) {
    flash('error', 'Aksi atau jadwal tidak valid.');
    redirect('../guru/dashboard_piket.php');
}

$tanggalHariIni = date('Y-m-d');
$waktuSekarang = date('H:i:s');

if ($tanggal !== $tanggalHariIni) {
    flash('error', 'Pengambilalihan hanya dapat dilakukan untuk jadwal hari ini.');
    redirect('../guru/dashboard_piket.php');
}

$namaHari = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hariIni = $namaHari[date('l')];

$stmt = $pdo->prepare("SELECT id, keterangan FROM jadwal_piket WHERE guru_id = ? AND hari = ? LIMIT 1");
$stmt->execute([$guruPiketId, $hariIni]);
$piketHariIni = $stmt->fetch();

if (!$piketHariIni) {
    http_response_code(403);
    die('Akses ditolak. Anda tidak bertugas sebagai Guru Piket hari ini.');
}

$stmt = $pdo->prepare("
    SELECT j.id, j.guru_id, j.kelas_id, j.mapel_id, j.hari, j.jam_mulai, j.jam_selesai,
           g.nama_lengkap AS nama_guru, m.nama_mapel, k.nama_kelas, k.tingkat
    FROM jadwal j
    INNER JOIN guru g ON g.id = j.guru_id
    INNER JOIN mapel m ON m.id = j.mapel_id
    INNER JOIN kelas k ON k.id = j.kelas_id
    WHERE j.id = ?
    LIMIT 1
");
$stmt->execute([$jadwalId]);
$jadwal = $stmt->fetch();

if (!$jadwal || $jadwal['hari'] !== $hariIni) {
    flash('error', 'Jadwal tidak ditemukan atau bukan jadwal hari ini.');
    redirect('../guru/dashboard_piket.php');
}

if ((int) $jadwal['guru_id'] === $guruPiketId) {
    flash('error', 'Anda tidak dapat mengambil alih jadwal Anda sendiri.');
    redirect('../guru/dashboard_piket.php');
}

$stmt = $pdo->prepare("SELECT id, nama_lengkap FROM guru WHERE id = ? AND status = 'aktif' LIMIT 1");
$stmt->execute([(int) $jadwal['guru_id']]);
if (!$stmt->fetch()) {
    flash('error', 'Guru yang terjadwal tidak aktif atau tidak ditemukan.');
    redirect('../guru/dashboard_piket.php');
}

$stmt = $pdo->prepare("SELECT id, waktu_masuk, waktu_pulang, status FROM presensi_guru WHERE guru_id = ? AND tanggal = ? LIMIT 1");
$stmt->execute([(int) $jadwal['guru_id'], $tanggalHariIni]);
$presensiGuruAsal = $stmt->fetch();

if ($presensiGuruAsal && !empty($presensiGuruAsal['waktu_masuk'])) {
    flash('error', 'Guru terjadwal sudah melakukan presensi datang. Jadwal tidak perlu diambil alih.');
    redirect('../guru/dashboard_piket.php');
}

if ($waktuSekarang < $jadwal['jam_mulai']) {
    flash('error', 'Jadwal tersebut belum dimulai.');
    redirect('../guru/dashboard_piket.php');
}

$stmt = $pdo->prepare("SELECT id, guru_asal_id, guru_pengganti_id, sumber, status FROM pengampu WHERE jadwal_id = ? AND tanggal = ? LIMIT 1");
$stmt->execute([$jadwalId, $tanggalHariIni]);
$pengampuExisting = $stmt->fetch();

if ($pengampuExisting && $pengampuExisting['status'] === 'Aktif') {
    flash('error', (int) $pengampuExisting['guru_pengganti_id'] === $guruPiketId ? 'Anda sudah mengambil alih jadwal ini.' : 'Jadwal ini sudah diambil alih oleh guru lain.');
    redirect('../guru/dashboard_piket.php');
}

if ($pengampuExisting && $pengampuExisting['status'] === 'Selesai') {
    flash('error', 'Pengambilalihan jadwal ini sudah selesai.');
    redirect('../guru/dashboard_piket.php');
}

$sumber = 'Ketidakhadiran';
if ($sumberInput === 'Perizinan') {
    $stmt = $pdo->prepare("SELECT p.id FROM perizinan_jadwal pj INNER JOIN perizinan p ON p.id = pj.perizinan_id WHERE pj.jadwal_id = ? AND p.guru_id = ? AND ? BETWEEN p.tanggal_mulai AND p.tanggal_selesai AND p.status = 'Disetujui' LIMIT 1");
    $stmt->execute([$jadwalId, (int) $jadwal['guru_id'], $tanggalHariIni]);
    if (!$stmt->fetch()) {
        flash('error', 'Tidak ditemukan perizinan yang disetujui untuk jadwal ini.');
        redirect('../guru/dashboard_piket.php');
    }
    $sumber = 'Perizinan';
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, status FROM pengampu WHERE jadwal_id = ? AND tanggal = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$jadwalId, $tanggalHariIni]);
    $pengampuLock = $stmt->fetch();

    if ($pengampuLock && $pengampuLock['status'] !== 'Dibatalkan') {
        $pdo->rollBack();
        flash('error', 'Jadwal ini sudah memiliki data pengambilalihan.');
        redirect('../guru/dashboard_piket.php');
    }

    $stmt = $pdo->prepare("INSERT INTO pengampu (jadwal_id, tanggal, guru_asal_id, guru_pengganti_id, sumber, status, waktu_mulai, waktu_selesai, created_by) VALUES (?, ?, ?, ?, ?, 'Aktif', ?, NULL, ?)");
    $stmt->execute([$jadwalId, $tanggalHariIni, (int) $jadwal['guru_id'], $guruPiketId, $sumber, $waktuSekarang, $userId]);
    $pengampuId = (int) $pdo->lastInsertId();

    $dataSesudah = [
        'id' => $pengampuId,
        'jadwal_id' => $jadwalId,
        'tanggal' => $tanggalHariIni,
        'guru_asal_id' => (int) $jadwal['guru_id'],
        'guru_pengganti_id' => $guruPiketId,
        'sumber' => $sumber,
        'status' => 'Aktif',
        'waktu_mulai' => $waktuSekarang,
        'waktu_selesai' => null,
        'created_by' => $userId
    ];

    $alasanAudit = ($sumber === 'Perizinan')
        ? 'Pengambilalihan jadwal berdasarkan perizinan guru yang telah disetujui.'
        : 'Pengambilalihan jadwal berdasarkan ketidakhadiran guru yang belum melakukan presensi datang.';

    $stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (?, 'pengampu', ?, 'INSERT', NULL, ?, ?)");
    $stmt->execute([$userId, $pengampuId, json_encode($dataSesudah, JSON_UNESCAPED_UNICODE), $alasanAudit]);

    $pdo->commit();
    flash('success', 'Jadwal ' . $jadwal['nama_mapel'] . ' untuk kelas ' . $jadwal['nama_kelas'] . ' berhasil diambil alih.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Jadwal gagal diambil alih. Silakan periksa kembali status jadwal tersebut.');
}

redirect('../guru/dashboard_piket.php');