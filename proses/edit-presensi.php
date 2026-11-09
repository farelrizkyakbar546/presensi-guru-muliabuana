<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Akses tidak valid.');
    redirect('../guru/presensi-siswa.php');
}

$userId = currentUserId();
$presensiId = (int) ($_POST['id'] ?? 0);
$statusBaru = trim($_POST['status'] ?? '');
$keteranganBaru = trim($_POST['keterangan'] ?? '');
$alasanKoreksi = trim($_POST['alasan_koreksi'] ?? '');

$statusValid = ['Hadir', 'Izin', 'Sakit', 'Alfa'];

if ($presensiId <= 0 || !in_array($statusBaru, $statusValid, true)) {
    flash('error', 'Data presensi atau status tidak valid.');
    redirect('../guru/presensi-siswa.php');
}

if ($alasanKoreksi === '' || strlen($alasanKoreksi) > 1000) {
    flash('error', 'Alasan koreksi wajib diisi (maksimal 1000 karakter).');
    redirect('../guru/presensi-siswa.php');
}

if (strlen($keteranganBaru) > 255) {
    flash('error', 'Keterangan maksimal 255 karakter.');
    redirect('../guru/presensi-siswa.php');
}

$stmt = $pdo->prepare("SELECT g.id, g.nama_lengkap FROM guru g INNER JOIN users u ON u.id = g.user_id WHERE g.user_id = ? AND g.status = 'aktif' AND u.status = 'aktif' LIMIT 1");
$stmt->execute([$userId]);
$guru = $stmt->fetch();

if (!$guru) {
    flash('error', 'Data guru aktif tidak ditemukan.');
    redirect('../guru/presensi-siswa.php');
}

$guruId = (int) $guru['id'];

$stmt = $pdo->prepare("
    SELECT ps.id, ps.siswa_id, ps.jadwal_id, ps.tanggal, ps.waktu, ps.status, ps.keterangan,
           s.nama_lengkap AS nama_siswa, s.nis,
           j.guru_id AS guru_jadwal_id, j.kelas_id, j.mapel_id, j.hari, j.jam_mulai, j.jam_selesai,
           m.nama_mapel, k.nama_kelas,
           pp.id AS pengampu_id, pp.guru_pengganti_id, pp.status AS status_pengampu
    FROM presensi_siswa ps
    INNER JOIN siswa s ON s.id = ps.siswa_id
    INNER JOIN jadwal j ON j.id = ps.jadwal_id
    INNER JOIN mapel m ON m.id = j.mapel_id
    INNER JOIN kelas k ON k.id = j.kelas_id
    LEFT JOIN pengampu pp ON pp.jadwal_id = ps.jadwal_id AND pp.tanggal = ps.tanggal AND pp.status IN ('Aktif', 'Selesai')
    WHERE ps.id = ?
    LIMIT 1
");
$stmt->execute([$presensiId]);
$presensi = $stmt->fetch();

if (!$presensi) {
    flash('error', 'Data presensi siswa tidak ditemukan.');
    redirect('../guru/presensi-siswa.php');
}

$guruBerwenang = ((int) $presensi['guru_jadwal_id'] === $guruId || ($presensi['guru_pengganti_id'] !== null && (int) $presensi['guru_pengganti_id'] === $guruId));

if (!$guruBerwenang) {
    flash('error', 'Anda tidak berwenang mengoreksi presensi siswa pada sesi ini.');
    redirect('../guru/presensi-siswa.php');
}

$waktuTutupString = $presensi['tanggal'] . ' ' . $presensi['jam_selesai'];
$waktuTutup = DateTime::createFromFormat('Y-m-d H:i:s', $waktuTutupString . ':00', new DateTimeZone('Asia/Jakarta'));
if (!$waktuTutup) {
    $waktuTutup = DateTime::createFromFormat('Y-m-d H:i:s', $presensi['tanggal'] . ' ' . $presensi['jam_selesai'], new DateTimeZone('Asia/Jakarta'));
}

if (!$waktuTutup) {
    flash('error', 'Waktu penutupan sesi tidak dapat ditentukan.');
    redirect('../guru/presensi-siswa.php');
}

$sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
$batasKoreksi = clone $waktuTutup;
$batasKoreksi->modify('+24 hours');

if ($sekarang > $batasKoreksi) {
    flash('error', 'Batas waktu koreksi presensi sudah lewat. Koreksi hanya dapat dilakukan maksimal 1×24 jam setelah sesi berakhir.');
    redirect('../guru/presensi-siswa.php');
}

$statusLama = $presensi['status'];
$keteranganLama = $presensi['keterangan'];
$keteranganBaruDb = $keteranganBaru !== '' ? $keteranganBaru : null;

$keteranganBerubah = (($keteranganLama ?? null) !== ($keteranganBaruDb ?? null));
$statusBerubah = ($statusLama !== $statusBaru);

if (!$statusBerubah && !$keteranganBerubah) {
    flash('error', 'Tidak ada perubahan data presensi.');
    redirect('../guru/presensi-siswa.php');
}

$waktuKoreksi = $sekarang->format('Y-m-d H:i:s');

$dataSebelum = [
    'id' => (int) $presensi['id'],
    'siswa_id' => (int) $presensi['siswa_id'],
    'nama_siswa' => $presensi['nama_siswa'],
    'nis' => $presensi['nis'],
    'jadwal_id' => (int) $presensi['jadwal_id'],
    'tanggal' => $presensi['tanggal'],
    'waktu' => $presensi['waktu'],
    'status' => $statusLama,
    'keterangan' => $keteranganLama
];

$dataSesudah = [
    'id' => (int) $presensi['id'],
    'siswa_id' => (int) $presensi['siswa_id'],
    'nama_siswa' => $presensi['nama_siswa'],
    'nis' => $presensi['nis'],
    'jadwal_id' => (int) $presensi['jadwal_id'],
    'tanggal' => $presensi['tanggal'],
    'waktu' => $presensi['waktu'],
    'status' => $statusBaru,
    'keterangan' => $keteranganBaruDb,
    'waktu_koreksi' => $waktuKoreksi
];

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE presensi_siswa SET status = ?, keterangan = ? WHERE id = ?");
    $stmt->execute([$statusBaru, $keteranganBaruDb, $presensiId]);

    $stmt = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (?, 'presensi_siswa', ?, 'UPDATE', ?, ?, ?)");
    $stmt->execute([
        $userId,
        $presensiId,
        json_encode($dataSebelum, JSON_UNESCAPED_UNICODE),
        json_encode($dataSesudah, JSON_UNESCAPED_UNICODE),
        $alasanKoreksi
    ]);

    $pdo->commit();
    flash('success', 'Presensi siswa berhasil dikoreksi dan perubahan telah dicatat dalam audit trail.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', 'Koreksi presensi gagal disimpan.');
}

redirect('../guru/presensi-siswa.php');