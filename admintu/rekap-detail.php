<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('adminTu');



date_default_timezone_set('Asia/Jakarta');







$guruId = (int) ($_GET['guru_id'] ?? 0);



$bulan = isset($_GET['bulan']) ? trim($_GET['bulan']) : date('Y-m');



if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {

    $bulan = date('Y-m');

}



$tanggalAwal  = $bulan . '-01';

$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));







$stmt = $pdo->prepare("

    SELECT id, nip, nama_lengkap

    FROM guru

    WHERE id = ?

    LIMIT 1

");



$stmt->execute([$guruId]);



$guru = $stmt->fetch();



if (!$guru) {

    flash('error', 'Data guru tidak ditemukan.');

    redirect('rekap.php');

}







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







$stmt = $pdo->prepare("

    SELECT

        tanggal,

        waktu_masuk,

        waktu_pulang,

        status,

        keterangan

    FROM presensi_guru

    WHERE guru_id = ?

      AND tanggal BETWEEN ? AND ?

    ORDER BY tanggal ASC

");



$stmt->execute([

    $guruId,

    $tanggalAwal,

    $tanggalAkhir

]);



$presensiList = $stmt->fetchAll(PDO::FETCH_ASSOC);







$stmt = $pdo->prepare("

    SELECT

        tanggal_mulai,

        tanggal_selesai,

        jenis_izin,

        alasan

    FROM perizinan

    WHERE guru_id = ?

      AND status = 'Disetujui'

      AND tanggal_mulai <= ?

      AND tanggal_selesai >= ?

    ORDER BY tanggal_mulai ASC

");



$stmt->execute([

    $guruId,

    $tanggalAkhir,

    $tanggalAwal

]);



$izinList = $stmt->fetchAll(PDO::FETCH_ASSOC);



$presensiByTanggal = [];

foreach ($presensiList as $row) {

    $presensiByTanggal[$row['tanggal']] = true;

}



foreach ($izinList as $izin) {



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



    for ($tanggal = clone $mulai; $tanggal <= $selesai; $tanggal->modify('+1 day')) {

        



        if ((int) $tanggal->format('N') === 7) {

            continue;

        }



        $tanggalString = $tanggal->format('Y-m-d');



        if (isset($presensiByTanggal[$tanggalString])) {

            continue;

        }



        $presensiList[] = [

            'tanggal' => $tanggalString,

            'waktu_masuk' => null,

            'waktu_pulang' => null,

            'status' => 'Izin',

            'keterangan' => 'Perizinan disetujui: ' . $izin['jenis_izin'] . (!empty($izin['alasan']) ? ' — ' . $izin['alasan'] : '')

        ];



        $presensiByTanggal[$tanggalString] = true;

    }

}



usort($presensiList, static function ($a, $b) {

    return strcmp($a['tanggal'], $b['tanggal']);

});







$totalHadir = 0;

$totalIzin  = 0;

$totalAlfa  = 0;



foreach ($presensiList as $row) {



    if ($row['status'] === 'Hadir') {

        $totalHadir++;

    }



    if ($row['status'] === 'Izin') {

        $totalIzin++;

    }



    if ($row['status'] === 'Alfa') {

        $totalAlfa++;

    }

}



$totalPresensi = count($presensiList);



$persen = ($totalPresensi > 0)

    ? round(($totalHadir / $totalPresensi) * 100, 1)

    : 0;







$badgeMap = [

    'Hadir' => 'success',

    'Izin'  => 'warning',

    'Alfa'  => 'danger',

];







$activeMenu   = 'rekap';

$pageTitle    = 'Detail Rekap';

$pageSubtitle = $guru['nama_lengkap'];



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Detail Rekap - <?= e($guru['nama_lengkap']) ?></title>



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



                        <a href="rekap.php">Rekap Presensi</a>



                        <span>/</span>



                        <span>Detail</span>



                    </div>



                    <h1 class="page-title">



                        <?= e($guru['nama_lengkap']) ?>



                    </h1>



                    <p class="page-description">



                        Detail presensi periode



                        <strong><?= e($labelPeriode) ?></strong>



                        <?php if (!empty($guru['nip'])): ?>



                            &mdash; NIP: <?= e($guru['nip']) ?>



                        <?php endif; ?>



                    </p>



                </div>



                



                <section class="stats-grid">



                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("clock", "", 24) ?></div>



                            <div>



                                <div class="stat-label">



                                    Total Entri



                                </div>



                                <div class="stat-value">



                                    <?= (int) $totalPresensi ?>



                                </div>



                                <div class="stat-description">



                                    Hari tercatat



                                </div>



                            </div>



                        </div>



                    </div>



                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("check", "", 24) ?></div>



                            <div>



                                <div class="stat-label">



                                    Hadir



                                </div>



                                <div class="stat-value">



                                    <?= (int) $totalHadir ?>



                                </div>



                                <div class="stat-description">



                                    <?= e($persen) ?>% kehadiran



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



                                    <?= (int) $totalIzin ?>



                                </div>



                                <div class="stat-description">



                                    Tidak hadir resmi



                                </div>



                            </div>



                        </div>



                    </div>



                    <div class="stat-card orange">



                        <div class="stat-card-top">



                            <div class="stat-icon">!</div>



                            <div>



                                <div class="stat-label">



                                    Alfa



                                </div>



                                <div class="stat-value">



                                    <?= (int) $totalAlfa ?>



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



                            <span class="dashboard-card-title-icon"><?= appIcon("calendar", "", 18) ?></span>



                            Detail Presensi Harian



                        </h2>



                        <a



                            href="rekap.php?bulan=<?= e($bulan) ?>"



                            class="dashboard-card-link"



                        >



                            ← Kembali ke Rekap



                        </a>



                    </div>



                    <div class="dashboard-card-body">



                        <?php if (empty($presensiList)): ?>



                            <div class="empty-state">



                                <div class="empty-state-icon">



                                    <?= appIcon("rekap", "", 18) ?>



                                </div>



                                <h3>



                                    Belum Ada Data



                                </h3>



                                <p>



                                    Belum ada data presensi guru ini



                                    pada periode tersebut.



                                </p>



                            </div>



                        <?php else: ?>



                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>



                                            <th>No</th>



                                            <th>Tanggal</th>



                                            <th>Masuk</th>



                                            <th>Pulang</th>



                                            <th>Status</th>



                                            <th>Keterangan</th>



                                        </tr>



                                    </thead>



                                    <tbody>



                                    <?php foreach ($presensiList as $i => $row): ?>



                                        <?php



                                        $badgeClass = $badgeMap[$row['status']] ?? 'secondary';



                                        ?>



                                        <tr>



                                            <td>



                                                <?= $i + 1 ?>



                                            </td>



                                            <td class="schedule-subject">



                                                <?= e(formatTanggal($row['tanggal'])) ?>



                                            </td>



                                            <td class="schedule-time">



                                                <?= !empty($row['waktu_masuk'])



                                                    ? e(formatJam($row['waktu_masuk']))



                                                    : '-' ?>



                                            </td>



                                            <td class="schedule-time">



                                                <?= !empty($row['waktu_pulang'])



                                                    ? e(formatJam($row['waktu_pulang']))



                                                    : '-' ?>



                                            </td>



                                            <td>



                                                <span class="status-badge <?= e($badgeClass) ?>">



                                                    <?= e($row['status']) ?>



                                                </span>



                                            </td>



                                            <td>



                                                <?= !empty($row['keterangan'])



                                                    ? e($row['keterangan'])



                                                    : '-' ?>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                    </tbody>



                                </table>



                            </div>



                        <?php endif; ?>



                    </div>



                </div>



                



                <a href="rekap.php?bulan=<?= e($bulan) ?>" class="back-link">



                    ← Kembali ke Rekap



                </a>



            </div>



        </main>



    </div>



</div>



</body>



</html>