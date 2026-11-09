<?php



date_default_timezone_set('Asia/Jakarta');



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('guru');



$userId = currentUserId();



$hariIndonesia = [

    'Monday'    => 'Senin',

    'Tuesday'   => 'Selasa',

    'Wednesday' => 'Rabu',

    'Thursday'  => 'Kamis',

    'Friday'    => 'Jumat',

    'Saturday'  => 'Sabtu',

    'Sunday'    => 'Minggu'

];



$tanggalHariIni = date('Y-m-d');

$hariIni = $hariIndonesia[date('l')] ?? '';

$waktuSekarang = date('H:i:s');

$serverNowEpoch = time();









$stmt = $pdo->prepare("

    SELECT

        g.id,

        g.nama_lengkap

    FROM guru g

    INNER JOIN users u

        ON u.id = g.user_id

    WHERE g.user_id = ?

      AND g.status = 'aktif'

      AND u.status = 'aktif'

    LIMIT 1

");



$stmt->execute([$userId]);



$guru = $stmt->fetch();



if (!$guru) {

    die('Data guru tidak ditemukan atau akun guru tidak aktif.');

}



$guruId = (int) $guru['id'];









$stmt = $pdo->prepare("

    SELECT

        j.id,

        j.guru_id,

        j.jam_mulai,

        j.jam_selesai,

        j.kelas_id,

        j.mapel_id,



        m.nama_mapel,

        m.kode_mapel,



        k.nama_kelas,

        k.tingkat,



        jur.nama_jurusan,



        'Normal' AS jenis_jadwal,



        NULL AS pengampu_id,

        NULL AS guru_asal_id,

        NULL AS nama_guru_asal



    FROM jadwal j



    INNER JOIN guru g

        ON g.id = j.guru_id



    INNER JOIN mapel m

        ON m.id = j.mapel_id



    INNER JOIN kelas k

        ON k.id = j.kelas_id



    INNER JOIN jurusan jur

        ON jur.id = k.jurusan_id



    WHERE j.guru_id = ?

      AND g.status = 'aktif'

      AND j.hari = ?



    ORDER BY j.jam_mulai ASC

");



$stmt->execute([

    $guruId,

    $hariIni

]);



$jadwalNormal = $stmt->fetchAll();









$stmt = $pdo->prepare("

    SELECT

        j.id,

        j.guru_id,

        j.jam_mulai,

        j.jam_selesai,

        j.kelas_id,

        j.mapel_id,



        m.nama_mapel,

        m.kode_mapel,



        k.nama_kelas,

        k.tingkat,



        jur.nama_jurusan,



        'Pengganti' AS jenis_jadwal,



        p.id AS pengampu_id,

        p.guru_asal_id,

        ga.nama_lengkap AS nama_guru_asal



    FROM pengampu p



    INNER JOIN jadwal j

        ON j.id = p.jadwal_id



    INNER JOIN guru gp

        ON gp.id = p.guru_pengganti_id



    INNER JOIN guru ga

        ON ga.id = p.guru_asal_id



    INNER JOIN mapel m

        ON m.id = j.mapel_id



    INNER JOIN kelas k

        ON k.id = j.kelas_id



    INNER JOIN jurusan jur

        ON jur.id = k.jurusan_id



    WHERE p.guru_pengganti_id = ?

      AND gp.status = 'aktif'

      AND p.tanggal = ?

      AND p.status = 'Aktif'

      AND j.hari = ?



    ORDER BY j.jam_mulai ASC

");



$stmt->execute([

    $guruId,

    $tanggalHariIni,

    $hariIni

]);



$jadwalPengganti = $stmt->fetchAll();









$jadwalList = array_merge(

    $jadwalNormal,

    $jadwalPengganti

);



usort(

    $jadwalList,

    function ($a, $b) {

        return strcmp($a['jam_mulai'], $b['jam_mulai']);

    }

);









$jadwalIds = [];



foreach ($jadwalList as $jadwal) {

    $jadwalIds[] = (int) $jadwal['id'];

}



$jadwalIds = array_values(array_unique($jadwalIds));



$presensiSesiMap = [];

$izinJadwalMap = [];









if ($jadwalIds) {



    $placeholders = implode(

        ',',

        array_fill(0, count($jadwalIds), '?')

    );



    $params = array_merge(

        [$tanggalHariIni],

        $jadwalIds

    );



    $stmt = $pdo->prepare("

        SELECT

            jadwal_id,

            COUNT(*) AS jumlah

        FROM presensi_siswa

        WHERE tanggal = ?

          AND jadwal_id IN ($placeholders)

        GROUP BY jadwal_id

    ");



    $stmt->execute($params);



    foreach ($stmt->fetchAll() as $row) {



        $presensiSesiMap[(int) $row['jadwal_id']] =

            (int) $row['jumlah'];

    }





    



    $paramsIzin = array_merge(

        [$guruId, $tanggalHariIni],

        $jadwalIds

    );



    $stmt = $pdo->prepare("

        SELECT DISTINCT

            pj.jadwal_id

        FROM perizinan_jadwal pj



        INNER JOIN perizinan p

            ON p.id = pj.perizinan_id



        WHERE p.guru_id = ?

          AND p.status = 'Disetujui'

          AND ? BETWEEN p.tanggal_mulai

                    AND p.tanggal_selesai

          AND pj.jadwal_id IN ($placeholders)

    ");



    $stmt->execute($paramsIzin);



    foreach ($stmt->fetchAll() as $row) {

        $izinJadwalMap[(int) $row['jadwal_id']] = true;

    }

}









foreach ($jadwalList as &$jadwal) {



    $jadwalIdCurrent = (int) $jadwal['id'];



    $sudahPresensi =

        isset($presensiSesiMap[$jadwalIdCurrent]);



    $izinDisetujui =

        $jadwal['jenis_jadwal'] === 'Normal'

        && isset($izinJadwalMap[$jadwalIdCurrent]);



    $belumMulai =

        $waktuSekarang < $jadwal['jam_mulai'];



    $sudahSelesai =

        $waktuSekarang >= $jadwal['jam_selesai'];



    $sedangBerlangsung =

        !$belumMulai

        && !$sudahSelesai;



    $bolehPresensi =

        !$izinDisetujui

        && !$sudahPresensi

        && $sedangBerlangsung;



    $lockReason = '';



    if ($izinDisetujui) {



        $lockReason = 'Izin guru disetujui untuk jadwal ini.';



    } elseif ($sudahPresensi) {



        $lockReason = 'Presensi siswa untuk sesi ini sudah tersimpan.';



    } elseif ($belumMulai) {



        $lockReason = 'Belum mulai. Presensi aktif tepat saat KBM dimulai.';



    } elseif ($sudahSelesai) {



        $lockReason = 'Sesi KBM sudah selesai.';



    }



    $jadwal['sudah_presensi'] = $sudahPresensi;

    $jadwal['izin_disetujui'] = $izinDisetujui;

    $jadwal['belum_mulai'] = $belumMulai;

    $jadwal['sudah_selesai'] = $sudahSelesai;

    $jadwal['sedang_berlangsung'] = $sedangBerlangsung;

    $jadwal['boleh_presensi'] = $bolehPresensi;

    $jadwal['lock_reason'] = $lockReason;

}

unset($jadwal);









$jadwalId = (int) ($_GET['jadwal_id'] ?? 0);

$jadwalAktif = null;



if ($jadwalId > 0) {



    foreach ($jadwalList as $jadwal) {



        if ((int) $jadwal['id'] === $jadwalId) {

            $jadwalAktif = $jadwal;

            break;

        }

    }

}









$siswaList = [];

$presensiMap = [];



if ($jadwalAktif) {



    $stmt = $pdo->prepare("

        SELECT

            id,

            nis,

            nama_lengkap,

            jenis_kelamin

        FROM siswa

        WHERE kelas_id = ?

          AND status = 'aktif'

        ORDER BY nama_lengkap ASC

    ");



    $stmt->execute([

        $jadwalAktif['kelas_id']

    ]);



    $siswaList = $stmt->fetchAll();





    



    $stmt = $pdo->prepare("

        SELECT

            siswa_id,

            status,

            keterangan

        FROM presensi_siswa

        WHERE jadwal_id = ?

          AND tanggal = ?

    ");



    $stmt->execute([

        $jadwalId,

        $tanggalHariIni

    ]);



    foreach ($stmt->fetchAll() as $row) {



        $presensiMap[$row['siswa_id']] = [

            'status' => $row['status'],

            'keterangan' => $row['keterangan']

        ];

    }

}









$flashSuccess = flash('success');

$flashError = flash('error');









$stmtPiket = $pdo->prepare("

    SELECT id

    FROM jadwal_piket

    WHERE guru_id = ?

      AND hari = ?

    LIMIT 1

");



$stmtPiket->execute([

    $guruId,

    $hariIni

]);



$isPiket = $stmtPiket->fetch();









$activeMenu   = 'presensi-siswa';

$piketHariIni = (bool) $isPiket;

$pageTitle    = 'Presensi Siswa';

$pageSubtitle = 'Catat kehadiran siswa';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Presensi Siswa - SMKS Mulia Buana</title>



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

                            Presensi Siswa

                        </span>



                    </div>



                    <h1 class="page-title">

                        Presensi Siswa

                    </h1>



                    <p class="page-description">

                        <?= e($hariIni) ?>,

                        <?= e(formatTanggal($tanggalHariIni)) ?>

                    </p>



                </div>





                <?php if ($flashSuccess): ?>



                    <div class="alert alert-success">

                        <?= e($flashSuccess) ?>

                    </div>



                <?php endif; ?>





                <?php if ($flashError): ?>



                    <div class="alert alert-danger">

                        <?= e($flashError) ?>

                    </div>



                <?php endif; ?>





                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("users", "", 18) ?>

                            </span>



                            Jadwal Mengajar Hari Ini



                        </h2>



                    </div>



                    <div class="dashboard-card-body">



                        <?php if (!$jadwalList): ?>



                            <div class="empty-state">



                                <div class="empty-state-icon"><?= appIcon("users", "", 48) ?></div>



                                <h3>

                                    Tidak Ada Jadwal

                                </h3>



                                <p>

                                    Tidak ada jadwal mengajar atau

                                    jadwal pengganti untuk hari ini.

                                </p>



                            </div>



                        <?php else: ?>



                            <div class="session-grid">



                                <?php foreach ($jadwalList as $jadwal): ?>



                                    <?php

                                    $isActiveCard =

                                        $jadwalAktif

                                        && (int) $jadwalAktif['id'] === (int) $jadwal['id']

                                        && $jadwalAktif['jenis_jadwal'] === $jadwal['jenis_jadwal'];



                                    $isTimeLocked =

                                        $jadwal['belum_mulai']

                                        || $jadwal['sudah_selesai'];



                                    $cardLocked =

                                        !$jadwal['boleh_presensi'];



                                    $startEpoch = strtotime(

                                        $tanggalHariIni . ' ' . $jadwal['jam_mulai']

                                    );



                                    $endEpoch = strtotime(

                                        $tanggalHariIni . ' ' . $jadwal['jam_selesai']

                                    );

                                    ?>



                                    <a

                                        href="presensi-siswa.php?jadwal_id=<?= (int) $jadwal['id'] ?>"

                                        class="session-card <?= $isActiveCard ? 'active' : '' ?>"

                                        <?= $cardLocked ? 'style="opacity:.62;cursor:not-allowed;"' : '' ?>

                                        <?= $cardLocked ? 'aria-disabled="true"' : '' ?>

                                        <?= $cardLocked ? 'tabindex="-1"' : '' ?>

                                        data-jadwal-id="<?= (int) $jadwal['id'] ?>"

                                        data-jadwal-type="<?= e($jadwal['jenis_jadwal']) ?>"

                                        data-time-lock="<?= $isTimeLocked ? '1' : '0' ?>"

                                        data-start-epoch="<?= (int) $startEpoch ?>"

                                        data-end-epoch="<?= (int) $endEpoch ?>"

                                        data-permanent-lock="<?= (

                                            $jadwal['izin_disetujui']

                                            || $jadwal['sudah_presensi']

                                        ) ? '1' : '0' ?>"

                                        data-lock-reason="<?= e($jadwal['lock_reason']) ?>"

                                        onclick="<?= $cardLocked ? 'return false;' : 'return true;' ?>"

                                    >



                                        <div class="session-time">



                                            <?= e(substr($jadwal['jam_mulai'], 0, 5)) ?>



                                            -



                                            <?= e(substr($jadwal['jam_selesai'], 0, 5)) ?>



                                        </div>



                                        <div class="session-title">

                                            <?= e($jadwal['nama_mapel']) ?>

                                        </div>



                                        <div class="session-info">



                                            <?= e($jadwal['tingkat']) ?>

                                            <?= e($jadwal['nama_kelas']) ?>



                                        </div>



                                        <div class="session-info">



                                            <?= e($jadwal['nama_jurusan']) ?>



                                        </div>





                                        <?php if ($jadwal['jenis_jadwal'] === 'Pengganti'): ?>



                                            <div class="session-badge replacement">

                                                Jadwal Pengganti

                                            </div>



                                            <div class="session-info">



                                                Guru Asal:



                                                <strong>

                                                    <?= e($jadwal['nama_guru_asal']) ?>

                                                </strong>



                                            </div>



                                        <?php else: ?>



                                            <div class="session-badge">

                                                Jadwal Saya

                                            </div>



                                        <?php endif; ?>





                                        <?php if ($jadwal['lock_reason'] !== ''): ?>



                                            <div

                                                class="session-info"

                                                style="margin-top:8px;font-size:12px;"

                                            >

                                                <?= e($jadwal['lock_reason']) ?>

                                            </div>



                                        <?php endif; ?>



                                    </a>



                                <?php endforeach; ?>



                            </div>



                        <?php endif; ?>



                    </div>



                </div>





                <?php if ($jadwalAktif): ?>



                    <div class="dashboard-card">



                        <div class="dashboard-card-header">



                            <h2 class="dashboard-card-title">



                                <span class="dashboard-card-title-icon"><?= appIcon("info", "", 18) ?></span>



                                Informasi Sesi



                            </h2>



                        </div>



                        <div class="dashboard-card-body">



                            <div class="info-grid">



                                <div class="info-box">



                                    <div class="info-label">

                                        Mata Pelajaran

                                    </div>



                                    <div class="info-value">

                                        <?= e($jadwalAktif['nama_mapel']) ?>

                                    </div>



                                </div>



                                <div class="info-box">



                                    <div class="info-label">

                                        Kelas

                                    </div>



                                    <div class="info-value">

                                        <?= e($jadwalAktif['tingkat']) ?>

                                        <?= e($jadwalAktif['nama_kelas']) ?>

                                    </div>



                                </div>



                                <div class="info-box">



                                    <div class="info-label">

                                        Tanggal

                                    </div>



                                    <div class="info-value">

                                        <?= e(formatTanggal($tanggalHariIni)) ?>

                                    </div>



                                </div>



                                <div class="info-box">



                                    <div class="info-label">

                                        Waktu

                                    </div>



                                    <div class="info-value">

                                        <?= e(formatJam($jadwalAktif['jam_mulai'])) ?>

                                        -

                                        <?= e(formatJam($jadwalAktif['jam_selesai'])) ?>

                                    </div>



                                </div>



                            </div>





                            <?php if ($jadwalAktif['jenis_jadwal'] === 'Pengganti'): ?>



                                <div

                                    class="alert alert-info"

                                    style="margin-top:18px;"

                                >



                                    <strong>

                                        Jadwal Pengganti

                                    </strong>



                                    <br>



                                    Anda sedang menggantikan:



                                    <strong>

                                        <?= e($jadwalAktif['nama_guru_asal']) ?>

                                    </strong>



                                    untuk mata pelajaran



                                    <strong>

                                        <?= e($jadwalAktif['nama_mapel']) ?>

                                    </strong>



                                    di kelas



                                    <strong>

                                        <?= e($jadwalAktif['tingkat']) ?>

                                        <?= e($jadwalAktif['nama_kelas']) ?>

                                    </strong>.



                                </div>



                            <?php endif; ?>





                            <?php if ($jadwalAktif['izin_disetujui']): ?>



                                <div

                                    class="alert alert-danger"

                                    style="margin-top:18px;"

                                >

                                    Jadwal ini tidak dapat digunakan untuk

                                    presensi siswa karena Anda memiliki

                                    perizinan yang telah disetujui untuk

                                    jadwal ini hari ini.

                                </div>



                            <?php elseif ($jadwalAktif['sudah_presensi']): ?>



                                <div

                                    class="alert alert-info"

                                    style="margin-top:18px;"

                                >

                                    Presensi siswa untuk sesi ini sudah

                                    tersimpan. Halaman presensi biasa

                                    dikunci untuk mencegah pengisian dua kali.

                                    Jika perlu perubahan, gunakan proses koreksi

                                    yang tersedia.

                                </div>



                            <?php elseif ($jadwalAktif['belum_mulai']): ?>



                                <div

                                    class="alert alert-info"

                                    style="margin-top:18px;"

                                    id="time-lock-message"

                                >

                                    Presensi siswa belum dibuka. Presensi aktif

                                    tepat pada pukul

                                    <strong>

                                        <?= e(formatJam($jadwalAktif['jam_mulai'])) ?>

                                    </strong>.

                                </div>



                            <?php elseif ($jadwalAktif['sudah_selesai']): ?>



                                <div

                                    class="alert alert-danger"

                                    style="margin-top:18px;"

                                    id="time-lock-message"

                                >

                                    Sesi KBM sudah selesai sehingga presensi

                                    siswa tidak dapat dilakukan lagi.

                                </div>



                            <?php endif; ?>



                        </div>



                    </div>





                    <?php

                    $showAttendanceForm =

                        $jadwalAktif['sudah_presensi']

                        || (

                            !$jadwalAktif['izin_disetujui']

                            && !$jadwalAktif['sudah_selesai']

                        );

                    ?>



                    <?php if ($showAttendanceForm): ?>



                        <div class="dashboard-card">



                            <div class="dashboard-card-header">



                                <h2 class="dashboard-card-title">



                                    <span class="dashboard-card-title-icon">

                                        ✓

                                    </span>



                                    Daftar Kehadiran Siswa



                                </h2>



                            </div>



                            <div class="dashboard-card-body">



                                <form

                                    method="POST"

                                    action="../proses/presensi-siswa.php"

                                    id="form-presensi-siswa"

                                    data-start-epoch="<?= (int) strtotime($tanggalHariIni . ' ' . $jadwalAktif['jam_mulai']) ?>"

                                    data-end-epoch="<?= (int) strtotime($tanggalHariIni . ' ' . $jadwalAktif['jam_selesai']) ?>"

                                    data-server-now="<?= (int) $serverNowEpoch ?>"

                                    data-already-saved="<?= $jadwalAktif['sudah_presensi'] ? '1' : '0' ?>"

                                >



                                    <input

                                        type="hidden"

                                        name="jadwal_id"

                                        value="<?= (int) $jadwalAktif['id'] ?>"

                                    >



                                    <div class="schedule-table-wrap">



                                        <table class="schedule-table">



                                            <thead>



                                                <tr>



                                                    <th>No</th>

                                                    <th>NIS</th>

                                                    <th>Nama Siswa</th>

                                                    <th>Status Kehadiran</th>



                                                </tr>



                                            </thead>



                                            <tbody>



                                            <?php if (!$siswaList): ?>



                                                <tr>



                                                    <td

                                                        colspan="4"

                                                        class="empty"

                                                    >

                                                        Belum ada siswa aktif

                                                        pada kelas ini.

                                                    </td>



                                                </tr>



                                            <?php else: ?>



                                                <?php foreach (

                                                    $siswaList

                                                    as $index => $siswa

                                                ): ?>



                                                    <?php

                                                    $statusSiswa =

                                                        $presensiMap[$siswa['id']]['status']

                                                        ?? 'Hadir';

                                                    ?>



                                                    <tr>



                                                        <td>

                                                            <?= $index + 1 ?>

                                                        </td>



                                                        <td>

                                                            <?= e($siswa['nis']) ?>

                                                        </td>



                                                        <td>

                                                            <?= e($siswa['nama_lengkap']) ?>

                                                        </td>



                                                        <td>



                                                            <select

                                                                name="status[<?= (int) $siswa['id'] ?>]"

                                                                required

                                                                <?= (

                                                                    $jadwalAktif['sudah_presensi']

                                                                    || $jadwalAktif['belum_mulai']

                                                                ) ? 'disabled' : '' ?>

                                                            >



                                                                <option

                                                                    value="Hadir"

                                                                    <?= $statusSiswa === 'Hadir' ? 'selected' : '' ?>

                                                                >

                                                                    Hadir

                                                                </option>



                                                                <option

                                                                    value="Izin"

                                                                    <?= $statusSiswa === 'Izin' ? 'selected' : '' ?>

                                                                >

                                                                    Izin

                                                                </option>



                                                                <option

                                                                    value="Sakit"

                                                                    <?= $statusSiswa === 'Sakit' ? 'selected' : '' ?>

                                                                >

                                                                    Sakit

                                                                </option>



                                                                <option

                                                                    value="Alfa"

                                                                    <?= $statusSiswa === 'Alfa' ? 'selected' : '' ?>

                                                                >

                                                                    Alfa

                                                                </option>



                                                            </select>



                                                        </td>



                                                    </tr>



                                                <?php endforeach; ?>



                                            <?php endif; ?>



                                            </tbody>



                                        </table>



                                    </div>





                                    <?php if ($siswaList && !$jadwalAktif['sudah_presensi']): ?>



                                        <div

                                            class="form-actions"

                                            id="attendance-actions"

                                        >



                                            <button

                                                type="submit"

                                                class="btn btn-primary"

                                                id="btn-simpan-presensi"

                                                <?= $jadwalAktif['belum_mulai'] ? 'disabled' : '' ?>

                                            >

                                                Simpan Absensi

                                            </button>



                                        </div>



                                    <?php endif; ?>



                                </form>



                            </div>



                        </div>



                    <?php endif; ?>



                <?php endif; ?>





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





<script>

(function () {



    const serverNow = <?= (int) $serverNowEpoch ?> * 1000;

    const clientLoadedAt = Date.now();



    function getServerNow() {

        return serverNow + (Date.now() - clientLoadedAt);

    }



    function updateTimeLockedCards() {



        const cards = document.querySelectorAll(

            '.session-card[data-time-lock="1"]'

        );



        const now = Math.floor(getServerNow() / 1000);



        cards.forEach(function (card) {



            if (

                card.dataset.permanentLock === '1'

            ) {

                return;

            }



            const start = parseInt(

                card.dataset.startEpoch || '0',

                10

            );



            const end = parseInt(

                card.dataset.endEpoch || '0',

                10

            );



            if (

                now >= start &&

                now < end

            ) {



                card.style.opacity = '';

                card.style.cursor = '';

                card.removeAttribute('aria-disabled');

                card.removeAttribute('tabindex');

                card.removeAttribute('onclick');



            } else if (now >= end) {



                card.style.opacity = '.62';

                card.style.cursor = 'not-allowed';

                card.setAttribute(

                    'aria-disabled',

                    'true'

                );

                card.setAttribute(

                    'tabindex',

                    '-1'

                );

                card.setAttribute(

                    'onclick',

                    'return false;'

                );



            }



        });





        const form =

            document.getElementById(

                'form-presensi-siswa'

            );



        if (!form) {

            return;

        }



        if (

            form.dataset.alreadySaved === '1'

        ) {

            return;

        }



        const start = parseInt(

            form.dataset.startEpoch || '0',

            10

        );



        const end = parseInt(

            form.dataset.endEpoch || '0',

            10

        );



        const now = Math.floor(

            getServerNow() / 1000

        );



        const controls =

            form.querySelectorAll(

                'select[name^="status["]'

            );



        const button =

            document.getElementById(

                'btn-simpan-presensi'

            );



        const message =

            document.getElementById(

                'time-lock-message'

            );



        if (

            now >= start &&

            now < end

        ) {



            controls.forEach(function (control) {

                control.disabled = false;

            });



            if (button) {

                button.disabled = false;

                button.style.opacity = '';

                button.style.cursor = '';

            }



            if (message) {

                message.style.display = 'none';

            }



        } else {



            controls.forEach(function (control) {

                control.disabled = true;

            });



            if (button) {

                button.disabled = true;

                button.style.opacity = '.6';

                button.style.cursor = 'not-allowed';

            }



        }



    }



    updateTimeLockedCards();



    setInterval(

        updateTimeLockedCards,

        1000

    );



})();

</script>



</body>



</html>