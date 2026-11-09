<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireRole('guru');

$userId = currentUserId();



$stmt = $pdo->prepare("
    SELECT
        g.id,
        g.nip,
        g.nama_lengkap,
        g.status,
        m.nama_mapel
    FROM guru g
    LEFT JOIN mapel m
        ON m.id = g.mapel_utama_id
    WHERE g.user_id = :user_id
      AND g.status = 'aktif'
    LIMIT 1
");

$stmt->execute([
    'user_id' => $userId
]);

$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {

    die('Data guru tidak ditemukan atau tidak aktif. Hubungi Admin TU.');
}

$guruId = (int) $guru['id'];




$namaHari = [
    'Sunday'    => 'Minggu',
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu'
];

$hariIni = $namaHari[date('l')] ?? 'Senin';




$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.hari,
        j.jam_mulai,
        j.jam_selesai,
        m.nama_mapel,
        m.kode_mapel,
        k.nama_kelas,
        k.tingkat,
        jur.nama_jurusan
    FROM jadwal j

    INNER JOIN mapel m
        ON m.id = j.mapel_id

    INNER JOIN kelas k
        ON k.id = j.kelas_id

    LEFT JOIN jurusan jur
        ON jur.id = k.jurusan_id

    WHERE j.guru_id = :guru_id

    ORDER BY
        CASE j.hari
            WHEN 'Senin' THEN 1
            WHEN 'Selasa' THEN 2
            WHEN 'Rabu' THEN 3
            WHEN 'Kamis' THEN 4
            WHEN 'Jumat' THEN 5
            WHEN 'Sabtu' THEN 6
            ELSE 7
        END,
        j.jam_mulai ASC
");

$stmt->execute([
    'guru_id' => $guruId
]);

$jadwal = $stmt->fetchAll(PDO::FETCH_ASSOC);




$jadwalPerHari = [];

foreach ($jadwal as $item) {

    $hari = $item['hari'];

    if (!isset($jadwalPerHari[$hari])) {
        $jadwalPerHari[$hari] = [];
    }

    $jadwalPerHari[$hari][] = $item;
}




$hariSekolah = [
    'Senin',
    'Selasa',
    'Rabu',
    'Kamis',
    'Jumat',
    'Sabtu'
];




$totalJadwal = count($jadwal);

$totalHariMengajar = 0;

foreach ($hariSekolah as $hari) {

    if (!empty($jadwalPerHari[$hari])) {
        $totalHariMengajar++;
    }
}




$totalKelasArr = [];

foreach ($jadwal as $item) {

    $kelasId = $item['id'];

    $kelasKey =
        ($item['nama_kelas'] ?? '') . '|' .
        ($item['tingkat'] ?? '');

    if (!isset($totalKelasArr[$kelasKey])) {
        $totalKelasArr[$kelasKey] = true;
    }
}

$totalKelas = count($totalKelasArr);




$stmtPiket = $pdo->prepare("
    SELECT
        id,
        hari,
        keterangan
    FROM jadwal_piket
    WHERE guru_id = :guru_id
      AND hari = :hari
    LIMIT 1
");

$stmtPiket->execute([
    'guru_id' => $guruId,
    'hari' => $hariIni
]);

$piketHariIni = $stmtPiket->fetch(PDO::FETCH_ASSOC);

$isPiket = !empty($piketHariIni);




$activeMenu   = 'jadwal';
$pageTitle    = 'Jadwal Mengajar';
$pageSubtitle = 'Lihat seluruh jadwal mengajar Anda';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Jadwal Mengajar - SMKS Mulia Buana
    </title>

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
                            Jadwal Mengajar
                        </span>

                    </div>


                    <h1 class="page-title">
                        Jadwal Mengajar
                    </h1>


                    <p class="page-description">
                        Lihat seluruh jadwal mengajar Anda selama
                        satu minggu.
                    </p>

                </div>


                

                <div class="stats-grid">


                    

                    <div class="stat-card blue">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('jadwal', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Jadwal
                                </div>

                                <div class="stat-value">
                                    <?= e($totalJadwal) ?>
                                </div>

                                <div class="stat-description">
                                    Periode mengajar
                                </div>

                            </div>

                        </div>

                    </div>


                    

                    <div class="stat-card green">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('clock', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Hari Mengajar
                                </div>

                                <div class="stat-value">
                                    <?= e($totalHariMengajar) ?>
                                </div>

                                <div class="stat-description">
                                    Hari dalam satu minggu
                                </div>

                            </div>

                        </div>

                    </div>


                    

                    <div class="stat-card purple">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('kelas', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Kelas
                                </div>

                                <div class="stat-value">
                                    <?= e($totalKelas) ?>
                                </div>

                                <div class="stat-description">
                                    Kelas yang diajar
                                </div>

                            </div>

                        </div>

                    </div>


                    

                    <div class="stat-card orange">

                        <div class="stat-card-top">

                            <div class="stat-icon">
                                <?= appIcon('jadwal', '', 24) ?>
                            </div>

                            <div>

                                <div class="stat-label">
                                    Hari Ini
                                </div>

                                <div class="stat-value">
                                    <?= e($hariIni) ?>
                                </div>

                                <div class="stat-description">
                                    Jadwal aktif hari ini
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                

                <section class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon('jadwal', '', 18) ?>
                            </span>

                            Jadwal Mengajar Minggu Ini

                        </h2>

                    </div>


                    <div class="dashboard-card-body">


                        <?php if (empty($jadwal)): ?>

                            <div class="empty-state">

                                <div class="empty-state-icon"><?= appIcon("jadwal", "", 48) ?></div>

                                <h3>
                                    Belum Ada Jadwal
                                </h3>

                                <p>
                                    Saat ini belum terdapat jadwal mengajar
                                    yang terdaftar untuk Anda.
                                </p>

                            </div>


                        <?php else: ?>


                            <?php foreach ($hariSekolah as $hari): ?>

                                <?php

                                $jadwalHari =
                                    $jadwalPerHari[$hari] ?? [];

                                $isHariIni =
                                    ($hari === $hariIni);

                                ?>


                                <div
                                    class="schedule-day <?= $isHariIni ? 'today' : '' ?>"
                                >

                                    <div class="schedule-day-header">

                                        <div>

                                            <span class="schedule-day-label">
                                                Hari
                                            </span>

                                            <h3>
                                                <?= e($hari) ?>
                                            </h3>

                                        </div>


                                        <?php if ($isHariIni): ?>

                                            <span class="today-badge">
                                                Hari Ini
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <?php if (empty($jadwalHari)): ?>


                                        <div class="schedule-empty">

                                            Tidak ada jadwal mengajar
                                            pada hari <?= e(strtolower($hari)) ?>.

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
                                                            Waktu
                                                        </th>

                                                        <th>
                                                            Mata Pelajaran
                                                        </th>

                                                        <th>
                                                            Kelas
                                                        </th>

                                                        <th>
                                                            Jurusan
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody>

                                                <?php $nomor = 1; ?>


                                                <?php foreach (
                                                    $jadwalHari
                                                    as $item
                                                ): ?>

                                                    <tr>


                                                        <td>
                                                            <?= $nomor++ ?>
                                                        </td>


                                                        <td class="schedule-time">

                                                            <?= e(
                                                                formatJam(
                                                                    $item[
                                                                        'jam_mulai'
                                                                    ]
                                                                )
                                                            ) ?>

                                                            -

                                                            <?= e(
                                                                formatJam(
                                                                    $item[
                                                                        'jam_selesai'
                                                                    ]
                                                                )
                                                            ) ?>

                                                        </td>


                                                        <td>

                                                            <span class="schedule-subject">

                                                                <?= e(
                                                                    $item[
                                                                        'nama_mapel'
                                                                    ]
                                                                ) ?>

                                                            </span>


                                                            <?php if (
                                                                !empty(
                                                                    $item[
                                                                        'kode_mapel'
                                                                    ]
                                                                )
                                                            ): ?>

                                                                <div class="schedule-code">

                                                                    <?= e(
                                                                        $item[
                                                                            'kode_mapel'
                                                                        ]
                                                                    ) ?>

                                                                </div>

                                                            <?php endif; ?>

                                                        </td>


                                                        <td>

                                                            <span class="schedule-class">

                                                                <?= e(
                                                                    $item[
                                                                        'nama_kelas'
                                                                    ]
                                                                ) ?>

                                                            </span>

                                                            <?php if (
                                                                !empty(
                                                                    $item[
                                                                        'tingkat'
                                                                    ]
                                                                )
                                                            ): ?>

                                                                <div class="schedule-code">

                                                                    Tingkat
                                                                    <?= e(
                                                                        $item[
                                                                            'tingkat'
                                                                        ]
                                                                    ) ?>

                                                                </div>

                                                            <?php endif; ?>

                                                        </td>


                                                        <td>

                                                            <?= e(
                                                                $item[
                                                                    'nama_jurusan'
                                                                ]
                                                                ?? '-'
                                                            ) ?>

                                                        </td>


                                                    </tr>

                                                <?php endforeach; ?>


                                                </tbody>

                                            </table>

                                        </div>


                                    <?php endif; ?>


                                </div>

                            <?php endforeach; ?>


                        <?php endif; ?>


                    </div>

                </section>


                

                <section class="dashboard-card">

                    <div class="dashboard-card-header">

                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon">
                                <?= appIcon('info', '', 18) ?>
                            </span>

                            Informasi Jadwal

                        </h2>

                    </div>


                    <div class="dashboard-card-body">

                        <p class="page-description">

                            Jadwal mengajar yang ditampilkan berasal
                            dari data jadwal yang telah ditetapkan oleh
                            Admin TU.

                        </p>


                        <p class="page-description">

                            Jika terdapat ketidaksesuaian jadwal,
                            silakan hubungi Admin TU untuk melakukan
                            perubahan data.

                        </p>


                        <?php if ($isPiket): ?>

                            <p class="page-description">

                                Anda juga tercatat sebagai Guru Piket
                                pada hari <?= e($hariIni) ?>.

                            </p>

                        <?php endif; ?>

                    </div>

                </section>


            </div>

        </main>

    </div>

</div>

</body>

</html>