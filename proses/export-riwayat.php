<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

date_default_timezone_set('Asia/Jakarta');
requireRole('guru');

$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT id, nip, nama_lengkap, status
    FROM guru
    WHERE user_id = :user_id
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    http_response_code(403);
    exit('Data guru tidak ditemukan.');
}

if ($guru['status'] !== 'aktif') {
    http_response_code(403);
    exit('Akun guru tidak aktif.');
}

$guruId = (int) $guru['id'];

$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan = date('Y-m');
}

$tahun = (int) substr($bulan, 0, 4);
$bulanAngka = (int) substr($bulan, 5, 2);
if ($tahun < 2000 || $tahun > 2100 || $bulanAngka < 1 || $bulanAngka > 12) {
    $bulan = date('Y-m');
    $tahun = (int) substr($bulan, 0, 4);
    $bulanAngka = (int) substr($bulan, 5, 2);
}

$tanggalAwal = sprintf('%04d-%02d-01', $tahun, $bulanAngka);
$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$labelPeriode = $namaBulan[$bulanAngka] . ' ' . $tahun;

$stmt = $pdo->prepare("
    SELECT tanggal, waktu_masuk, waktu_pulang, status, keterangan
    FROM presensi_guru
    WHERE guru_id = :guru_id AND tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
    ORDER BY tanggal ASC, waktu_masuk ASC
");
$stmt->execute([
    'guru_id' => $guruId,
    'tanggal_awal' => $tanggalAwal,
    'tanggal_akhir' => $tanggalAkhir
]);
$riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalHadir = 0;
$totalIzin = 0;
$totalAlfa = 0;
foreach ($riwayat as $data) {
    if ($data['status'] === 'Hadir') $totalHadir++;
    if ($data['status'] === 'Izin') $totalIzin++;
    if ($data['status'] === 'Alfa') $totalAlfa++;
}
$totalPresensi = count($riwayat);

$namaHari = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$identitas = !empty($guru['nip']) ? preg_replace('/[^A-Za-z0-9_-]/', '', $guru['nip']) : 'guru-' . $guruId;
$namaFile = 'riwayat-presensi-guru-' . $identitas . '-' . $bulan . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');
if ($output === false) {
    http_response_code(500);
    exit('Gagal membuat file export.');
}

fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['LAPORAN RIWAYAT PRESENSI GURU']);
fputcsv($output, ['SMKS Mulia Buana']);
fputcsv($output, ['Nama', $guru['nama_lengkap']]);
fputcsv($output, ['NIP', !empty($guru['nip']) ? $guru['nip'] : '-']);
fputcsv($output, ['Periode', $labelPeriode]);
fputcsv($output, []);

fputcsv($output, ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan']);

$nomor = 1;
foreach ($riwayat as $data) {
    $tanggal = $data['tanggal'];
    $hari = $namaHari[date('l', strtotime($tanggal))] ?? '-';
    $jamMasuk = !empty($data['waktu_masuk']) ? date('H:i', strtotime($data['waktu_masuk'])) : '-';
    $jamPulang = !empty($data['waktu_pulang']) ? substr($data['waktu_pulang'], 0, 5) : '-';

    fputcsv($output, [
        $nomor++,
        $tanggal,
        $hari,
        $jamMasuk,
        $jamPulang,
        $data['status'],
        !empty($data['keterangan']) ? $data['keterangan'] : '-'
    ]);
}

fputcsv($output, []);
fputcsv($output, ['RINGKASAN']);
fputcsv($output, ['Total Presensi', $totalPresensi]);
fputcsv($output, ['Hadir', $totalHadir]);
fputcsv($output, ['Izin', $totalIzin]);
fputcsv($output, ['Alfa', $totalAlfa]);
fputcsv($output, []);
fputcsv($output, ['Dicetak', date('d-m-Y H:i') . ' WIB']);

fclose($output);
exit;