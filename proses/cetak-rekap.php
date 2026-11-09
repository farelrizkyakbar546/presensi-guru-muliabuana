<?php
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

$sekolah = file_exists(__DIR__ . '/../config/sekolah.php')
    ? require __DIR__ . '/../config/sekolah.php'
    : [];

requireAnyRole(['adminTu', 'kepsek']);

$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {
    $bulan = date('Y-m');
}

$search = trim($_GET['search'] ?? '');
$tanggalAwal  = $bulan . '-01';
$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));

$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$bulanAngka = (int) substr($bulan, 5, 2);
$tahunAngka = substr($bulan, 0, 4);
$labelPeriode = ($namaBulan[$bulanAngka] ?? '') . ' ' . $tahunAngka;

$sql = "
    SELECT
        g.id,
        g.nip,
        g.nama_lengkap,
        COALESCE(m.nama_mapel, '-') AS nama_mapel,
        COUNT(pg.id) AS total_presensi,
        SUM(CASE WHEN pg.status = 'Hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN pg.status = 'Izin'  THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN pg.status = 'Alfa'  THEN 1 ELSE 0 END) AS total_alfa
    FROM guru g
    LEFT JOIN mapel m ON m.id = g.mapel_utama_id
    LEFT JOIN presensi_guru pg
        ON pg.guru_id = g.id
        AND pg.tanggal BETWEEN :tanggal_awal AND :tanggal_akhir
    WHERE g.status = 'aktif'
";

$params = [
    'tanggal_awal' => $tanggalAwal,
    'tanggal_akhir' => $tanggalAkhir,
];

if ($search !== '') {
    $sql .= " AND (g.nama_lengkap LIKE :search_nama OR g.nip LIKE :search_nip)";
    $params['search_nama'] = '%' . $search . '%';
    $params['search_nip']  = '%' . $search . '%';
}

$sql .= "
    GROUP BY g.id, g.nip, g.nama_lengkap, m.nama_mapel
    ORDER BY g.nama_lengkap ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dataRekap = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalGuru = count($dataRekap);
$totalSemuaHadir = 0;
$totalSemuaIzin = 0;
$totalSemuaAlfa = 0;
$totalSemuaPresensi = 0;

foreach ($dataRekap as $row) {
    $totalSemuaHadir += (int)$row['total_hadir'];
    $totalSemuaIzin += (int)$row['total_izin'];
    $totalSemuaAlfa += (int)$row['total_alfa'];
    $totalSemuaPresensi += (int)$row['total_presensi'];
}

$rataRataKehadiran = $totalSemuaPresensi > 0 ? round(($totalSemuaHadir / $totalSemuaPresensi) * 100, 1) : 0;
$tanggalCetak = date('j') . ' ' . ($namaBulan[(int)date('n')] ?? '') . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Rekap Presensi Guru - <?= e($labelPeriode) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Times, serif;
            color: #000000;
            background: #e5e7eb;
            margin: 0;
            padding: 20px;
            font-size: 13px;
            line-height: 1.4;
        }
        .paper {
            background: #ffffff;
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 40px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }
        .kop-surat {
            display: flex;
            align-items: center;
            border-bottom: 3px double #000000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .kop-logo {
            width: 85px;
            height: auto;
            margin-right: 20px;
        }
        .kop-teks {
            flex: 1;
            text-align: center;
        }
        .kop-teks h3 {
            margin: 0;
            font-size: 15px;
            font-weight: normal;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .kop-teks h2 {
            margin: 3px 0;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }
        .kop-teks p {
            margin: 2px 0;
            font-size: 11px;
            color: #222;
        }
        .laporan-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .laporan-title {
            margin: 0 0 4px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .laporan-subtitle {
            margin: 0;
            font-size: 13px;
            font-weight: normal;
        }
        .rekap-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 12px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th,
        table.data-table td {
            border: 1px solid #000000;
            padding: 6px 8px;
            font-size: 12px;
        }
        table.data-table th {
            background-color: #f3f4f6;
            text-align: center;
            font-weight: bold;
        }
        table.data-table td.center {
            text-align: center;
        }
        table.data-table tfoot td {
            font-weight: bold;
            background-color: #f9fafb;
        }
        .ttd-container {
            display: flex;
            justify-content: space-between;
            margin-top: 35px;
            page-break-inside: avoid;
        }
        .ttd-box {
            width: 250px;
            text-align: center;
            font-size: 12px;
        }
        .ttd-jabatan {
            margin-bottom: 65px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }
        .action-bar {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        }
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            background: #1e40af;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            transition: background 0.15s ease;
        }
        .action-btn:hover { background: #1d4ed8; }
        .action-btn.secondary { background: #4b5563; }
        .action-btn.secondary:hover { background: #374151; }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                font-size: 11pt;
            }
            .paper {
                box-shadow: none;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .action-bar {
                display: none !important;
            }
            @page {
                size: A4 portrait;
                margin: 1.5cm;
            }
        }
    </style>
</head>
<body>

<div class="action-bar">
    <button type="button" class="action-btn" onclick="window.print()">
        <?= appIcon('export', '', 16) ?> Cetak Sekarang (Print / PDF)
    </button>
    <button type="button" class="action-btn secondary" onclick="window.close()">
        <?= appIcon('x', '', 16) ?> Tutup
    </button>
</div>

<div class="paper">
    <div class="kop-surat">
        <img src="../assets/img/logosmks-removebg-preview-removebg-preview.png" alt="Logo <?= e($sekolah['nama_sekolah'] ?? 'Sekolah') ?>" class="kop-logo">
        <div class="kop-teks">
            <h3><?= e($sekolah['nama_yayasan'] ?? 'Yayasan Pendidikan Mulia Buana') ?></h3>
            <h2><?= e($sekolah['nama_sekolah'] ?? 'SMK SWASTA MULIA BUANA') ?></h2>
            <p><?= e($sekolah['akreditasi'] ?? 'Terakreditasi B') ?> &bull; NSS: <?= e($sekolah['nss'] ?? '-') ?> &bull; NPSN: <?= e($sekolah['npsn'] ?? '-') ?></p>
            <p><?= e($sekolah['alamat'] ?? '') ?></p>
            <p>Email: <?= e($sekolah['email'] ?? '-') ?> &bull; Website: <?= e($sekolah['website'] ?? '-') ?></p>
        </div>
    </div>

    <div class="laporan-header">
        <h1 class="laporan-title">Laporan Rekapitulasi Presensi Kehadiran Guru</h1>
        <p class="laporan-subtitle">Periode: <?= e($labelPeriode) ?></p>
    </div>

    <div class="rekap-meta">
        <div><strong>Total Guru:</strong> <?= (int)$totalGuru ?> orang</div>
        <div><strong>Rata-rata Kehadiran:</strong> <?= e($rataRataKehadiran) ?>%</div>
        <div><strong>Dicetak:</strong> <?= e($tanggalCetak) ?></div>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 35px;">No</th>
                <th style="width: 130px;">NIP</th>
                <th>Nama Lengkap</th>
                <th>Mata Pelajaran</th>
                <th style="width: 55px;">Hadir</th>
                <th style="width: 50px;">Izin</th>
                <th style="width: 50px;">Alfa</th>
                <th style="width: 60px;">Total</th>
                <th style="width: 65px;">% Hadir</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dataRekap)): ?>
                <tr>
                    <td colspan="9" class="center">Tidak ada data presensi pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php $no = 1; foreach ($dataRekap as $r): ?>
                    <?php
                        $presensi = (int)$r['total_presensi'];
                        $hadir = (int)$r['total_hadir'];
                        $persen = $presensi > 0 ? round(($hadir / $presensi) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td class="center"><?= $no++ ?></td>
                        <td><?= e($r['nip'] ?: '-') ?></td>
                        <td><strong><?= e($r['nama_lengkap']) ?></strong></td>
                        <td><?= e($r['nama_mapel']) ?></td>
                        <td class="center"><?= (int)$r['total_hadir'] ?></td>
                        <td class="center"><?= (int)$r['total_izin'] ?></td>
                        <td class="center"><?= (int)$r['total_alfa'] ?></td>
                        <td class="center"><?= (int)$r['total_presensi'] ?></td>
                        <td class="center"><?= e($persen) ?>%</td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right; padding-right: 12px;">Total Keseluruhan:</td>
                <td class="center"><?= (int)$totalSemuaHadir ?></td>
                <td class="center"><?= (int)$totalSemuaIzin ?></td>
                <td class="center"><?= (int)$totalSemuaAlfa ?></td>
                <td class="center"><?= (int)$totalSemuaPresensi ?></td>
                <td class="center"><?= e($rataRataKehadiran) ?>%</td>
            </tr>
        </tfoot>
    </table>

    <div class="ttd-container">
        <div class="ttd-box">
            <div>Mengetahui,</div>
            <div class="ttd-jabatan"><?= e($sekolah['jabatan_kepsek'] ?? 'Kepala SMKS Mulia Buana') ?></div>
            <div class="ttd-nama"><?= e($sekolah['nama_kepsek'] ?? 'Ahmad Suherman, S.Pd.') ?></div>
            <div>NIP. <?= e($sekolah['nip_kepsek'] ?? '-') ?></div>
        </div>

        <div class="ttd-box">
            <div><?= e($sekolah['kota_surat'] ?? 'Bogor') ?>, <?= e($tanggalCetak) ?></div>
            <div class="ttd-jabatan"><?= e($sekolah['jabatan_tu'] ?? 'Admin Tata Usaha') ?></div>
            <div class="ttd-nama"><?= e($sekolah['nama_tu'] ?? 'Staf Administrasi TU') ?></div>
            <div>NIP. <?= e($sekolah['nip_tu'] ?? '-') ?></div>
        </div>
    </div>
</div>

</body>
</html>
