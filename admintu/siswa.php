<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('adminTu');



$pesan = '';

$error = '';







function validasiDataSiswa(PDO $pdo, string $nis, string $nama, int $kelasId, string $jenisKelamin, string $status): ?string

{

    if ($nis === '' || $nama === '' || $kelasId <= 0 || $jenisKelamin === '') {

        return 'Semua field wajib diisi.';

    }



    if (strlen($nis) > 30) {

        return 'NIS maksimal 30 karakter.';

    }



    if (strlen($nama) > 100) {

        return 'Nama lengkap maksimal 100 karakter.';

    }



    if (!in_array($jenisKelamin, ['L', 'P'], true)) {

        return 'Jenis kelamin tidak valid.';

    }



    if (!in_array($status, ['aktif', 'nonaktif'], true)) {

        return 'Status siswa tidak valid.';

    }



    



    $stmtKelas = $pdo->prepare("

        SELECT id

        FROM kelas

        WHERE id = ?

        LIMIT 1

    ");



    $stmtKelas->execute([$kelasId]);



    if (!$stmtKelas->fetch()) {

        return 'Kelas yang dipilih tidak ditemukan.';

    }



    return null;

}









if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_siswa'])) {



    $nis = trim($_POST['nis'] ?? '');

    $nama = trim($_POST['nama_lengkap'] ?? '');

    $kelasId = (int) ($_POST['kelas_id'] ?? 0);

    $jenisKelamin = $_POST['jenis_kelamin'] ?? '';

    $status = $_POST['status'] ?? 'aktif';



    $validasi = validasiDataSiswa(

        $pdo,

        $nis,

        $nama,

        $kelasId,

        $jenisKelamin,

        $status

    );



    if ($validasi !== null) {



        $error = $validasi;



    } else {



        



        $cek = $pdo->prepare("

            SELECT id

            FROM siswa

            WHERE nis = ?

            LIMIT 1

        ");



        $cek->execute([$nis]);



        if ($cek->fetch()) {



            $error = 'NIS tersebut sudah digunakan.';



        } else {



            try {



                $pdo->beginTransaction();



                



                $stmt = $pdo->prepare("

                    INSERT INTO siswa

                    (

                        nis,

                        nama_lengkap,

                        kelas_id,

                        jenis_kelamin,

                        status

                    )

                    VALUES (?, ?, ?, ?, ?)

                ");



                $stmt->execute([

                    $nis,

                    $nama,

                    $kelasId,

                    $jenisKelamin,

                    $status

                ]);



                $siswaId = (int) $pdo->lastInsertId();



                



                $audit = $pdo->prepare("

                    INSERT INTO audit_trail

                    (

                        user_id,

                        tabel,

                        record_id,

                        aksi,

                        data_sebelum,

                        data_sesudah,

                        alasan

                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?)

                ");



                $dataSesudah = json_encode([

                    'id' => $siswaId,

                    'nis' => $nis,

                    'nama_lengkap' => $nama,

                    'kelas_id' => $kelasId,

                    'jenis_kelamin' => $jenisKelamin,

                    'status' => $status

                ], JSON_UNESCAPED_UNICODE);



                $audit->execute([

                    currentUserId(),

                    'siswa',

                    $siswaId,

                    'INSERT',

                    null,

                    $dataSesudah,

                    'Penambahan data siswa oleh Admin TU.'

                ]);



                $pdo->commit();



                $pesan = 'Data siswa berhasil ditambahkan.';



            } catch (Throwable $e) {



                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }



                $error = 'Data siswa gagal ditambahkan.';

            }

        }

    }

}









if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_siswa'])) {



    $id = (int) ($_POST['id'] ?? 0);

    $nis = trim($_POST['nis'] ?? '');

    $nama = trim($_POST['nama_lengkap'] ?? '');

    $kelasId = (int) ($_POST['kelas_id'] ?? 0);

    $jenisKelamin = $_POST['jenis_kelamin'] ?? '';

    $status = $_POST['status'] ?? 'aktif';



    if ($id <= 0) {



        $error = 'ID siswa tidak valid.';



    } else {



        



        $stmtLama = $pdo->prepare("

            SELECT

                id,

                nis,

                nama_lengkap,

                kelas_id,

                jenis_kelamin,

                status

            FROM siswa

            WHERE id = ?

            LIMIT 1

        ");



        $stmtLama->execute([$id]);

        $dataLama = $stmtLama->fetch();



        if (!$dataLama) {



            $error = 'Data siswa tidak ditemukan.';



        } else {



            $validasi = validasiDataSiswa(

                $pdo,

                $nis,

                $nama,

                $kelasId,

                $jenisKelamin,

                $status

            );



            if ($validasi !== null) {



                $error = $validasi;



            } else {



                



                $cek = $pdo->prepare("

                    SELECT id

                    FROM siswa

                    WHERE nis = ?

                    AND id != ?

                    LIMIT 1

                ");



                $cek->execute([

                    $nis,

                    $id

                ]);



                if ($cek->fetch()) {



                    $error = 'NIS tersebut sudah digunakan siswa lain.';



                } else {



                    try {



                        $pdo->beginTransaction();



                        



                        $stmt = $pdo->prepare("

                            UPDATE siswa

                            SET

                                nis = ?,

                                nama_lengkap = ?,

                                kelas_id = ?,

                                jenis_kelamin = ?,

                                status = ?

                            WHERE id = ?

                        ");



                        $stmt->execute([

                            $nis,

                            $nama,

                            $kelasId,

                            $jenisKelamin,

                            $status,

                            $id

                        ]);



                        



                        $audit = $pdo->prepare("

                            INSERT INTO audit_trail

                            (

                                user_id,

                                tabel,

                                record_id,

                                aksi,

                                data_sebelum,

                                data_sesudah,

                                alasan

                            )

                            VALUES (?, ?, ?, ?, ?, ?, ?)

                        ");



                        $dataSebelum = json_encode([

                            'id' => $dataLama['id'],

                            'nis' => $dataLama['nis'],

                            'nama_lengkap' => $dataLama['nama_lengkap'],

                            'kelas_id' => $dataLama['kelas_id'],

                            'jenis_kelamin' => $dataLama['jenis_kelamin'],

                            'status' => $dataLama['status']

                        ], JSON_UNESCAPED_UNICODE);



                        $dataSesudah = json_encode([

                            'id' => $id,

                            'nis' => $nis,

                            'nama_lengkap' => $nama,

                            'kelas_id' => $kelasId,

                            'jenis_kelamin' => $jenisKelamin,

                            'status' => $status

                        ], JSON_UNESCAPED_UNICODE);



                        $audit->execute([

                            currentUserId(),

                            'siswa',

                            $id,

                            'UPDATE',

                            $dataSebelum,

                            $dataSesudah,

                            'Perubahan data siswa oleh Admin TU.'

                        ]);



                        $pdo->commit();



                        $pesan = 'Data siswa berhasil diperbarui.';



                    } catch (Throwable $e) {



                        if ($pdo->inTransaction()) {

                            $pdo->rollBack();

                        }



                        $error = 'Data siswa gagal diperbarui.';

                    }

                }

            }

        }

    }

}









if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ubah_status_siswa'])) {



    $id = (int) ($_POST['id'] ?? 0);

    $statusBaru = $_POST['status_baru'] ?? '';



    if ($id <= 0) {



        $error = 'ID siswa tidak valid.';



    } elseif (!in_array($statusBaru, ['aktif', 'nonaktif'], true)) {



        $error = 'Status siswa tidak valid.';



    } else {



        



        $stmtLama = $pdo->prepare("

            SELECT

                id,

                nis,

                nama_lengkap,

                kelas_id,

                jenis_kelamin,

                status

            FROM siswa

            WHERE id = ?

            LIMIT 1

        ");



        $stmtLama->execute([$id]);

        $dataLama = $stmtLama->fetch();



        if (!$dataLama) {



            $error = 'Data siswa tidak ditemukan.';



        } elseif ($dataLama['status'] === $statusBaru) {



            $pesan = $statusBaru === 'aktif'

                ? 'Siswa sudah dalam status aktif.'

                : 'Siswa sudah dalam status nonaktif.';



        } else {



            try {



                $pdo->beginTransaction();



                



                $stmt = $pdo->prepare("

                    UPDATE siswa

                    SET status = ?

                    WHERE id = ?

                ");



                $stmt->execute([

                    $statusBaru,

                    $id

                ]);



                



                $audit = $pdo->prepare("

                    INSERT INTO audit_trail

                    (

                        user_id,

                        tabel,

                        record_id,

                        aksi,

                        data_sebelum,

                        data_sesudah,

                        alasan

                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?)

                ");



                $dataSebelum = json_encode([

                    'id' => $dataLama['id'],

                    'nis' => $dataLama['nis'],

                    'nama_lengkap' => $dataLama['nama_lengkap'],

                    'kelas_id' => $dataLama['kelas_id'],

                    'jenis_kelamin' => $dataLama['jenis_kelamin'],

                    'status' => $dataLama['status']

                ], JSON_UNESCAPED_UNICODE);



                $dataSesudah = json_encode([

                    'id' => $dataLama['id'],

                    'nis' => $dataLama['nis'],

                    'nama_lengkap' => $dataLama['nama_lengkap'],

                    'kelas_id' => $dataLama['kelas_id'],

                    'jenis_kelamin' => $dataLama['jenis_kelamin'],

                    'status' => $statusBaru

                ], JSON_UNESCAPED_UNICODE);



                $alasan = $statusBaru === 'aktif'

                    ? 'Mengaktifkan kembali siswa oleh Admin TU.'

                    : 'Menonaktifkan siswa oleh Admin TU.';



                $audit->execute([

                    currentUserId(),

                    'siswa',

                    $id,

                    'UPDATE',

                    $dataSebelum,

                    $dataSesudah,

                    $alasan

                ]);



                $pdo->commit();



                $pesan = $statusBaru === 'aktif'

                    ? 'Siswa berhasil diaktifkan.'

                    : 'Siswa berhasil dinonaktifkan.';



            } catch (Throwable $e) {



                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }



                $error = 'Status siswa gagal diperbarui.';

            }

        }

    }

}









$stmtKelas = $pdo->query("

    SELECT

        k.id,

        k.nama_kelas,

        k.tingkat,

        j.nama_jurusan

    FROM kelas k

    INNER JOIN jurusan j

        ON j.id = k.jurusan_id

    ORDER BY

        j.nama_jurusan,

        k.tingkat,

        k.nama_kelas

");



$kelasList = $stmtKelas->fetchAll();









$filterKelas  = (int) ($_GET['kelas_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['search'] ?? '');
$perPage      = (int) ($_GET['per_page'] ?? 25);
if (!in_array($perPage, [25, 50, 100], true)) {
    $perPage = 25;
}
$page = max(1, (int) ($_GET['page'] ?? 1));

if (!in_array($filterStatus, ['', 'aktif', 'nonaktif'], true)) {
    $filterStatus = '';
}

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(s.nama_lengkap LIKE ? OR s.nis LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filterKelas > 0) {
    $whereClauses[] = 's.kelas_id = ?';
    $params[] = $filterKelas;
}

if ($filterStatus !== '') {
    $whereClauses[] = 's.status = ?';
    $params[] = $filterStatus;
}

$whereSql = implode(' AND ', $whereClauses);

// Hitung total records untuk pagination
$stmtCount = $pdo->prepare("
    SELECT COUNT(*) 
    FROM siswa s 
    INNER JOIN kelas k ON k.id = s.kelas_id 
    INNER JOIN jurusan j ON j.id = k.jurusan_id 
    WHERE $whereSql
");
$stmtCount->execute($params);
$totalSiswa = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalSiswa / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT
        s.id,
        s.nis,
        s.nama_lengkap,
        s.kelas_id,
        s.jenis_kelamin,
        s.status,
        s.created_at,
        s.updated_at,
        k.nama_kelas,
        k.tingkat,
        j.nama_jurusan
    FROM siswa s
    INNER JOIN kelas k ON k.id = s.kelas_id
    INNER JOIN jurusan j ON j.id = k.jurusan_id
    WHERE $whereSql
    ORDER BY
        j.nama_jurusan,
        k.tingkat,
        k.nama_kelas,
        s.nama_lengkap
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();









$editSiswa = null;



if (isset($_GET['edit'])) {



    $idEdit = (int) $_GET['edit'];



    if ($idEdit > 0) {



        $stmtEdit = $pdo->prepare("

            SELECT

                id,

                nis,

                nama_lengkap,

                kelas_id,

                jenis_kelamin,

                status

            FROM siswa

            WHERE id = ?

            LIMIT 1

        ");



        $stmtEdit->execute([$idEdit]);

        $editSiswa = $stmtEdit->fetch();

    }

}



// $totalSiswa sudah dihitung dari database (total keseluruhan filter)









$activeMenu   = 'siswa';

$pageTitle    = 'Data Siswa';

$pageSubtitle = 'Kelola data siswa per kelas';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>Data Siswa - Admin TU</title>



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

                            Data Siswa

                        </span>



                    </div>



                    <h1 class="page-title">

                        Data Siswa

                    </h1>



                    <p class="page-description">

                        Kelola data siswa berdasarkan jurusan dan kelas.

                    </p>



                </div>





                



                <?php if ($pesan): ?>



                    <div class="alert alert-success">

                        <?= e($pesan) ?>

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

                                <?= $editSiswa ? appIcon('edit', '', 16) : '+' ?>

                            </span>



                            <?= $editSiswa ? 'Edit Data Siswa' : 'Tambah Siswa' ?>



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form method="POST">



                            <?php if ($editSiswa): ?>



                                <input

                                    type="hidden"

                                    name="id"

                                    value="<?= e($editSiswa['id']) ?>"

                                >



                            <?php endif; ?>





                            <div class="form-grid">





                                



                                <div class="form-group">



                                    <label

                                        for="nis"

                                        class="form-label"

                                    >

                                        NIS

                                    </label>



                                    <input

                                        type="text"

                                        id="nis"

                                        name="nis"

                                        maxlength="30"

                                        required

                                        value="<?= e($editSiswa['nis'] ?? '') ?>"

                                    >



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="nama_lengkap"

                                        class="form-label"

                                    >

                                        Nama Lengkap

                                    </label>



                                    <input

                                        type="text"

                                        id="nama_lengkap"

                                        name="nama_lengkap"

                                        maxlength="100"

                                        required

                                        value="<?= e($editSiswa['nama_lengkap'] ?? '') ?>"

                                    >



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="kelas_id"

                                        class="form-label"

                                    >

                                        Kelas

                                    </label>



                                    <select

                                        id="kelas_id"

                                        name="kelas_id"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Kelas --

                                        </option>



                                        <?php foreach ($kelasList as $kelas): ?>



                                            <option

                                                value="<?= e($kelas['id']) ?>"

                                                <?= isset($editSiswa['kelas_id']) && $editSiswa['kelas_id'] == $kelas['id'] ? 'selected' : '' ?>

                                            >

                                                <?= e($kelas['nama_jurusan']) ?>

                                                -

                                                <?= e($kelas['tingkat']) ?>

                                                <?= e($kelas['nama_kelas']) ?>

                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="jenis_kelamin"

                                        class="form-label"

                                    >

                                        Jenis Kelamin

                                    </label>



                                    <select

                                        id="jenis_kelamin"

                                        name="jenis_kelamin"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih --

                                        </option>



                                        <option

                                            value="L"

                                            <?= isset($editSiswa['jenis_kelamin']) && $editSiswa['jenis_kelamin'] === 'L' ? 'selected' : '' ?>

                                        >

                                            Laki-laki

                                        </option>



                                        <option

                                            value="P"

                                            <?= isset($editSiswa['jenis_kelamin']) && $editSiswa['jenis_kelamin'] === 'P' ? 'selected' : '' ?>

                                        >

                                            Perempuan

                                        </option>



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



                                        <option

                                            value="aktif"

                                            <?= !isset($editSiswa['status']) || $editSiswa['status'] === 'aktif' ? 'selected' : '' ?>

                                        >

                                            Aktif

                                        </option>



                                        <option

                                            value="nonaktif"

                                            <?= isset($editSiswa['status']) && $editSiswa['status'] === 'nonaktif' ? 'selected' : '' ?>

                                        >

                                            Nonaktif

                                        </option>



                                    </select>



                                </div>



                            </div>





                            <div class="form-actions">



                                <?php if ($editSiswa): ?>



                                    <button

                                        type="submit"

                                        name="edit_siswa"

                                        class="btn btn-primary"

                                    >

                                        Simpan Perubahan

                                    </button>



                                    <a

                                        href="siswa.php"

                                        class="btn btn-outline"

                                    >

                                        Batal

                                    </a>



                                <?php else: ?>



                                    <button

                                        type="submit"

                                        name="tambah_siswa"

                                        class="btn btn-primary"

                                    >

                                        Tambah Siswa

                                    </button>



                                <?php endif; ?>



                            </div>



                        </form>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("siswa", "", 18) ?>

                            </span>



                            Daftar Siswa



                        </h2>



                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >

                            Total: <?= $totalSiswa ?> siswa

                        </div>



                    </div>





                    <div class="dashboard-card-body">





                        



                        <form
                            method="GET"
                            class="filter-bar"
                            style="margin-bottom: 20px;"
                        >
                            <div class="filter-bar-item" style="max-width: 260px;">
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

                            <div class="filter-bar-item">



                                <label

                                    for="filter_kelas"

                                    class="form-label"

                                >

                                    Filter Kelas

                                </label>



                                <select

                                    id="filter_kelas"

                                    name="kelas_id"

                                >



                                    <option value="">

                                        Semua Kelas

                                    </option>



                                    <?php foreach ($kelasList as $kelas): ?>



                                        <option

                                            value="<?= e($kelas['id']) ?>"

                                            <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>

                                        >

                                            <?= e($kelas['nama_jurusan']) ?>

                                            -

                                            <?= e($kelas['tingkat']) ?>

                                            <?= e($kelas['nama_kelas']) ?>

                                        </option>



                                    <?php endforeach; ?>



                                </select>



                            </div>





                            <div class="filter-bar-item">



                                <label

                                    for="filter_status"

                                    class="form-label"

                                >

                                    Filter Status

                                </label>



                                <select

                                    id="filter_status"

                                    name="status"

                                >



                                    <option value="">

                                        Semua Status

                                    </option>



                                    <option

                                        value="aktif"

                                        <?= $filterStatus === 'aktif' ? 'selected' : '' ?>

                                    >

                                        Aktif

                                    </option>



                                    <option

                                        value="nonaktif"

                                        <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>

                                    >

                                        Nonaktif

                                    </option>



                                </select>



                            </div>





                            <div class="filter-bar-item" style="max-width: 140px;">
                                <label for="per_page" class="form-label">
                                    Tampilkan
                                </label>
                                <select id="per_page" name="per_page" onchange="this.form.submit()">
                                    <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25 per hal</option>
                                    <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50 per hal</option>
                                    <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100 per hal</option>
                                </select>
                            </div>

                            <div class="filter-bar-actions">
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                >
                                    Filter / Cari
                                </button>
                                <a
                                    href="siswa.php"
                                    class="btn btn-outline"
                                >
                                    Reset
                                </a>
                            </div>



                        </form>





                        



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>

                                        <th>NIS</th>

                                        <th>Nama</th>

                                        <th>Jurusan</th>

                                        <th>Kelas</th>

                                        <th>JK</th>

                                        <th>Status</th>

                                        <th>Aksi</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$siswaList): ?>



                                    <tr>



                                        <td

                                            colspan="8"

                                            style="text-align:center; padding: 30px; color: var(--text-500);"

                                        >

                                            Belum ada data siswa.

                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($siswaList as $i => $siswa): ?>



                                        <?php



                                        $badgeClass = ($siswa['status'] === 'aktif')

                                            ? 'success'

                                            : 'secondary';



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $offset + $i + 1 ?>

                                            </td>



                                            <td>

                                                <?= e($siswa['nis']) ?>

                                            </td>



                                            <td class="schedule-subject">

                                                <?= e($siswa['nama_lengkap']) ?>

                                            </td>



                                            <td>

                                                <?= e($siswa['nama_jurusan']) ?>

                                            </td>



                                            <td>

                                                <?= e($siswa['tingkat']) ?>

                                                <?= e($siswa['nama_kelas']) ?>

                                            </td>



                                            <td>

                                                <?= $siswa['jenis_kelamin'] === 'L'

                                                    ? 'Laki-laki'

                                                    : 'Perempuan'

                                                ?>

                                            </td>



                                            <td>



                                                <span

                                                    class="status-badge <?= e($badgeClass) ?>"

                                                >

                                                    <?= e(ucfirst($siswa['status'])) ?>

                                                </span>



                                            </td>



                                            <td>



                                                <a

                                                    href="?edit=<?= e($siswa['id']) ?>"

                                                    class="btn btn-sm btn-outline"

                                                >

                                                    Edit

                                                </a>





                                                <?php if ($siswa['status'] === 'aktif'): ?>



                                                    <form

                                                        method="POST"

                                                        style="display:inline;"

                                                        onsubmit="return confirm('Nonaktifkan siswa ini?')"

                                                    >



                                                        <input

                                                            type="hidden"

                                                            name="id"

                                                            value="<?= e($siswa['id']) ?>"

                                                        >



                                                        <input

                                                            type="hidden"

                                                            name="status_baru"

                                                            value="nonaktif"

                                                        >



                                                        <button

                                                            type="submit"

                                                            name="ubah_status_siswa"

                                                            class="btn btn-sm btn-outline"

                                                        >

                                                            Nonaktifkan

                                                        </button>



                                                    </form>



                                                <?php else: ?>



                                                    <form

                                                        method="POST"

                                                        style="display:inline;"

                                                        onsubmit="return confirm('Aktifkan siswa ini?')"

                                                    >



                                                        <input

                                                            type="hidden"

                                                            name="id"

                                                            value="<?= e($siswa['id']) ?>"

                                                        >



                                                        <input

                                                            type="hidden"

                                                            name="status_baru"

                                                            value="aktif"

                                                        >



                                                        <button

                                                            type="submit"

                                                            name="ubah_status_siswa"

                                                            class="btn btn-sm btn-outline"

                                                        >

                                                            Aktifkan

                                                        </button>



                                                    </form>



                                                <?php endif; ?>



                                            </td>



                                        </tr>



                                    <?php endforeach; ?>



                                <?php endif; ?>



                                </tbody>



                            </table>



                        </div>

                        <!-- PAGINATION NAV -->
                        <?php if ($totalSiswa > 0): ?>
                            <?php
                                $startRecord = $offset + 1;
                                $endRecord = min($offset + $perPage, $totalSiswa);
                                
                                // Base query string builder
                                $queryParams = [
                                    'search'   => $search,
                                    'kelas_id' => $filterKelas ?: null,
                                    'status'   => $filterStatus ?: null,
                                    'per_page' => $perPage !== 25 ? $perPage : null,
                                ];
                                $buildPageUrl = function($p) use ($queryParams) {
                                    $params = array_filter(array_merge($queryParams, ['page' => $p]));
                                    return 'siswa.php?' . http_build_query($params);
                                };
                            ?>
                            <div class="pagination-container">
                                <div class="pagination-info">
                                    Menampilkan <strong><?= $startRecord ?></strong> - <strong><?= $endRecord ?></strong> dari <strong><?= number_format($totalSiswa, 0, ',', '.') ?></strong> siswa
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