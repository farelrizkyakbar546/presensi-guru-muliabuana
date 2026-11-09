<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

date_default_timezone_set('Asia/Jakarta');
requireLogin();

$role = currentRole();
if (!in_array($role, ['adminTu', 'kepsek'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

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

$search = trim($_GET['search'] ?? '');
$tanggalAwal = sprintf('%04d-%02d-01', $tahun, $bulanAngka);
$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$labelPeriode = $namaBulan[$bulanAngka] . ' ' . $tahun;

$sql = "
    SELECT
        g.id, g.nip, g.nama_lengkap,
        COUNT(pg.id) AS total_presensi,
        SUM(CASE WHEN pg.status = 'Hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN pg.status = 'Izin' THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN pg.status = 'Alfa' THEN 1 ELSE 0 END) AS total_alfa
    FROM guru g
    LEFT JOIN presensi_guru pg ON pg.guru_id = g.id AND pg.tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
    WHERE g.status = 'aktif'
";

$params = [
    'tanggal_awal' => $tanggalAwal,
    'tanggal_akhir' => $tanggalAkhir,
];

if ($search !== '') {
    $sql .= " AND (g.nama_lengkap LIKE :search_nama OR g.nip LIKE :search_nip)";
    $searchValue = '%' . $search . '%';
    $params['search_nama'] = $searchValue;
    $params['search_nip'] = $searchValue;
}

$sql .= " GROUP BY g.id, g.nip, g.nama_lengkap ORDER BY g.nama_lengkap ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rekapList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grandHadir = 0;
$grandIzin = 0;
$grandAlfa = 0;
foreach ($rekapList as $row) {
    $grandHadir += (int) $row['total_hadir'];
    $grandIzin += (int) $row['total_izin'];
    $grandAlfa += (int) $row['total_alfa'];
}
$grandTotal = $grandHadir + $grandIzin + $grandAlfa;

$namaFile = 'rekap-presensi-guru-' . $bulan . '.csv';

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

fputcsv($output, ['REKAP PRESENSI GURU']);
fputcsv($output, ['SMKS Mulia Buana']);
fputcsv($output, ['Periode', $labelPeriode]);
fputcsv($output, ['Akses', $role === 'adminTu' ? 'AdminTU' : 'Kepala Sekolah']);
if ($search !== '') {
    fputcsv($output, ['Filter Pencarian', $search]);
}
fputcsv($output, []);

fputcsv($output, ['No', 'Nama Guru', 'NIP', 'Hadir', 'Izin', 'Alfa', 'Total', '% Kehadiran']);

$nomor = 1;
foreach ($rekapList as $row) {
    $totalPresensi = (int) $row['total_presensi'];
    $totalHadir = (int) $row['total_hadir'];
    $totalIzin = (int) $row['total_izin'];
    $totalAlfa = (int) $row['total_alfa'];
    $persen = $totalPresensi > 0 ? round(($totalHadir / $totalPresensi) * 100, 1) : 0;

    fputcsv($output, [
        $nomor++,
        $row['nama_lengkap'],
        ($row['nip'] !== null && $row['nip'] !== '') ? $row['nip'] : '-',
        $totalHadir,
        $totalIzin,
        $totalAlfa,
        $totalPresensi,
        $persen . '%'
    ]);
}

fputcsv($output, []);
fputcsv($output, ['RINGKASAN TOTAL']);
fputcsv($output, ['Total Hadir', $grandHadir]);
fputcsv($output, ['Total Izin', $grandIzin]);
fputcsv($output, ['Total Alfa', $grandAlfa]);
fputcsv($output, ['Total Presensi', $grandTotal]);
fputcsv($output, ['Total Guru', count($rekapList)]);
fputcsv($output, []);
fputcsv($output, ['Dicetak', date('d-m-Y H:i') . ' WIB']);

fclose($output);
exit;