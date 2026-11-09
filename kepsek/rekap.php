<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('kepsek');







$tanggalMulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');

$tanggalSelesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');



$jenis = $_GET['jenis'] ?? 'guru';



if (!in_array($jenis, ['guru', 'siswa'], true)) {

    $jenis = 'guru';

}



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







$rekapGuru = [];



if ($jenis === 'guru') {



    $stmt = $pdo->prepare("

        SELECT

            g.id,

            g.nip,

            g.nama_lengkap,

            m.nama_mapel,

            COUNT(pg.id) AS total_presensi,

            SUM(CASE WHEN pg.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,

            SUM(CASE WHEN pg.status = 'Izin' THEN 1 ELSE 0 END) AS izin,

            SUM(CASE WHEN pg.status = 'Alfa' THEN 1 ELSE 0 END) AS alfa

        FROM guru g

        LEFT JOIN mapel m

            ON m.id = g.mapel_utama_id

        LEFT JOIN presensi_guru pg

            ON pg.guru_id = g.id

            AND pg.tanggal BETWEEN :tanggal_mulai AND :tanggal_selesai

        WHERE g.status = 'aktif'

        GROUP BY

            g.id,

            g.nip,

            g.nama_lengkap,

            m.nama_mapel

        ORDER BY g.nama_lengkap ASC

    ");



    $stmt->execute([

        ':tanggal_mulai' => $tanggalMulai,

        ':tanggal_selesai' => $tanggalSelesai

    ]);



    $rekapGuru = $stmt->fetchAll(PDO::FETCH_ASSOC);



    

    $actual = [];



    $stmtActual = $pdo->prepare("

        SELECT guru_id, tanggal

        FROM presensi_guru

        WHERE tanggal BETWEEN ? AND ?

    ");

    $stmtActual->execute([$tanggalMulai, $tanggalSelesai]);



    foreach ($stmtActual->fetchAll(PDO::FETCH_ASSOC) as $rowActual) {

        $actual[(int) $rowActual['guru_id'] . '|' . $rowActual['tanggal']] = true;

    }



    $stmtIzin = $pdo->prepare("

        SELECT guru_id, tanggal_mulai, tanggal_selesai

        FROM perizinan

        WHERE status = 'Disetujui'

          AND tanggal_mulai <= ?

          AND tanggal_selesai >= ?

    ");

    $stmtIzin->execute([$tanggalSelesai, $tanggalMulai]);



    $izinTambahan = [];



    foreach ($stmtIzin->fetchAll(PDO::FETCH_ASSOC) as $izin) {

        $mulai = new DateTime($izin['tanggal_mulai']);

        $selesai = new DateTime($izin['tanggal_selesai']);

        $batasMulai = new DateTime($tanggalMulai);

        $batasSelesai = new DateTime($tanggalSelesai);



        if ($mulai < $batasMulai) $mulai = clone $batasMulai;

        if ($selesai > $batasSelesai) $selesai = clone $batasSelesai;



        for ($tanggal = clone $mulai; $tanggal <= $selesai; $tanggal->modify('+1 day')) {

            if ((int) $tanggal->format('N') === 7) continue;



            $tanggalString = $tanggal->format('Y-m-d');

            $key = (int) $izin['guru_id'] . '|' . $tanggalString;



            if (isset($actual[$key])) continue;

            $izinTambahan[$key] = true;

        }

    }



    foreach ($rekapGuru as &$row) {

        $guruKey = (int) $row['id'];

        $jumlahIzin = 0;



        foreach ($izinTambahan as $key => $_) {

            if (str_starts_with($key, $guruKey . '|')) {

                $jumlahIzin++;

            }

        }



        $row['izin'] = (int) $row['izin'] + $jumlahIzin;

        $row['total_presensi'] = (int) $row['total_presensi'] + $jumlahIzin;

    }

    unset($row);

}







$rekapSiswa = [];



if ($jenis === 'siswa') {



    $stmt = $pdo->prepare("

        SELECT

            s.id,

            s.nis,

            s.nama_lengkap,

            k.nama_kelas,

            j.nama_jurusan,

            COUNT(ps.id) AS total_presensi,

            SUM(CASE WHEN ps.status = 'Hadir' THEN 1 ELSE 0 END) AS hadir,

            SUM(CASE WHEN ps.status = 'Izin' THEN 1 ELSE 0 END) AS izin,

            SUM(CASE WHEN ps.status = 'Sakit' THEN 1 ELSE 0 END) AS sakit,

            SUM(CASE WHEN ps.status = 'Alfa' THEN 1 ELSE 0 END) AS alfa

        FROM siswa s

        INNER JOIN kelas k

            ON k.id = s.kelas_id

        INNER JOIN jurusan j

            ON j.id = k.jurusan_id

        LEFT JOIN presensi_siswa ps

            ON ps.siswa_id = s.id

            AND ps.tanggal BETWEEN :tanggal_mulai AND :tanggal_selesai

        WHERE s.status = 'aktif'

        GROUP BY

            s.id,

            s.nis,

            s.nama_lengkap,

            k.nama_kelas,

            j.nama_jurusan

        ORDER BY

            k.nama_kelas ASC,

            s.nama_lengkap ASC

    ");



    $stmt->execute([

        ':tanggal_mulai' => $tanggalMulai,

        ':tanggal_selesai' => $tanggalSelesai

    ]);



    $rekapSiswa = $stmt->fetchAll();

}







$activeMenu   = 'rekap';

$pageTitle    = 'Rekap Presensi';

$pageSubtitle = 'Rekapitulasi kehadiran';



?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rekap Presensi - Kepala Sekolah</title>

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

                        Rekap Presensi

                    </h1>



                    <p class="page-description">

                        Rekapitulasi kehadiran untuk kebutuhan

                        monitoring sekolah.

                    </p>



                </div>



                

                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">

                            <span class="dashboard-card-title-icon"><?= appIcon("search", "", 18) ?></span>

                            Filter Rekap

                        </h2>



                    </div>



                    <div class="dashboard-card-body">



                        <form method="GET">



                            <div class="form-grid">



                                <div class="form-group">



                                    <label for="jenis" class="form-label">

                                        Jenis Rekap

                                    </label>



                                    <select id="jenis" name="jenis">



                                        <option

                                            value="guru"

                                            <?= $jenis === 'guru' ? 'selected' : '' ?>

                                        >

                                            Guru

                                        </option>



                                        <option

                                            value="siswa"

                                            <?= $jenis === 'siswa' ? 'selected' : '' ?>

                                        >

                                            Siswa

                                        </option>



                                    </select>



                                </div>



                                <div class="form-group">



                                    <label for="tanggal_mulai" class="form-label">

                                        Tanggal Mulai

                                    </label>



                                    <input

                                        type="date"

                                        id="tanggal_mulai"

                                        name="tanggal_mulai"

                                        value="<?= e($tanggalMulai) ?>"

                                        required

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

                                        required

                                    >



                                </div>



                            </div>



                            <div class="form-actions">



                                <button type="submit" class="btn btn-primary">

                                    Tampilkan Rekap

                                </button>

                                <?php if ($jenis === 'guru'): ?>

                                <a href="cetak-rekap.php?bulan=<?= e($bulan) ?>" target="_blank" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px;">

                                    <?= appIcon("file-text", "", 16) ?> Cetak Laporan (PDF / Print)

                                </a>

                                <?php endif; ?>



                            </div>



                        </form>



                    </div>



                </div>



                

                <?php if ($jenis === 'guru'): ?>



                    <div class="dashboard-card">



                        <div class="dashboard-card-header">



                            <h2 class="dashboard-card-title">



                                <span class="dashboard-card-title-icon"><?= appIcon("guru", "", 18) ?></span>



                                Rekap Presensi Guru



                            </h2>



                            <div class="dashboard-card-link" style="cursor: default;">

                                Total: <?= count($rekapGuru) ?> guru

                            </div>



                        </div>



                        <div class="dashboard-card-body">



                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>

                                            <th>No</th>

                                            <th>NIP</th>

                                            <th>Nama Guru</th>

                                            <th>Mapel Utama</th>

                                            <th>Total</th>

                                            <th>Hadir</th>

                                            <th>Izin</th>

                                            <th>Alfa</th>

                                            <th>% Hadir</th>

                                        </tr>



                                    </thead>



                                    <tbody>



                                    <?php if (empty($rekapGuru)): ?>



                                        <tr>



                                            <td

                                                colspan="9"

                                                style="text-align:center; padding: 30px; color: var(--text-500);"

                                            >

                                                Belum ada data presensi guru pada periode ini.

                                            </td>



                                        </tr>



                                    <?php else: ?>



                                        <?php foreach ($rekapGuru as $index => $row): ?>



                                            <?php



                                            $total = (int) $row['total_presensi'];

                                            $hadir = (int) $row['hadir'];



                                            $persentase = $total > 0

                                                ? round(($hadir / $total) * 100, 1)

                                                : 0;



                                            if ($persentase >= 90) {

                                                $persenClass = 'success';

                                            } elseif ($persentase >= 75) {

                                                $persenClass = 'info';

                                            } elseif ($persentase >= 50) {

                                                $persenClass = 'warning';

                                            } else {

                                                $persenClass = 'danger';

                                            }



                                            ?>



                                            <tr>



                                                <td>

                                                    <?= $index + 1 ?>

                                                </td>



                                                <td>

                                                    <?= e($row['nip'] ?: '-') ?>

                                                </td>



                                                <td class="schedule-subject">

                                                    <?= e($row['nama_lengkap']) ?>

                                                </td>



                                                <td>

                                                    <?= e($row['nama_mapel'] ?: '-') ?>

                                                </td>



                                                <td>

                                                    <?= $total ?>

                                                </td>



                                                <td>



                                                    <span class="status-badge success">

                                                        <?= $hadir ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge warning">

                                                        <?= (int) $row['izin'] ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge danger">

                                                        <?= (int) $row['alfa'] ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge <?= e($persenClass) ?>">

                                                        <?= $persentase ?>%

                                                    </span>



                                                </td>



                                            </tr>



                                        <?php endforeach; ?>



                                    <?php endif; ?>



                                    </tbody>



                                </table>



                            </div>



                        </div>



                    </div>



                

                <?php else: ?>



                    <div class="dashboard-card">



                        <div class="dashboard-card-header">



                            <h2 class="dashboard-card-title">



                                <span class="dashboard-card-title-icon"><?= appIcon("siswa", "", 18) ?></span>



                                Rekap Presensi Siswa



                            </h2>



                            <div class="dashboard-card-link" style="cursor: default;">

                                Total: <?= count($rekapSiswa) ?> siswa

                            </div>



                        </div>



                        <div class="dashboard-card-body">



                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>

                                            <th>No</th>

                                            <th>NIS</th>

                                            <th>Nama Siswa</th>

                                            <th>Kelas</th>

                                            <th>Jurusan</th>

                                            <th>Total</th>

                                            <th>Hadir</th>

                                            <th>Izin</th>

                                            <th>Sakit</th>

                                            <th>Alfa</th>

                                            <th>% Hadir</th>

                                        </tr>



                                    </thead>



                                    <tbody>



                                    <?php if (empty($rekapSiswa)): ?>



                                        <tr>



                                            <td

                                                colspan="11"

                                                style="text-align:center; padding: 30px; color: var(--text-500);"

                                            >

                                                Belum ada data presensi siswa pada periode ini.

                                            </td>



                                        </tr>



                                    <?php else: ?>



                                        <?php foreach ($rekapSiswa as $index => $row): ?>



                                            <?php



                                            $total = (int) $row['total_presensi'];

                                            $hadir = (int) $row['hadir'];



                                            $persentase = $total > 0

                                                ? round(($hadir / $total) * 100, 1)

                                                : 0;



                                            if ($persentase >= 90) {

                                                $persenClass = 'success';

                                            } elseif ($persentase >= 75) {

                                                $persenClass = 'info';

                                            } elseif ($persentase >= 50) {

                                                $persenClass = 'warning';

                                            } else {

                                                $persenClass = 'danger';

                                            }



                                            ?>



                                            <tr>



                                                <td>

                                                    <?= $index + 1 ?>

                                                </td>



                                                <td>

                                                    <?= e($row['nis']) ?>

                                                </td>



                                                <td class="schedule-subject">

                                                    <?= e($row['nama_lengkap']) ?>

                                                </td>



                                                <td>

                                                    <?= e($row['nama_kelas']) ?>

                                                </td>



                                                <td>

                                                    <?= e($row['nama_jurusan']) ?>

                                                </td>



                                                <td>

                                                    <?= $total ?>

                                                </td>



                                                <td>



                                                    <span class="status-badge success">

                                                        <?= $hadir ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge warning">

                                                        <?= (int) $row['izin'] ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge info">

                                                        <?= (int) $row['sakit'] ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge danger">

                                                        <?= (int) $row['alfa'] ?>

                                                    </span>



                                                </td>



                                                <td>



                                                    <span class="status-badge <?= e($persenClass) ?>">

                                                        <?= $persentase ?>%

                                                    </span>



                                                </td>



                                            </tr>



                                        <?php endforeach; ?>



                                    <?php endif; ?>



                                    </tbody>



                                </table>



                            </div>



                        </div>



                    </div>



                <?php endif; ?>



                

                <a href="dashboard.php" class="back-link">

                    ← Kembali ke Dashboard

                </a>



            </div>

        </main>



    </div>



</div>



</body>

</html>