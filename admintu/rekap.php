<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('adminTu');



date_default_timezone_set('Asia/Jakarta');







$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');



if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {

    $bulan = date('Y-m');

}



$search = trim($_GET['search'] ?? '');



$tanggalAwal  = $bulan . '-01';

$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));







$namaBulan = [

    1  => 'Januari',

    2  => 'Februari',

    3  => 'Maret',

    4  => 'April',

    5  => 'Mei',

    6  => 'Juni',

    7  => 'Juli',

    8  => 'Agustus',

    9  => 'September',

    10 => 'Oktober',

    11 => 'November',

    12 => 'Desember'

];



$bulanAngka = (int) substr($bulan, 5, 2);

$tahunAngka = substr($bulan, 0, 4);



$labelPeriode =

    $namaBulan[$bulanAngka] . ' ' . $tahunAngka;







$sql = "

    SELECT

        g.id,

        g.nip,

        g.nama_lengkap,

        COUNT(pg.id) AS total_presensi,

        SUM(CASE WHEN pg.status = 'Hadir' THEN 1 ELSE 0 END) AS total_hadir,

        SUM(CASE WHEN pg.status = 'Izin'  THEN 1 ELSE 0 END) AS total_izin,

        SUM(CASE WHEN pg.status = 'Alfa'  THEN 1 ELSE 0 END) AS total_alfa

    FROM guru g

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

    GROUP BY g.id, g.nip, g.nama_lengkap

    ORDER BY g.nama_lengkap ASC

";



$stmt = $pdo->prepare($sql);

$stmt->execute($params);



$rekapList = $stmt->fetchAll(PDO::FETCH_ASSOC);









$presensiAktualByGuru = [];



$stmt = $pdo->prepare("

    SELECT guru_id, tanggal, status

    FROM presensi_guru

    WHERE tanggal BETWEEN ? AND ?

");



$stmt->execute([

    $tanggalAwal,

    $tanggalAkhir

]);



foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $presensi) {



    $guruKey = (int) $presensi['guru_id'];



    if (!isset($presensiAktualByGuru[$guruKey])) {

        $presensiAktualByGuru[$guruKey] = [];

    }



    $presensiAktualByGuru[$guruKey][$presensi['tanggal']] =

        $presensi['status'];

}









$stmt = $pdo->prepare("

    SELECT

        guru_id,

        tanggal_mulai,

        tanggal_selesai

    FROM perizinan

    WHERE status = 'Disetujui'

      AND tanggal_mulai <= ?

      AND tanggal_selesai >= ?

");



$stmt->execute([

    $tanggalAkhir,

    $tanggalAwal

]);



$izinDisetujui = $stmt->fetchAll(PDO::FETCH_ASSOC);









$izinHariByGuru = [];



foreach ($izinDisetujui as $izin) {



    $mulai = new DateTime($izin['tanggal_mulai']);

    $selesai = new DateTime($izin['tanggal_selesai']);



    $batasMulai = new DateTime($tanggalAwal);

    $batasSelesai = new DateTime($tanggalAkhir);



    if ($mulai < $batasMulai) {

        $mulai = clone $batasMulai;

    }



    if ($selesai > $batasSelesai) {

        $selesai = clone $batasSelesai;

    }



    $guruKey = (int) $izin['guru_id'];



    if (!isset($izinHariByGuru[$guruKey])) {

        $izinHariByGuru[$guruKey] = [];

    }



    for (

        $tanggal = clone $mulai;

        $tanggal <= $selesai;

        $tanggal->modify('+1 day')

    ) {



        $tanggalString = $tanggal->format('Y-m-d');



        if (

            isset(

                $presensiAktualByGuru[$guruKey][$tanggalString]

            )

        ) {

            continue;

        }



        $izinHariByGuru[$guruKey][$tanggalString] = true;

    }

}









foreach ($rekapList as &$row) {



    $guruKey = (int) $row['id'];



    $jumlahIzinTambahan =

        isset($izinHariByGuru[$guruKey])

            ? count($izinHariByGuru[$guruKey])

            : 0;



    $row['total_izin'] =

        (int) $row['total_izin']

        + $jumlahIzinTambahan;



    $row['total_presensi'] =

        (int) $row['total_presensi']

        + $jumlahIzinTambahan;

}



unset($row);









$grandTotal = [

    'total_presensi' => 0,

    'total_hadir'    => 0,

    'total_izin'     => 0,

    'total_alfa'     => 0,

];



$totalGuruAktif = count($rekapList);



foreach ($rekapList as $row) {

    $grandTotal['total_presensi'] += (int) $row['total_presensi'];

    $grandTotal['total_hadir']    += (int) $row['total_hadir'];

    $grandTotal['total_izin']     += (int) $row['total_izin'];

    $grandTotal['total_alfa']     += (int) $row['total_alfa'];

}











$activeMenu   = 'rekap';

$pageTitle    = 'Rekap Presensi';

$pageSubtitle = 'Rekapitulasi kehadiran guru';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Rekap Presensi - Admin TU</title>



    <link rel="stylesheet" href="../assets/css/style.css">



</head>



<body>



<div class="app-shell">



    <?php include __DIR__ . '/partials/sidebar.php'; ?>



    <div class="app-main">



        <?php include __DIR__ . '/partials/notif-count.php'; ?>



        <?php include __DIR__ . '/partials/topbar.php'; ?>



        <main class="dashboard-content">



            <div class="dashboard-container">



                



                <div class="page-header">



                    <div class="breadcrumb">



                        <a href="dashboard.php">Dashboard</a>



                        <span>/</span>



                        <span>Rekap Presensi</span>



                    </div>



                    <h1 class="page-title">



                        Rekap Presensi Guru



                    </h1>



                    <p class="page-description">



                        Rekapitulasi kehadiran guru periode



                        <strong><?= e($labelPeriode) ?></strong>.



                    </p>



                </div>



                



                <section class="stats-grid">



                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("guru", "", 24) ?></div>



                            <div>



                                <div class="stat-label">



                                    Guru Aktif



                                </div>



                                <div class="stat-value">



                                    <?= (int) $totalGuruAktif ?>



                                </div>



                                <div class="stat-description">



                                    Periode ini



                                </div>



                            </div>



                        </div>



                    </div>



                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("check", "", 24) ?></div>



                            <div>



                                <div class="stat-label">



                                    Total Hadir



                                </div>



                                <div class="stat-value">



                                    <?= (int) $grandTotal['total_hadir'] ?>



                                </div>



                                <div class="stat-description">



                                    Entri kehadiran



                                </div>



                            </div>



                        </div>



                    </div>



                    <div class="stat-card purple">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("perizinan", "", 24) ?></div>



                            <div>



                                <div class="stat-label">



                                    Total Izin



                                </div>



                                <div class="stat-value">



                                    <?= (int) $grandTotal['total_izin'] ?>



                                </div>



                                <div class="stat-description">



                                    Total tidak hadir resmi



                                </div>



                            </div>



                        </div>



                    </div>



                    <div class="stat-card orange">



                        <div class="stat-card-top">



                            <div class="stat-icon">!</div>



                            <div>



                                <div class="stat-label">



                                    Total Alfa



                                </div>



                                <div class="stat-value">



                                    <?= (int) $grandTotal['total_alfa'] ?>



                                </div>



                                <div class="stat-description">



                                    Tanpa keterangan



                                </div>



                            </div>



                        </div>



                    </div>



                </section>



                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">



                                <?= appIcon("search", "", 16) ?>



                            </span>



                            Filter Data



                        </h2>



                    </div>



                    <div class="dashboard-card-body">



                        <form method="GET" class="filter-bar">



                            <div class="filter-bar-item">



                                <label for="bulan" class="form-label">



                                    Pilih Bulan



                                </label>



                                <input



                                    type="month"



                                    id="bulan"



                                    name="bulan"



                                    value="<?= e($bulan) ?>"



                                    required



                                >



                            </div>



                            <div class="filter-bar-item">



                                <label for="search" class="form-label">



                                    Cari Guru / NIP



                                </label>



                                <input



                                    type="text"



                                    id="search"



                                    name="search"



                                    value="<?= e($search) ?>"



                                    placeholder="Cari nama atau NIP..."



                                >



                            </div>



                            <div class="filter-bar-actions">



                                <button type="submit" class="btn btn-primary">



                                    Tampilkan



                                </button>



                                <a href="rekap.php" class="btn btn-outline">



                                    Reset



                                </a>



                                <a



                                    href="../proses/export-rekap.php?bulan=<?= e($bulan) ?>&search=<?= e($search) ?>"



                                    class="btn btn-success"



                                >



                                    <?= appIcon("export", "", 16) ?> Export Excel



                                </a>



                                <a



                                    href="cetak-rekap.php?bulan=<?= e($bulan) ?>&search=<?= e($search) ?>"



                                    target="_blank"



                                    class="btn btn-primary"



                                >



                                    <?= appIcon("file-text", "", 16) ?> Cetak Laporan (PDF / Print)



                                </a>



                            </div>



                        </form>



                    </div>



                </div>



                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon"><?= appIcon("rekap", "", 18) ?></span>



                            Rekapitulasi Per Guru



                        </h2>



                        <div class="dashboard-card-link" style="cursor: default;">



                            <?= (int) $totalGuruAktif ?> guru &mdash;



                            <?= (int) $grandTotal['total_presensi'] ?> entri



                        </div>



                    </div>



                    <div class="dashboard-card-body">



                        <?php if (empty($rekapList)): ?>



                            <div class="empty-state">



                                <div class="empty-state-icon">



                                    <?= appIcon("rekap", "", 18) ?>



                                </div>



                                <h3>



                                    Belum Ada Data



                                </h3>



                                <p>



                                    Tidak ada data presensi pada periode ini.



                                </p>



                            </div>



                        <?php else: ?>



                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>



                                            <th>No</th>



                                            <th>Nama Guru</th>



                                            <th>Hadir</th>



                                            <th>Izin</th>



                                            <th>Alfa</th>



                                            <th>% Kehadiran</th>



                                            <th>Aksi</th>



                                        </tr>



                                    </thead>



                                    <tbody>



                                    <?php foreach ($rekapList as $i => $row): ?>



                                        <?php



                                        $totalPresensi = (int) $row['total_presensi'];



                                        $totalHadir = (int) $row['total_hadir'];



                                        $persen = ($totalPresensi > 0)



                                            ? round(($totalHadir / $totalPresensi) * 100, 1)



                                            : 0;



                                        if ($persen >= 90) {



                                            $persenClass = 'success';



                                        } elseif ($persen >= 75) {



                                            $persenClass = 'info';



                                        } elseif ($persen >= 50) {



                                            $persenClass = 'warning';



                                        } else {



                                            $persenClass = 'danger';



                                        }



                                        ?>



                                        <tr>



                                            <td>



                                                <?= $i + 1 ?>



                                            </td>



                                            <td>



                                                <span class="schedule-subject">



                                                    <?= e($row['nama_lengkap']) ?>



                                                </span>



                                                <?php if (!empty($row['nip'])): ?>



                                                    <div class="schedule-code">



                                                        NIP: <?= e($row['nip']) ?>



                                                    </div>



                                                <?php endif; ?>



                                            </td>



                                            <td>



                                                <span class="status-badge success">



                                                    <?= $totalHadir ?>



                                                </span>



                                            </td>



                                            <td>



                                                <span class="status-badge warning">



                                                    <?= (int) $row['total_izin'] ?>



                                                </span>



                                            </td>



                                            <td>



                                                <span class="status-badge danger">



                                                    <?= (int) $row['total_alfa'] ?>



                                                </span>



                                            </td>



                                            <td>



                                                <span class="status-badge <?= e($persenClass) ?>">



                                                    <?= e($persen) ?>%



                                                </span>



                                            </td>



                                            <td>



                                                <a



                                                    href="rekap-detail.php?guru_id=<?= (int) $row['id'] ?>&bulan=<?= e($bulan) ?>"



                                                    class="btn btn-sm btn-outline"



                                                >



                                                    Detail



                                                </a>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                    </tbody>



                                </table>



                            </div>



                        <?php endif; ?>



                    </div>



                </div>



                



                <a href="dashboard.php" class="back-link">



                    ← Kembali ke Dashboard



                </a>



            </div>



        </main>



    </div>



</div>



</body>



</html>