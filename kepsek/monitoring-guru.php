<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('kepsek');









$tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');

$tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

$guruId = (int) ($_GET['guru_id'] ?? 0);

$status = $_GET['status'] ?? '';





$statusValid = [

    'Hadir',

    'Izin',

    'Alfa'

];









$stmt = $pdo->query("

    SELECT

        id,

        nama_lengkap,

        nip

    FROM guru

    WHERE status = 'aktif'

    ORDER BY nama_lengkap ASC

");



$guruList = $stmt->fetchAll();









if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalMulai)) {

    $tanggalMulai = date('Y-m-01');

}



if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalSelesai)) {

    $tanggalSelesai = date('Y-m-d');

}



if ($tanggalMulai > $tanggalSelesai) {

    $temp = $tanggalMulai;

    $tanggalMulai = $tanggalSelesai;

    $tanggalSelesai = $temp;

}



if (!in_array($status, $statusValid, true)) {

    $status = '';

}









$params = [

    $tanggalMulai,

    $tanggalSelesai

];



$sqlActual = "

    SELECT

        pg.id,

        pg.tanggal,

        pg.waktu_masuk,

        pg.waktu_pulang,

        pg.status,

        pg.keterangan,

        g.id AS guru_id,

        g.nama_lengkap,

        g.nip,

        m.nama_mapel

    FROM presensi_guru pg

    INNER JOIN guru g ON g.id = pg.guru_id

    LEFT JOIN mapel m ON m.id = g.mapel_utama_id

    WHERE pg.tanggal BETWEEN ? AND ?

";



if ($guruId > 0) {

    $sqlActual .= " AND pg.guru_id = ?";

    $params[] = $guruId;

}



if ($status !== '') {

    $sqlActual .= " AND pg.status = ?";

    $params[] = $status;

}



$stmt = $pdo->prepare($sqlActual);

$stmt->execute($params);

$actualList = $stmt->fetchAll(PDO::FETCH_ASSOC);







$izinParams = [

    $tanggalSelesai,

    $tanggalMulai

];



$sqlIzin = "

    SELECT

        p.id AS perizinan_id,

        p.guru_id,

        p.tanggal_mulai,

        p.tanggal_selesai,

        p.jenis_izin,

        p.alasan,

        g.nama_lengkap,

        g.nip,

        m.nama_mapel

    FROM perizinan p

    INNER JOIN guru g ON g.id = p.guru_id

    LEFT JOIN mapel m ON m.id = g.mapel_utama_id

    WHERE p.status = 'Disetujui'

      AND p.tanggal_mulai <= ?

      AND p.tanggal_selesai >= ?

      AND g.status = 'aktif'

";



if ($guruId > 0) {

    $sqlIzin .= " AND p.guru_id = ?";

    $izinParams[] = $guruId;

}



$sqlIzin .= "

    ORDER BY p.tanggal_mulai ASC, g.nama_lengkap ASC

";



$stmt = $pdo->prepare($sqlIzin);

$stmt->execute($izinParams);

$izinList = $stmt->fetchAll(PDO::FETCH_ASSOC);







$actualByKey = [];



foreach ($actualList as $row) {

    $key = (int) $row['guru_id'] . '|' . $row['tanggal'];

    $actualByKey[$key] = $row;

}



$presensiMap = $actualByKey;







foreach ($izinList as $izin) {



    $mulai = new DateTime($izin['tanggal_mulai']);

    $selesai = new DateTime($izin['tanggal_selesai']);

    $batasMulai = new DateTime($tanggalMulai);

    $batasSelesai = new DateTime($tanggalSelesai);



    if ($mulai < $batasMulai) {

        $mulai = clone $batasMulai;

    }



    if ($selesai > $batasSelesai) {

        $selesai = clone $batasSelesai;

    }



    for (

        $tanggal = clone $mulai;

        $tanggal <= $selesai;

        $tanggal->modify('+1 day')

    ) {



        $tanggalString = $tanggal->format('Y-m-d');

        $key = (int) $izin['guru_id'] . '|' . $tanggalString;



        if (isset($presensiMap[$key])) {

            continue;

        }



        if ($status !== '' && $status !== 'Izin') {

            continue;

        }



        $keterangan = 'Perizinan disetujui: ' . $izin['jenis_izin'];



        if (!empty($izin['alasan'])) {

            $keterangan .= ' — ' . $izin['alasan'];

        }



        $presensiMap[$key] = [

            'id' => null,

            'tanggal' => $tanggalString,

            'waktu_masuk' => null,

            'waktu_pulang' => null,

            'status' => 'Izin',

            'keterangan' => $keterangan,

            'guru_id' => (int) $izin['guru_id'],

            'nama_lengkap' => $izin['nama_lengkap'],

            'nip' => $izin['nip'],

            'nama_mapel' => $izin['nama_mapel']

        ];

    }

}



$presensiList = array_values($presensiMap);



usort(

    $presensiList,

    function ($a, $b) {



        if ($a['tanggal'] !== $b['tanggal']) {

            return strcmp($b['tanggal'], $a['tanggal']);

        }



        $waktuA = $a['waktu_masuk'] ?? '';

        $waktuB = $b['waktu_masuk'] ?? '';



        if ($waktuA !== $waktuB) {

            return strcmp($waktuB, $waktuA);

        }



        return strcmp(

            $a['nama_lengkap'],

            $b['nama_lengkap']

        );

    }

);









$totalData = count($presensiList);



$totalHadir = 0;

$totalIzin = 0;

$totalAlfa = 0;



foreach ($presensiList as $data) {



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









$persentaseHadir = $totalData > 0

    ? round(($totalHadir / $totalData) * 100, 1)

    : 0;









$badgeMap = [

    'Hadir' => 'success',

    'Izin'  => 'warning',

    'Alfa'  => 'danger',

];









$activeMenu   = 'monitoring-guru';

$pageTitle    = 'Monitoring Guru';

$pageSubtitle = 'Pantau kehadiran guru';



?>



<!DOCTYPE html>



<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Monitoring Presensi Guru - Kepsek</title>



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



                        <a href="dashboard.php">

                            Dashboard

                        </a>



                        <span>/</span>



                        <span>

                            Monitoring Guru

                        </span>



                    </div>





                    <h1 class="page-title">

                        Monitoring Presensi Guru

                    </h1>





                    <p class="page-description">

                        Pantau data kehadiran guru berdasarkan periode dan status.

                    </p>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("search", "", 18) ?>

                            </span>



                            Filter Presensi



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form method="GET">



                            <div class="form-grid">





                                <div class="form-group">



                                    <label

                                        for="tanggal_mulai"

                                        class="form-label"

                                    >

                                        Tanggal Mulai

                                    </label>





                                    <input

                                        type="date"

                                        id="tanggal_mulai"

                                        name="tanggal_mulai"

                                        value="<?= e($tanggalMulai) ?>"

                                    >



                                </div>





                                <div class="form-group">



                                    <label

                                        for="tanggal_selesai"

                                        class="form-label"

                                    >

                                        Tanggal Selesai

                                    </label>





                                    <input

                                        type="date"

                                        id="tanggal_selesai"

                                        name="tanggal_selesai"

                                        value="<?= e($tanggalSelesai) ?>"

                                    >



                                </div>





                                <div class="form-group">



                                    <label

                                        for="guru_id"

                                        class="form-label"

                                    >

                                        Guru

                                    </label>





                                    <select

                                        id="guru_id"

                                        name="guru_id"

                                    >



                                        <option value="">

                                            Semua Guru

                                        </option>





                                        <?php foreach ($guruList as $guru): ?>



                                            <option

                                                value="<?= (int) $guru['id'] ?>"

                                                <?= $guruId === (int) $guru['id'] ? 'selected' : '' ?>

                                            >



                                                <?= e($guru['nama_lengkap']) ?>



                                                <?php if (!empty($guru['nip'])): ?>



                                                    - <?= e($guru['nip']) ?>



                                                <?php endif; ?>



                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                <div class="form-group">



                                    <label

                                        for="status"

                                        class="form-label"

                                    >

                                        Status

                                    </label>





                                    <select

                                        id="status"

                                        name="status"

                                    >



                                        <option value="">

                                            Semua Status

                                        </option>





                                        <?php foreach ($statusValid as $statusOption): ?>



                                            <option

                                                value="<?= e($statusOption) ?>"

                                                <?= $status === $statusOption ? 'selected' : '' ?>

                                            >

                                                <?= e($statusOption) ?>

                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>



                            </div>





                            <div class="form-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >

                                    Tampilkan Data

                                </button>





                                <a

                                    href="monitoring-guru.php"

                                    class="btn btn-outline"

                                >

                                    Reset

                                </a>



                            </div>



                        </form>



                    </div>



                </div>





                



                <section class="stats-grid">





                    <div class="stat-card blue">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("clock", "", 24) ?></div>





                            <div>



                                <div class="stat-label">

                                    Total Presensi

                                </div>



                                <div class="stat-value">

                                    <?= $totalData ?>

                                </div>



                                <div class="stat-description">

                                    Data sesuai filter

                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="stat-card green">



                        <div class="stat-card-top">



                            <div class="stat-icon">

                                ✓

                            </div>





                            <div>



                                <div class="stat-label">

                                    Hadir

                                </div>



                                <div class="stat-value">

                                    <?= $totalHadir ?>

                                </div>



                                <div class="stat-description">

                                    <?= $persentaseHadir ?>% dari data

                                </div>



                            </div>



                        </div>



                    </div>





                    <div class="stat-card purple">



                        <div class="stat-card-top">



                            <div class="stat-icon"><?= appIcon("perizinan", "", 24) ?></div>





                            <div>



                                <div class="stat-label">

                                    Izin

                                </div>



                                <div class="stat-value">

                                    <?= $totalIzin ?>

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

                                !

                            </div>





                            <div>



                                <div class="stat-label">

                                    Alfa

                                </div>



                                <div class="stat-value">

                                    <?= $totalAlfa ?>

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

                                <?= appIcon("rekap", "", 18) ?>

                            </span>



                            Data Presensi Guru



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >



                            Periode:



                            <?= e(formatTanggal($tanggalMulai)) ?>



                            &mdash;



                            <?= e(formatTanggal($tanggalSelesai)) ?>



                        </div>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>



                                        <th>Guru</th>



                                        <th>Mata Pelajaran</th>



                                        <th>Tanggal</th>



                                        <th>Waktu Masuk</th>



                                        <th>Status</th>



                                        <th>Keterangan</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$presensiList): ?>



                                    <tr>



                                        <td

                                            colspan="7"

                                            style="text-align:center; padding: 30px; color: var(--text-500);"

                                        >



                                            Tidak ada data presensi guru pada filter yang dipilih.



                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($presensiList as $index => $data): ?>



                                        <?php



                                        $badgeClass =

                                            $badgeMap[$data['status']]

                                            ?? 'secondary';



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>





                                            <td>



                                                <span class="schedule-subject">

                                                    <?= e($data['nama_lengkap']) ?>

                                                </span>





                                                <div class="schedule-code">



                                                    NIP:



                                                    <?= e($data['nip'] ?: '-') ?>



                                                </div>



                                            </td>





                                            <td>



                                                <?= e($data['nama_mapel'] ?: '-') ?>



                                            </td>





                                            <td>



                                                <?= e(formatTanggal($data['tanggal'])) ?>



                                            </td>





                                            <td class="schedule-time">



                                                <?= e(formatJam($data['waktu_masuk'])) ?>



                                            </td>





                                            <td>



                                                <span

                                                    class="status-badge <?= e($badgeClass) ?>"

                                                >



                                                    <?= e($data['status']) ?>



                                                </span>



                                            </td>





                                            <td>



                                                <?= e($data['keterangan'] ?: '-') ?>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                <?php endif; ?>



                                </tbody>



                            </table>



                        </div>



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