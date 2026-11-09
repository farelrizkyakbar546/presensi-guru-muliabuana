<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('kepsek');









$tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');
$kelasId = (int) ($_GET['kelas_id'] ?? 0);
$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$perPage = (int) ($_GET['per_page'] ?? 50);
if (!in_array($perPage, [25, 50, 100], true)) {
    $perPage = 50;
}
$page = max(1, (int) ($_GET['page'] ?? 1));





$statusValid = [

    'Hadir',

    'Izin',

    'Sakit',

    'Alfa'

];









$stmt = $pdo->query("

    SELECT

        k.id,

        k.nama_kelas,

        k.tingkat,

        j.nama_jurusan

    FROM kelas k

    INNER JOIN jurusan j

        ON j.id = k.jurusan_id

    ORDER BY

        k.tingkat ASC,

        k.nama_kelas ASC

");



$kelasList = $stmt->fetchAll();









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









$where = [

    'ps.tanggal BETWEEN ? AND ?'

];



$params = [

    $tanggalMulai,

    $tanggalSelesai

];





if ($kelasId > 0) {



    $where[] = 's.kelas_id = ?';

    $params[] = $kelasId;



}





if ($status !== '') {
    $where[] = 'ps.status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = '(s.nama_lengkap LIKE ? OR s.nis LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}





$whereSql = implode(' AND ', $where);





// 1. Hitung ringkasan statistik (Hadir, Izin, Sakit, Alfa) untuk seluruh data terfilter
$stmtSummary = $pdo->prepare("
    SELECT
        COUNT(*) AS total_data,
        SUM(CASE WHEN ps.status = 'Hadir' THEN 1 ELSE 0 END) AS total_hadir,
        SUM(CASE WHEN ps.status = 'Izin'  THEN 1 ELSE 0 END) AS total_izin,
        SUM(CASE WHEN ps.status = 'Sakit' THEN 1 ELSE 0 END) AS total_sakit,
        SUM(CASE WHEN ps.status = 'Alfa'  THEN 1 ELSE 0 END) AS total_alfa
    FROM presensi_siswa ps
    INNER JOIN siswa s ON s.id = ps.siswa_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    INNER JOIN jurusan j ON j.id = k.jurusan_id
    LEFT JOIN jadwal jd ON jd.id = ps.jadwal_id
    LEFT JOIN mapel m ON m.id = jd.mapel_id
    LEFT JOIN guru g ON g.id = jd.guru_id
    WHERE $whereSql
");
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

$totalData  = (int) ($summary['total_data'] ?? 0);
$totalHadir = (int) ($summary['total_hadir'] ?? 0);
$totalIzin  = (int) ($summary['total_izin'] ?? 0);
$totalSakit = (int) ($summary['total_sakit'] ?? 0);
$totalAlfa  = (int) ($summary['total_alfa'] ?? 0);

$totalPages = max(1, (int) ceil($totalData / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// 2. Ambil data dengan LIMIT dan OFFSET per halaman
$stmt = $pdo->prepare("
    SELECT
        ps.id,
        ps.tanggal,
        ps.waktu,
        ps.status,
        ps.keterangan,
        s.nis,
        s.nama_lengkap,
        k.nama_kelas,
        k.tingkat,
        j.nama_jurusan,
        m.nama_mapel,
        g.nama_lengkap AS nama_guru
    FROM presensi_siswa ps
    INNER JOIN siswa s ON s.id = ps.siswa_id
    INNER JOIN kelas k ON k.id = s.kelas_id
    INNER JOIN jurusan j ON j.id = k.jurusan_id
    LEFT JOIN jadwal jd ON jd.id = ps.jadwal_id
    LEFT JOIN mapel m ON m.id = jd.mapel_id
    LEFT JOIN guru g ON g.id = jd.guru_id
    WHERE $whereSql
    ORDER BY
        ps.tanggal DESC,
        ps.waktu DESC,
        k.nama_kelas ASC,
        s.nama_lengkap ASC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$presensiList = $stmt->fetchAll();









// Ringkasan statistik dihitung langsung via SQL $summary





$persentaseHadir = $totalData > 0

    ? round(($totalHadir / $totalData) * 100, 1)

    : 0;









$badgeMap = [

    'Hadir' => 'success',

    'Izin'  => 'warning',

    'Sakit' => 'info',

    'Alfa'  => 'danger',

];









$activeMenu   = 'monitoring-siswa';

$pageTitle    = 'Monitoring Siswa';

$pageSubtitle = 'Pantau kehadiran siswa';

?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Monitoring Presensi Siswa - Kepsek</title>



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



                        <span>Monitoring Siswa</span>



                    </div>





                    <h1 class="page-title">

                        Monitoring Presensi Siswa

                    </h1>





                    <p class="page-description">

                        Pantau kehadiran siswa berdasarkan periode,

                        kelas, dan status.

                    </p>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon"><?= appIcon("search", "", 18) ?></span>



                            Filter Presensi



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form method="GET">



                            <div class="form-grid">





                                <div class="form-group">



                                    <label for="tanggal_mulai" class="form-label">

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



                                    <label for="tanggal_selesai" class="form-label">

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



                                    <label for="kelas_id" class="form-label">

                                        Kelas

                                    </label>



                                    <select id="kelas_id" name="kelas_id">



                                        <option value="">

                                            Semua Kelas

                                        </option>



                                        <?php foreach ($kelasList as $kelas): ?>



                                            <option

                                                value="<?= (int) $kelas['id'] ?>"

                                                <?= $kelasId === (int) $kelas['id'] ? 'selected' : '' ?>

                                            >

                                                <?= e($kelas['nama_kelas']) ?>

                                                -

                                                <?= e($kelas['nama_jurusan']) ?>

                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                                                <div class="form-group">
                                    <label for="status" class="form-label">
                                        Status
                                    </label>
                                    <select id="status" name="status">
                                        <option value="">Semua Status</option>
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

                                <div class="form-group">
                                    <label for="search" class="form-label">
                                        Cari Nama / NIS
                                    </label>
                                    <input
                                        type="text"
                                        id="search"
                                        name="search"
                                        value="<?= e($search) ?>"
                                        placeholder="Ketik nama siswa atau NIS..."
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="per_page" class="form-label">
                                        Tampilkan
                                    </label>
                                    <select id="per_page" name="per_page">
                                        <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25 per hal</option>
                                        <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 per hal</option>
                                        <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 per hal</option>
                                    </select>
                                </div>



                            </div>





                            <div class="form-actions">



                                <button type="submit" class="btn btn-primary">

                                    Tampilkan Data

                                </button>



                                <a href="monitoring-siswa.php" class="btn btn-outline">

                                    Reset

                                </a>



                            </div>



                        </form>



                    </div>



                </div>





                



                <section class="stats-grid-5">





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



                            <div class="stat-icon">✓</div>



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



                            <div class="stat-icon">i</div>



                            <div>



                                <div class="stat-label">

                                    Sakit

                                </div>



                                <div class="stat-value">

                                    <?= $totalSakit ?>

                                </div>



                                <div class="stat-description">

                                    Status sakit

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



                            Data Presensi Siswa



                        </h2>





                        <div class="dashboard-card-link" style="cursor: default;">



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

                                        <th>Siswa</th>

                                        <th>Kelas</th>

                                        <th>Mata Pelajaran</th>

                                        <th>Guru</th>

                                        <th>Tanggal</th>

                                        <th>Waktu</th>

                                        <th>Status</th>

                                        <th>Keterangan</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$presensiList): ?>



                                    <tr>



                                        <td colspan="9" style="text-align:center; padding: 30px; color: var(--text-500);">



                                            Tidak ada data presensi siswa pada filter yang dipilih.



                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($presensiList as $index => $data): ?>



                                        <?php



                                        $badgeClass = $badgeMap[$data['status']] ?? 'secondary';



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

                                                    NIS: <?= e($data['nis']) ?>

                                                </div>



                                            </td>



                                            <td>



                                                <?= e($data['nama_kelas']) ?>



                                                <div class="schedule-code">

                                                    <?= e($data['nama_jurusan']) ?>

                                                </div>



                                            </td>



                                            <td>

                                                <?= e($data['nama_mapel'] ?: '-') ?>

                                            </td>



                                            <td>

                                                <?= e($data['nama_guru'] ?: '-') ?>

                                            </td>



                                            <td>

                                                <?= e(formatTanggal($data['tanggal'])) ?>

                                            </td>



                                            <td class="schedule-time">

                                                <?= e(formatJam($data['waktu'])) ?>

                                            </td>



                                            <td>



                                                <span class="status-badge <?= e($badgeClass) ?>">

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

                        <!-- PAGINATION KEPSEK -->
                        <?php if ($totalData > 0): ?>
                            <?php
                                $startRecord = $offset + 1;
                                $endRecord = min($offset + $perPage, $totalData);
                                $queryParams = [
                                    'tanggal_mulai'   => $tanggalMulai,
                                    'tanggal_selesai' => $tanggalSelesai,
                                    'kelas_id'        => $kelasId ?: null,
                                    'status'          => $status ?: null,
                                    'search'          => $search ?: null,
                                    'per_page'        => $perPage !== 50 ? $perPage : null,
                                ];
                                $buildPageUrl = function($p) use ($queryParams) {
                                    $params = array_filter(array_merge($queryParams, ['page' => $p]));
                                    return 'monitoring-siswa.php?' . http_build_query($params);
                                };
                            ?>
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Menampilkan <strong><?= $startRecord ?></strong> - <strong><?= $endRecord ?></strong> dari <strong><?= number_format($totalData, 0, ',', '.') ?></strong> presensi
                                </div>
                                <div class="pagination-links">
                                    <?php if ($page > 1): ?>
                                        <a href="<?= e($buildPageUrl(1)) ?>" class="pagination-btn" title="Halaman Pertama">&laquo;</a>
                                        <a href="<?= e($buildPageUrl($page - 1)) ?>" class="pagination-btn" title="Sebelumnya">&lsaquo;</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">&laquo;</span>
                                        <span class="pagination-btn disabled">&lsaquo;</span>
                                    <?php endif; ?>

                                    <?php
                                        $startP = max(1, $page - 2);
                                        $endP = min($totalPages, $page + 2);
                                        if ($startP > 1) {
                                            echo '<a href="' . e($buildPageUrl(1)) . '" class="pagination-btn">1</a>';
                                            if ($startP > 2) echo '<span class="pagination-ellipsis">...</span>';
                                        }
                                        for ($p = $startP; $p <= $endP; $p++) {
                                            $active = ($p === $page) ? 'active' : '';
                                            echo '<a href="' . e($buildPageUrl($p)) . '" class="pagination-btn ' . $active . '">' . $p . '</a>';
                                        }
                                        if ($endP < $totalPages) {
                                            if ($endP < $totalPages - 1) echo '<span class="pagination-ellipsis">...</span>';
                                            echo '<a href="' . e($buildPageUrl($totalPages)) . '" class="pagination-btn">' . $totalPages . '</a>';
                                        }
                                    ?>

                                    <?php if ($page < $totalPages): ?>
                                        <a href="<?= e($buildPageUrl($page + 1)) ?>" class="pagination-btn" title="Berikutnya">&rsaquo;</a>
                                        <a href="<?= e($buildPageUrl($totalPages)) ?>" class="pagination-btn" title="Halaman Terakhir">&raquo;</a>
                                    <?php else: ?>
                                        <span class="pagination-btn disabled">&rsaquo;</span>
                                        <span class="pagination-btn disabled">&raquo;</span>
                                    <?php endif; ?>
                                </div>
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