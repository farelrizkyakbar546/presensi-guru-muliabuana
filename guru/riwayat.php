<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('guru');



date_default_timezone_set('Asia/Jakarta');



$userId = currentUserId();







$stmt = $pdo->prepare("

    SELECT

        id,

        nama_lengkap

    FROM guru

    WHERE user_id = :user_id

    LIMIT 1

");



$stmt->execute([

    'user_id' => $userId

]);



$guru = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$guru) {

    die('Data guru tidak ditemukan. Hubungi Admin TU.');

}



$guruId = (int) $guru['id'];









$bulan = isset($_GET['bulan'])

    ? trim($_GET['bulan'])

    : date('Y-m');



if (!preg_match('/^\d{4}-\d{2}$/', $bulan)) {

    $bulan = date('Y-m');

}









$tahun = (int) substr($bulan, 0, 4);

$bulanAngka = (int) substr($bulan, 5, 2);



if ($bulanAngka < 1 || $bulanAngka > 12) {

    $bulan = date('Y-m');

}



$tanggalAwal = $bulan . '-01';

$tanggalAkhir = date('Y-m-t', strtotime($tanggalAwal));









$stmt = $pdo->prepare("

    SELECT

        tanggal,

        waktu_masuk,

        status,

        keterangan

    FROM presensi_guru

    WHERE guru_id = :guru_id

      AND tanggal BETWEEN :tanggal_awal AND :tanggal_akhir

    ORDER BY tanggal DESC

");



$stmt->execute([

    'guru_id' => $guruId,

    'tanggal_awal' => $tanggalAwal,

    'tanggal_akhir' => $tanggalAkhir

]);



$presensiAsli = $stmt->fetchAll(PDO::FETCH_ASSOC);









$presensiTanggal = [];



foreach ($presensiAsli as $data) {

    $presensiTanggal[$data['tanggal']] = true;

}









$stmt = $pdo->prepare("

    SELECT

        p.id,

        p.tanggal_mulai,

        p.tanggal_selesai,

        p.jenis_izin,

        p.alasan,

        p.catatan_admin

    FROM perizinan p

    WHERE p.guru_id = :guru_id

      AND p.status = 'Disetujui'

      AND p.tanggal_mulai <= :tanggal_akhir

      AND p.tanggal_selesai >= :tanggal_awal

    ORDER BY p.id DESC

");



$stmt->execute([

    'guru_id' => $guruId,

    'tanggal_awal' => $tanggalAwal,

    'tanggal_akhir' => $tanggalAkhir

]);



$perizinanDisetujui = $stmt->fetchAll(PDO::FETCH_ASSOC);









$namaHari = [

    'Sunday' => 'Minggu',

    'Monday' => 'Senin',

    'Tuesday' => 'Selasa',

    'Wednesday' => 'Rabu',

    'Thursday' => 'Kamis',

    'Friday' => 'Jumat',

    'Saturday' => 'Sabtu'

];









$riwayatIzin = [];



foreach ($perizinanDisetujui as $izin) {



    $mulaiIzin = new DateTime($izin['tanggal_mulai']);

    $selesaiIzin = new DateTime($izin['tanggal_selesai']);



    $awalPeriode = new DateTime($tanggalAwal);

    $akhirPeriode = new DateTime($tanggalAkhir);



    

    if ($mulaiIzin < $awalPeriode) {

        $mulaiIzin = $awalPeriode;

    }



    

    if ($selesaiIzin > $akhirPeriode) {

        $selesaiIzin = $akhirPeriode;

    }



    

    $tanggalBerjalan = clone $mulaiIzin;



    while ($tanggalBerjalan <= $selesaiIzin) {



        $tanggal = $tanggalBerjalan->format('Y-m-d');



        

        if ($tanggalBerjalan->format('N') != 7) {



            

            if (!isset($presensiTanggal[$tanggal])) {



                

                if (!isset($riwayatIzin[$tanggal])) {



                    $keteranganIzin = 'Perizinan disetujui';



                    if (!empty($izin['jenis_izin'])) {

                        $keteranganIzin .= ' - ' . $izin['jenis_izin'];

                    }



                    if (!empty($izin['alasan'])) {

                        $keteranganIzin .= ': ' . trim($izin['alasan']);

                    }



                    $riwayatIzin[$tanggal] = [

                        'tanggal' => $tanggal,

                        'waktu_masuk' => null,

                        'status' => 'Izin',

                        'keterangan' => $keteranganIzin,

                        'sumber' => 'perizinan'

                    ];

                }

            }

        }



        $tanggalBerjalan->modify('+1 day');

    }

}









$riwayat = [];







foreach ($presensiAsli as $data) {



    $data['sumber'] = 'presensi';



    $riwayat[] = $data;

}







foreach ($riwayatIzin as $data) {

    $riwayat[] = $data;

}









usort($riwayat, function ($a, $b) {



    if ($a['tanggal'] === $b['tanggal']) {

        return 0;

    }



    return ($a['tanggal'] > $b['tanggal']) ? -1 : 1;

});









$totalHadir = 0;

$totalIzin = 0;

$totalAlfa = 0;



foreach ($riwayat as $data) {



    if ($data['status'] === 'Hadir') {

        $totalHadir++;

    }



    if ($data['status'] === 'Izin') {

        $totalIzin++;

    }



    if ($data['status'] === 'Alfa') {

        $totalAlfa++;

    }

}



$totalPresensi = count($riwayat);









$hariIni = $namaHari[date('l')];



$stmtPiket = $pdo->prepare("

    SELECT

        id

    FROM jadwal_piket

    WHERE guru_id = :guru_id

      AND hari = :hari

    LIMIT 1

");



$stmtPiket->execute([

    'guru_id' => $guruId,

    'hari' => $hariIni

]);



$isPiket = $stmtPiket->fetch(PDO::FETCH_ASSOC);









$statusBadgeMap = [

    'Hadir' => 'success',

    'Izin' => 'warning',

    'Alfa' => 'danger',

];









$activeMenu = 'riwayat';

$piketHariIni = (bool) $isPiket;



$pageTitle = 'Riwayat Presensi';

$pageSubtitle = 'Rekap kehadiran Anda';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Riwayat Presensi - SMKS Mulia Buana</title>



    <link

        rel="stylesheet"

        href="../assets/css/style.css"

    >



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



                        <a href="dashboard.php">

                            Dashboard

                        </a>



                        <span>/</span>



                        <span>

                            Riwayat Presensi

                        </span>



                    </div>





                    <h1 class="page-title">

                        Riwayat Presensi

                    </h1>





                    <p class="page-description">



                        Guru:



                        <strong>

                            <?= e($guru['nama_lengkap']) ?>

                        </strong>



                    </p>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("calendar", "", 18) ?>

                            </span>



                            Filter Periode



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form

                            method="GET"

                            class="filter-bar"

                        >



                            <div class="filter-bar-item">



                                <label

                                    for="bulan"

                                    class="form-label"

                                >

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





                            <div class="filter-bar-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >

                                    Tampilkan

                                </button>





                                <a

                                    href="../proses/export-riwayat.php?bulan=<?= e($bulan) ?>"

                                    class="btn btn-outline"

                                >

                                    <?= appIcon("export", "", 16) ?> Export Excel

                                </a>



                            </div>



                        </form>



                    </div>



                </div>





                



                <div class="stats-grid">



                    



                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("clock", "", 24) ?>

                            </div>



                            <div>



                                <div class="stat-label">

                                    Total Presensi

                                </div>



                                <div class="stat-value">

                                    <?= (int) $totalPresensi ?>

                                </div>



                                <div class="stat-description">

                                    Entri bulan ini

                                </div>



                            </div>



                        </div>



                    </div>





                    



                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("check", "", 24) ?>

                            </div>



                            <div>



                                <div class="stat-label">

                                    Hadir

                                </div>



                                <div class="stat-value">

                                    <?= (int) $totalHadir ?>

                                </div>



                                <div class="stat-description">

                                    Status hadir

                                </div>



                            </div>



                        </div>



                    </div>





                    



                    <div class="stat-card purple">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("perizinan", "", 24) ?>

                            </div>



                            <div>



                                <div class="stat-label">

                                    Izin

                                </div>



                                <div class="stat-value">

                                    <?= (int) $totalIzin ?>

                                </div>



                                <div class="stat-description">

                                    Status izin

                                </div>



                            </div>



                        </div>



                    </div>





                    



                    <div class="stat-card orange">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                <?= appIcon("alert", "", 24) ?>

                            </div>



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



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("rekap", "", 18) ?>

                            </span>



                            Detail Presensi



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >



                            Periode:



                            <?= e(formatTanggal($tanggalAwal)) ?>



                            &mdash;



                            <?= e(formatTanggal($tanggalAkhir)) ?>



                        </div>



                    </div>





                    <div class="dashboard-card-body">



                        <?php if (empty($riwayat)): ?>



                            <div class="empty-state">



                                <div class="empty-state-icon"><?= appIcon("riwayat", "", 48) ?></div>



                                <h3>

                                    Belum Ada Data

                                </h3>



                                <p>

                                    Belum ada data presensi pada

                                    periode ini.

                                </p>



                            </div>



                        <?php else: ?>



                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>



                                            <th>

                                                No

                                            </th>



                                            <th>

                                                Tanggal

                                            </th>



                                            <th>

                                                Waktu

                                            </th>



                                            <th>

                                                Status

                                            </th>



                                            <th>

                                                Keterangan

                                            </th>



                                        </tr>



                                    </thead>





                                    <tbody>



                                    <?php foreach ($riwayat as $index => $data): ?>



                                        <?php



                                        $badgeClass =

                                            $statusBadgeMap[$data['status']]

                                            ?? 'secondary';



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>





                                            <td class="schedule-subject">



                                                <?= e(

                                                    formatTanggal(

                                                        $data['tanggal']

                                                    )

                                                ) ?>



                                            </td>





                                            <td class="schedule-time">



                                                <?php if (!empty($data['waktu_masuk'])): ?>



                                                    <?= e(

                                                        formatJam(

                                                            $data['waktu_masuk']

                                                        )

                                                    ) ?>



                                                <?php else: ?>



                                                    -



                                                <?php endif; ?>



                                            </td>





                                            <td>



                                                <span

                                                    class="status-badge <?= e($badgeClass) ?>"

                                                >

                                                    <?= e($data['status']) ?>

                                                </span>



                                            </td>





                                            <td>



                                                <?php if (!empty($data['keterangan'])): ?>



                                                    <?= e($data['keterangan']) ?>



                                                <?php else: ?>



                                                    -



                                                <?php endif; ?>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                    </tbody>



                                </table>



                            </div>



                        <?php endif; ?>



                    </div>



                </div>





                



                <a

                    href="dashboard.php"

                    class="back-link"

                >

                    ← Kembali ke Dashboard

                </a>



            </div>



        </main>



    </div>



</div>



</body>



</html>