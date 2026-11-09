<?php



date_default_timezone_set('Asia/Jakarta');



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('guru');



$userId = currentUserId();



$tanggalHariIni = date('Y-m-d');

$waktuSekarang = date('H:i:s');



$namaHari = [

    'Sunday' => 'Minggu',

    'Monday' => 'Senin',

    'Tuesday' => 'Selasa',

    'Wednesday' => 'Rabu',

    'Thursday' => 'Kamis',

    'Friday' => 'Jumat',

    'Saturday' => 'Sabtu'

];



$hariIni = $namaHari[date('l')];









$stmt = $pdo->prepare("

    SELECT

        g.id,

        g.nama_lengkap,

        m.nama_mapel

    FROM guru g

    LEFT JOIN mapel m

        ON m.id = g.mapel_utama_id

    WHERE g.user_id = ?

    LIMIT 1

");



$stmt->execute([

    $userId

]);



$guru = $stmt->fetch();



if (!$guru) {

    die('Data guru tidak ditemukan. Hubungi Admin TU.');

}



$guruId = $guru['id'];









$stmt = $pdo->prepare("

    SELECT

        id,

        hari,

        keterangan

    FROM jadwal_piket

    WHERE guru_id = ?

      AND hari = ?

    LIMIT 1

");



$stmt->execute([

    $guruId,

    $hariIni

]);



$piketHariIni = $stmt->fetch();









if (!$piketHariIni) {



    http_response_code(403);



    die('

        <div style="font-family: Arial, sans-serif; padding: 30px;">



            <h2>Akses Tidak Tersedia</h2>



            <p>

                Anda tidak bertugas sebagai Guru Piket hari ini.

            </p>



            <p>

                <a href="dashboard.php">

                    &larr; Kembali ke Dashboard Guru

                </a>

            </p>



        </div>

    ');

}









$success = flash('success');

$error = flash('error');









$search = trim($_GET['search'] ?? '');
$kategori = trim($_GET['kategori'] ?? 'guru');
if (!in_array($kategori, ['semua', 'guru', 'mapel', 'kelas'], true)) {
    $kategori = 'guru';
}









$sql = "

    SELECT

        j.id,

        j.jam_mulai,

        j.jam_selesai,

        m.nama_mapel,

        k.nama_kelas,

        g.id AS guru_id,

        g.nama_lengkap AS nama_guru,

        pg.waktu_masuk,

        pg.waktu_pulang,

        p.id AS perizinan_id,

        p.jenis_izin,

        p.alasan,

        p.status AS status_perizinan,

        pa.id AS pengampu_id

    FROM jadwal j

    INNER JOIN guru g ON g.id = j.guru_id

    INNER JOIN mapel m ON m.id = j.mapel_id

    INNER JOIN kelas k ON k.id = j.kelas_id

    LEFT JOIN presensi_guru pg

        ON pg.guru_id = j.guru_id

        AND pg.tanggal = ?

    LEFT JOIN perizinan_jadwal pj

        ON pj.jadwal_id = j.id

    LEFT JOIN perizinan p

        ON p.id = pj.perizinan_id

        AND ? BETWEEN p.tanggal_mulai AND p.tanggal_selesai

        AND p.id = (

            SELECT MAX(p2.id)

            FROM perizinan_jadwal pj2

            INNER JOIN perizinan p2 ON p2.id = pj2.perizinan_id

            WHERE pj2.jadwal_id = j.id

              AND ? BETWEEN p2.tanggal_mulai AND p2.tanggal_selesai

              AND p2.status IN ('Menunggu', 'Disetujui')

        )

        LEFT JOIN pengampu pa

            ON pa.jadwal_id = j.id

            AND pa.tanggal = ?

            AND pa.status = 'Aktif'

    WHERE j.hari = ?

      AND j.guru_id <> ?

";

$params = [

    $tanggalHariIni,

    $tanggalHariIni,

    $tanggalHariIni,

    $tanggalHariIni,

    $hariIni,

    $guruId

];



if ($search !== '') {
    $searchValue = '%' . $search . '%';
    if ($kategori === 'guru') {
        $sql .= " AND g.nama_lengkap LIKE ? ";
        $params[] = $searchValue;
    } elseif ($kategori === 'mapel') {
        $sql .= " AND m.nama_mapel LIKE ? ";
        $params[] = $searchValue;
    } elseif ($kategori === 'kelas') {
        $sql .= " AND k.nama_kelas LIKE ? ";
        $params[] = $searchValue;
    } else {
        $sql .= " AND (g.nama_lengkap LIKE ? OR m.nama_mapel LIKE ? OR k.nama_kelas LIKE ?) ";
        $params[] = $searchValue;
        $params[] = $searchValue;
        $params[] = $searchValue;
    }
}



$sql .= "

    ORDER BY

        j.jam_mulai ASC,

        k.nama_kelas ASC

";



$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$jadwalHariIni = $stmt->fetchAll();









$jadwalDenganIzin = [];

$jadwalLain = [];



foreach ($jadwalHariIni as $jadwal) {



    if (

        !empty($jadwal['perizinan_id']) &&

        in_array(

            $jadwal['status_perizinan'],

            ['Menunggu', 'Disetujui'],

            true

        )

    ) {



        $jadwalDenganIzin[] = $jadwal;



    } else {



        $jadwalLain[] = $jadwal;

    }

}









function statusJadwalPiket($jadwal, $waktuSekarang)

{

    



    



    if (

        !empty($jadwal['perizinan_id'])

        &&

        $jadwal['status_perizinan'] === 'Disetujui'

    ) {

        if (!empty($jadwal['pengampu_id'])) {

            return [

                'label' => 'Sudah Diambil Alih',

                'class' => 'success'

            ];

        }



        if ($waktuSekarang < $jadwal['jam_mulai']) {

            return [

                'label' => 'Izin Disetujui',

                'class' => 'secondary'

            ];

        }



        if ($waktuSekarang >= $jadwal['jam_selesai']) {

            return [

                'label' => 'Izin Disetujui - KBM Selesai',

                'class' => 'secondary'

            ];

        }



        return [

            'label' => 'Izin Disetujui',

            'class' => 'warning'

        ];

    }



    if (!empty($jadwal['pengampu_id'])) {

        return [

            'label' => 'Sudah Diambil Alih',

            'class' => 'success'

        ];

    }



    if (!empty($jadwal['waktu_masuk'])) {

        return [

            'label' => 'Sudah Hadir',

            'class' => 'success'

        ];

    }



    if ($waktuSekarang >= $jadwal['jam_selesai']) {

        return [

            'label' => 'KBM Selesai',

            'class' => 'secondary'

        ];

    }



    if ($waktuSekarang >= $jadwal['jam_mulai']) {

        return [

            'label' => 'Belum Hadir',

            'class' => 'warning'

        ];

    }



    return [

        'label' => 'Belum Dimulai',

        'class' => 'secondary'

    ];

}









$activeMenu   = 'dashboard-piket';

$piketHariIni = true;

$pageTitle    = 'Dashboard Guru Piket';

$pageSubtitle = 'Monitoring kehadiran guru hari ini';

?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Dashboard Piket - SMKS Mulia Buana</title>



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



                        <a href="dashboard.php">Dashboard</a>



                        <span>/</span>



                        <span>Guru Piket</span>



                    </div>





                    <h1 class="page-title">

                        Dashboard Guru Piket

                    </h1>





                    <p class="page-description">



                        Selamat datang,

                        <strong><?= e($guru['nama_lengkap']) ?></strong>



                        &mdash;



                        <?= e($hariIni) ?>,

                        <?= e(formatTanggal($tanggalHariIni)) ?>



                    </p>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-body">



                        <div class="piket-card">



                            <div class="piket-card-content">



                                <span class="piket-card-label">

                                    Tugas Hari Ini

                                </span>



                                <h3 class="piket-card-title">

                                    Anda Bertugas Sebagai Guru Piket

                                </h3>



                                <p class="piket-card-description">



                                    <?= e(

                                        $piketHariIni['keterangan']

                                        ?? 'Silakan melakukan monitoring jadwal dan membantu penanganan ketidakhadiran guru.'

                                    ) ?>



                                </p>



                            </div>



                        </div>



                    </div>



                </div>





                



                <?php if ($success): ?>



                    <div class="alert alert-success">

                        <?= e($success) ?>

                    </div>



                <?php endif; ?>





                <?php if ($error): ?>



                    <div class="alert alert-danger">

                        <?= e($error) ?>

                    </div>



                <?php endif; ?>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("perizinan", "", 18) ?>

                            </span>



                            Guru Tidak Hadir Terencana



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <p class="page-description" style="margin-top: 0;">

                            Daftar berikut berasal dari pengajuan perizinan

                            yang berkaitan dengan jadwal mengajar hari ini.

                        </p>





                        <?php if (empty($jadwalDenganIzin)): ?>





                            <div class="empty-state">



                                <div class="empty-state-icon">

                                    ✓

                                </div>



                                <h3>

                                    Tidak Ada Perizinan

                                </h3>



                                <p>

                                    Tidak ada pengajuan perizinan yang

                                    terkait dengan jadwal mengajar hari ini.

                                </p>



                            </div>





                        <?php else: ?>





                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>



                                            <th>Waktu</th>

                                            <th>Guru</th>

                                            <th>Mata Pelajaran</th>

                                            <th>Kelas</th>

                                            <th>Jenis Izin</th>

                                            <th>Status</th>

                                            <th>Tindakan</th>



                                        </tr>



                                    </thead>





                                    <tbody>



                                    <?php foreach ($jadwalDenganIzin as $jadwal): ?>



                                        <?php



                                        $statusJadwal = statusJadwalPiket(

                                            $jadwal,

                                            $waktuSekarang

                                        );



                                        ?>



                                        <tr>



                                            <td class="schedule-time">



                                                <?= e(

                                                    formatJam(

                                                        $jadwal['jam_mulai']

                                                    )

                                                ) ?>



                                                -



                                                <?= e(

                                                    formatJam(

                                                        $jadwal['jam_selesai']

                                                    )

                                                ) ?>



                                            </td>





                                            <td class="schedule-subject">

                                                <?= e($jadwal['nama_guru']) ?>

                                            </td>





                                            <td>

                                                <?= e($jadwal['nama_mapel']) ?>

                                            </td>





                                            <td>

                                                <?= e($jadwal['nama_kelas']) ?>

                                            </td>





                                            <td>

                                                <?= e($jadwal['jenis_izin']) ?>

                                            </td>





                                            <td>



                                                <span class="status-badge <?= e($statusJadwal['class']) ?>">

                                                    <?= e($statusJadwal['label']) ?>

                                                </span>



                                                <br>



                                                <span style="font-size: 10px; color: var(--text-500); margin-top: 4px; display: inline-block;">

                                                    Izin:

                                                    <strong>

                                                        <?= e($jadwal['status_perizinan']) ?>

                                                    </strong>

                                                </span>



                                            </td>





                                            <td>



                                                <?php if (

                                                    $jadwal['status_perizinan'] === 'Disetujui'

                                                    &&

                                                    !empty($jadwal['perizinan_id'])

                                                    &&

                                                    empty($jadwal['pengampu_id'])

                                                    &&

                                                    $waktuSekarang >= $jadwal['jam_mulai']

                                                    &&

                                                    $waktuSekarang < $jadwal['jam_selesai']

                                                ): ?>



                                                    <form

                                                        action="../proses/pengampu.php"

                                                        method="POST"

                                                    >



                                                        <input

                                                            type="hidden"

                                                            name="aksi"

                                                            value="ambil_alih"

                                                        >



                                                        <input

                                                            type="hidden"

                                                            name="jadwal_id"

                                                            value="<?= e($jadwal['id']) ?>"

                                                        >



                                                        <input

                                                            type="hidden"

                                                            name="tanggal"

                                                            value="<?= e($tanggalHariIni) ?>"

                                                        >



                                                        <input

                                                            type="hidden"

                                                            name="sumber"

                                                            value="Perizinan"

                                                        >



                                                        <button

                                                            type="submit"

                                                            class="btn btn-primary"

                                                        >

                                                            Ambil Alih

                                                        </button>



                                                    </form>





                                                <?php elseif (

                                                    !empty($jadwal['pengampu_id'])

                                                ): ?>



                                                    <span style="font-size: 11px; color: var(--text-500);">

                                                        Sudah Diambil Alih

                                                    </span>



                                                <?php elseif (

                                                    $jadwal['status_perizinan'] === 'Menunggu'

                                                ): ?>



                                                    <span style="font-size: 11px; color: var(--text-500);">

                                                        Menunggu persetujuan Admin TU

                                                    </span>



                                                <?php elseif (

                                                    $jadwal['status_perizinan'] === 'Disetujui'

                                                    &&

                                                    $waktuSekarang < $jadwal['jam_mulai']

                                                ): ?>



                                                    <span style="font-size: 11px; color: var(--text-500);">

                                                        Belum masuk jam KBM

                                                    </span>



                                                <?php elseif (

                                                    $jadwal['status_perizinan'] === 'Disetujui'

                                                    &&

                                                    $waktuSekarang >= $jadwal['jam_selesai']

                                                ): ?>



                                                    <span style="font-size: 11px; color: var(--text-500);">

                                                        Waktu KBM sudah selesai

                                                    </span>



                                                <?php else: ?>



                                                    <span style="color: var(--text-400);">

                                                        -

                                                    </span>



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





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("search", "", 18) ?>

                            </span>



                            Cari Guru / Jadwal



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <p class="page-description" style="margin-top: 0;">

                            Gunakan pencarian ini untuk menemukan jadwal guru

                            yang belum melakukan presensi datang, termasuk

                            ketidakhadiran yang belum memiliki pengajuan izin.

                        </p>





                        <form

                            method="GET"

                            action="dashboard_piket.php"

                            class="filter-bar"

                        >



                            <div class="filter-bar-item" style="max-width: 200px;">
                                <label for="kategori" class="form-label">
                                    Cari Berdasarkan
                                </label>
                                <select id="kategori" name="kategori" class="form-control" onchange="updatePlaceholder()">
                                    <option value="guru" <?= $kategori === 'guru' ? 'selected' : '' ?>>Nama Guru</option>
                                    <option value="semua" <?= $kategori === 'semua' ? 'selected' : '' ?>>Semua (Global)</option>
                                    <option value="mapel" <?= $kategori === 'mapel' ? 'selected' : '' ?>>Mata Pelajaran</option>
                                    <option value="kelas" <?= $kategori === 'kelas' ? 'selected' : '' ?>>Kelas</option>
                                </select>
                            </div>

                            <div class="filter-bar-item" style="max-width: 360px;">
                                <label for="search" class="form-label">
                                    Kata Kunci
                                </label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    value="<?= e($search) ?>"
                                    placeholder="<?= $kategori === 'guru' ? 'Masukkan nama guru (misal: Budi)...' : ($kategori === 'mapel' ? 'Masukkan mata pelajaran...' : ($kategori === 'kelas' ? 'Masukkan nama kelas...' : 'Cari guru, mapel, atau kelas...')) ?>"
                                >
                            </div>





                            <div class="filter-bar-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary btn-lg"

                                    style="min-width: 120px;"

                                >

                                    Cari

                                </button>





                                <?php if ($search !== ''): ?>



                                    <a

                                        href="dashboard_piket.php"

                                        class="btn btn-outline btn-lg"

                                        style="min-width: 120px;"

                                    >

                                        Reset

                                    </a>



                                <?php endif; ?>



                            </div>



                        </form>





                        <div style="margin-top: 20px;">



                            <?php if ($search === ''): ?>





                                <div class="alert alert-info">

                                    Silakan masukkan nama guru, mata pelajaran,

                                    atau kelas untuk mencari jadwal.

                                </div>





                            <?php elseif (empty($jadwalLain)): ?>





                                <div class="empty-state">



                                    <div class="empty-state-icon"><?= appIcon("search", "", 48) ?></div>



                                    <h3>

                                        Tidak Ditemukan

                                    </h3>



                                    <p>

                                        Tidak ditemukan jadwal yang sesuai

                                        dengan pencarian.

                                    </p>



                                </div>





                            <?php else: ?>





                                <div class="schedule-table-wrap">



                                    <table class="schedule-table">



                                        <thead>



                                            <tr>



                                                <th>Waktu</th>

                                                <th>Guru Terjadwal</th>

                                                <th>Mata Pelajaran</th>

                                                <th>Kelas</th>

                                                <th>Status</th>

                                                <th>Tindakan</th>



                                            </tr>



                                        </thead>





                                        <tbody>



                                        <?php foreach ($jadwalLain as $jadwal): ?>



                                            <?php



                                            $statusJadwal = statusJadwalPiket(

                                                $jadwal,

                                                $waktuSekarang

                                            );



                                            ?>



                                            <tr>



                                                <td class="schedule-time">



                                                    <?= e(

                                                        formatJam(

                                                            $jadwal['jam_mulai']

                                                        )

                                                    ) ?>



                                                    -



                                                    <?= e(

                                                        formatJam(

                                                            $jadwal['jam_selesai']

                                                        )

                                                    ) ?>



                                                </td>





                                                <td class="schedule-subject">

                                                    <?= e($jadwal['nama_guru']) ?>

                                                </td>





                                                <td>

                                                    <?= e($jadwal['nama_mapel']) ?>

                                                </td>





                                                <td>

                                                    <?= e($jadwal['nama_kelas']) ?>

                                                </td>





                                                <td>



                                                    <span class="status-badge <?= e($statusJadwal['class']) ?>">

                                                        <?= e($statusJadwal['label']) ?>

                                                    </span>



                                                </td>





                                                <td>



                                                    <?php if (

                                                        empty($jadwal['pengampu_id'])

                                                        &&

                                                        empty($jadwal['waktu_masuk'])

                                                        &&

                                                        $waktuSekarang >= $jadwal['jam_mulai']

                                                        &&

                                                        $waktuSekarang < $jadwal['jam_selesai']

                                                    ): ?>



                                                        <form

                                                            action="../proses/pengampu.php"

                                                            method="POST"

                                                        >



                                                            <input

                                                                type="hidden"

                                                                name="aksi"

                                                                value="ambil_alih"

                                                            >



                                                            <input

                                                                type="hidden"

                                                                name="jadwal_id"

                                                                value="<?= e($jadwal['id']) ?>"

                                                            >



                                                            <input

                                                                type="hidden"

                                                                name="tanggal"

                                                                value="<?= e($tanggalHariIni) ?>"

                                                            >



                                                            <input

                                                                type="hidden"

                                                                name="sumber"

                                                                value="Ketidakhadiran"

                                                            >



                                                            <button

                                                                type="submit"

                                                                class="btn btn-primary"

                                                            >

                                                                Ambil Alih

                                                            </button>



                                                        </form>



                                                    <?php elseif (

                                                        !empty($jadwal['pengampu_id'])

                                                    ): ?>



                                                        <span style="font-size: 11px; color: var(--text-500);">

                                                            Sudah Diambil Alih

                                                        </span>



                                                    <?php else: ?>



                                                        <span style="color: var(--text-400);">

                                                            -

                                                        </span>



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



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("info", "", 18) ?>

                            </span>



                            Informasi Guru Piket



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="notification-list">





                            <div class="notification-item">



                                <div class="notification-marker"></div>



                                <div class="notification-content">



                                    <div class="notification-title">

                                        Penentuan Guru Piket

                                    </div>



                                    <div class="notification-meta">

                                        Guru Piket ditentukan berdasarkan

                                        jadwal piket pada hari berjalan.

                                    </div>



                                </div>



                            </div>





                            <div class="notification-item">



                                <div class="notification-marker"></div>



                                <div class="notification-content">



                                    <div class="notification-title">

                                        Bukan role khusus

                                    </div>



                                    <div class="notification-meta">

                                        Guru Piket bukan role atau akun khusus.

                                    </div>



                                </div>



                            </div>





                            <div class="notification-item">



                                <div class="notification-marker orange"></div>



                                <div class="notification-content">



                                    <div class="notification-title">

                                        Status "Belum Hadir"

                                    </div>



                                    <div class="notification-meta">

                                        Guru yang belum melakukan presensi datang

                                        ditampilkan sebagai Belum Hadir.

                                    </div>



                                </div>



                            </div>





                            <div class="notification-item">



                                <div class="notification-marker orange"></div>



                                <div class="notification-content">



                                    <div class="notification-title">

                                        Tidak otomatis Izin/Sakit/Alfa

                                    </div>



                                    <div class="notification-meta">

                                        Sistem tidak otomatis mengubah status guru

                                        menjadi Izin, Sakit, atau Alfa.

                                    </div>



                                </div>



                            </div>





                            <div class="notification-item">



                                <div class="notification-marker gray"></div>



                                <div class="notification-content">



                                    <div class="notification-title">

                                        Pengambilalihan jadwal

                                    </div>



                                    <div class="notification-meta">

                                        Pengambilalihan jadwal dilakukan

                                        berdasarkan kondisi dan keputusan

                                        di lapangan.

                                    </div>



                                </div>



                            </div>





                        </div>



                    </div>



                </div>





                



                <a href="dashboard.php" class="back-link">

                    ← Kembali ke Dashboard

                </a>





            </div>



        </main>



    </div>



</div>



<script>
function updatePlaceholder() {
    const kat = document.getElementById('kategori').value;
    const searchInput = document.getElementById('search');
    if (!searchInput) return;
    if (kat === 'guru') {
        searchInput.placeholder = 'Masukkan nama guru (misal: Budi)...';
    } else if (kat === 'mapel') {
        searchInput.placeholder = 'Masukkan mata pelajaran (misal: Matematika)...';
    } else if (kat === 'kelas') {
        searchInput.placeholder = 'Masukkan nama kelas (misal: X TKJ 2)...';
    } else {
        searchInput.placeholder = 'Cari guru, mapel, atau kelas...';
    }
}
</script>
</body>



</html>