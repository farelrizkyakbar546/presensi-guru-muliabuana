<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('adminTu');



$pesan = '';

$error = '';



$allowedRoles = ['adminTu', 'guru', 'kepsek'];

$allowedStatuses = ['aktif', 'nonaktif'];



$stmtMapel = $pdo->query("

    SELECT id, kode_mapel, nama_mapel

    FROM mapel

    ORDER BY nama_mapel ASC

");

$mapelList = $stmtMapel->fetchAll();



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_user'])) {



    $nama = trim($_POST['nama_lengkap'] ?? '');

    $username = trim($_POST['username'] ?? '');

    $password = $_POST['password'] ?? '';

    $role = $_POST['role'] ?? '';

    $status = $_POST['status'] ?? 'aktif';



    $nip = trim($_POST['nip'] ?? '');

    $mapelId = (int) ($_POST['mapel_utama_id'] ?? 0);



    if (

        $nama === '' ||

        $username === '' ||

        $password === '' ||

        !in_array($role, $allowedRoles, true)

    ) {



        $error = 'Data akun belum lengkap atau role tidak valid.';



    } elseif (strlen($nama) > 100) {



        $error = 'Nama lengkap maksimal 100 karakter.';



    } elseif (strlen($username) > 50) {



        $error = 'Username maksimal 50 karakter.';



    } elseif (strlen($password) < 6) {



        $error = 'Password minimal 6 karakter.';



    } elseif (!in_array($status, $allowedStatuses, true)) {



        $error = 'Status akun tidak valid.';



    } elseif ($role === 'guru' && $nip !== '' && strlen($nip) > 30) {



        $error = 'NIP maksimal 30 karakter.';



    } else {



        $cek = $pdo->prepare("

            SELECT id

            FROM users

            WHERE username = ?

            LIMIT 1

        ");



        $cek->execute([$username]);



        if ($cek->fetchColumn()) {



            $error = 'Username sudah digunakan.';



        } else {



            if ($role === 'guru' && $nip !== '') {

                $cekNip = $pdo->prepare("

                    SELECT id FROM guru WHERE nip = ? LIMIT 1

                ");

                $cekNip->execute([$nip]);

                if ($cekNip->fetchColumn()) {

                    $error = 'NIP / ID Guru sudah digunakan oleh guru lain.';

                }

            }



            if ($error === '') {



                try {



                    $pdo->beginTransaction();



                    $hash = password_hash($password, PASSWORD_DEFAULT);



                    $stmt = $pdo->prepare("

                        INSERT INTO users

                        (

                            username,

                            password,

                            nama_lengkap,

                            role,

                            status

                        )

                        VALUES (?, ?, ?, ?, ?)

                    ");



                    $stmt->execute([

                        $username,

                        $hash,

                        $nama,

                        $role,

                        $status

                    ]);



                    $newUserId = (int) $pdo->lastInsertId();



                    if ($role === 'guru') {



                        $finalNip = ($nip !== '') ? $nip : 'G' . str_pad($newUserId, 4, '0', STR_PAD_LEFT);

                        $finalMapelId = ($mapelId > 0) ? $mapelId : null;



                        $stmtGuru = $pdo->prepare("

                            INSERT INTO guru

                            (

                                user_id,

                                nip,

                                nama_lengkap,

                                mapel_utama_id,

                                status

                            )

                            VALUES (?, ?, ?, ?, ?)

                        ");



                        $stmtGuru->execute([

                            $newUserId,

                            $finalNip,

                            $nama,

                            $finalMapelId,

                            $status

                        ]);

                    }



                    $pdo->commit();



                    $pesan = 'Akun ' . ($role === 'guru' ? 'Guru' : ($role === 'adminTu' ? 'Admin TU' : 'Kepala Sekolah')) . ' berhasil dibuat.';



                } catch (Throwable $e) {



                    if ($pdo->inTransaction()) {

                        $pdo->rollBack();

                    }



                    $error = 'Gagal membuat akun: ' . $e->getMessage();

                }

            }

        }

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {



    $id = (int) ($_POST['id'] ?? 0);

    $nama = trim($_POST['nama_lengkap'] ?? '');

    $username = trim($_POST['username'] ?? '');

    $role = $_POST['role'] ?? '';

    $status = $_POST['status'] ?? 'aktif';

    $passwordBaru = $_POST['password_baru'] ?? '';



    $nip = trim($_POST['nip'] ?? '');

    $mapelId = (int) ($_POST['mapel_utama_id'] ?? 0);



    if (

        $id <= 0 ||

        $nama === '' ||

        $username === '' ||

        !in_array($role, $allowedRoles, true)

    ) {



        $error = 'Data akun belum lengkap atau role tidak valid.';



    } elseif (strlen($nama) > 100) {



        $error = 'Nama lengkap maksimal 100 karakter.';



    } elseif (strlen($username) > 50) {



        $error = 'Username maksimal 50 karakter.';



    } elseif (!in_array($status, $allowedStatuses, true)) {



        $error = 'Status akun tidak valid.';



    } elseif ($passwordBaru !== '' && strlen($passwordBaru) < 6) {



        $error = 'Password baru minimal 6 karakter.';



    } elseif ($role === 'guru' && $nip !== '' && strlen($nip) > 30) {



        $error = 'NIP maksimal 30 karakter.';



    } else {



        $stmtCurrent = $pdo->prepare("

            SELECT

                u.id,

                u.username,

                u.nama_lengkap,

                u.role,

                u.status,

                g.id AS guru_id,

                g.nip,

                g.mapel_utama_id

            FROM users u

            LEFT JOIN guru g ON g.user_id = u.id

            WHERE u.id = ?

            AND u.role IN ('adminTu', 'guru', 'kepsek')

            LIMIT 1

        ");



        $stmtCurrent->execute([$id]);



        $currentUser = $stmtCurrent->fetch();



        if (!$currentUser) {



            $error = 'Akun tidak ditemukan atau tidak dapat diedit.';



        } elseif ($id === (int) currentUserId()) {



            if ($role !== $currentUser['role']) {



                $error = 'Role akun yang sedang digunakan tidak dapat diubah.';



            } elseif ($status !== 'aktif') {



                $error = 'Akun yang sedang digunakan tidak dapat dinonaktifkan.';



            }

        }



        if ($error === '') {



            $cek = $pdo->prepare("

                SELECT id

                FROM users

                WHERE username = ?

                AND id != ?

                LIMIT 1

            ");



            $cek->execute([

                $username,

                $id

            ]);



            if ($cek->fetchColumn()) {



                $error = 'Username sudah digunakan akun lain.';

            }



            if ($error === '' && $role === 'guru' && $nip !== '') {

                $cekNip = $pdo->prepare("

                    SELECT id FROM guru WHERE nip = ? AND user_id != ? LIMIT 1

                ");

                $cekNip->execute([$nip, $id]);

                if ($cekNip->fetchColumn()) {

                    $error = 'NIP / ID Guru sudah digunakan guru lain.';

                }

            }

        }



        if ($error === '') {



            try {



                $pdo->beginTransaction();



                if ($passwordBaru !== '') {



                    $hash = password_hash(

                        $passwordBaru,

                        PASSWORD_DEFAULT

                    );



                    $stmt = $pdo->prepare("

                        UPDATE users

                        SET

                            username = ?,

                            nama_lengkap = ?,

                            role = ?,

                            status = ?,

                            password = ?

                        WHERE id = ?

                    ");



                    $stmt->execute([

                        $username,

                        $nama,

                        $role,

                        $status,

                        $hash,

                        $id

                    ]);



                } else {



                    $stmt = $pdo->prepare("

                        UPDATE users

                        SET

                            username = ?,

                            nama_lengkap = ?,

                            role = ?,

                            status = ?

                        WHERE id = ?

                    ");



                    $stmt->execute([

                        $username,

                        $nama,

                        $role,

                        $status,

                        $id

                    ]);

                }



                if ($role === 'guru') {



                    $finalNip = ($nip !== '') ? $nip : ($currentUser['nip'] ?? 'G' . str_pad($id, 4, '0', STR_PAD_LEFT));

                    $finalMapelId = ($mapelId > 0) ? $mapelId : ($currentUser['mapel_utama_id'] ?? null);



                    if ($currentUser['guru_id']) {



                        $stmtUpdateGuru = $pdo->prepare("

                            UPDATE guru

                            SET

                                nama_lengkap = ?,

                                nip = ?,

                                mapel_utama_id = ?,

                                status = ?

                            WHERE user_id = ?

                        ");



                        $stmtUpdateGuru->execute([

                            $nama,

                            $finalNip,

                            $finalMapelId,

                            $status,

                            $id

                        ]);



                    } else {



                        $stmtInsertGuru = $pdo->prepare("

                            INSERT INTO guru

                            (

                                user_id,

                                nip,

                                nama_lengkap,

                                mapel_utama_id,

                                status

                            )

                            VALUES (?, ?, ?, ?, ?)

                        ");



                        $stmtInsertGuru->execute([

                            $id,

                            $finalNip,

                            $nama,

                            $finalMapelId,

                            $status

                        ]);

                    }



                } elseif ($currentUser['guru_id']) {



                    $stmtNonaktifGuru = $pdo->prepare("

                        UPDATE guru SET status = 'nonaktif' WHERE user_id = ?

                    ");

                    $stmtNonaktifGuru->execute([$id]);

                }



                $pdo->commit();



                $pesan = 'Data akun berhasil diperbarui.';



            } catch (Throwable $e) {



                if ($pdo->inTransaction()) {

                    $pdo->rollBack();

                }



                $pesan = '';

                $error = 'Gagal memperbarui akun: ' . $e->getMessage();

            }

        }

    }

}



if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_status'])) {



    $id = (int) ($_POST['id'] ?? 0);

    $aksiStatus = $_POST['aksi_status'] ?? '';



    if ($aksiStatus === 'nonaktif') {



        if ($id > 0 && $id !== (int) currentUserId()) {



            try {

                $pdo->beginTransaction();



                $stmt = $pdo->prepare("

                    UPDATE users

                    SET status = 'nonaktif'

                    WHERE id = ?

                    AND role IN ('adminTu', 'guru', 'kepsek')

                ");



                $stmt->execute([$id]);



                $stmtGuru = $pdo->prepare("

                    UPDATE guru

                    SET status = 'nonaktif'

                    WHERE user_id = ?

                ");

                $stmtGuru->execute([$id]);



                $pdo->commit();



                if ($stmt->rowCount() > 0) {

                    $pesan = 'Akun berhasil dinonaktifkan.';

                } else {

                    $error = 'Akun tidak ditemukan atau sudah nonaktif.';

                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) $pdo->rollBack();

                $error = 'Gagal menonaktifkan akun.';

            }



        } elseif ($id === (int) currentUserId()) {

            $error = 'Akun yang sedang digunakan tidak dapat dinonaktifkan.';

        }



    } elseif ($aksiStatus === 'aktifkan') {



        if ($id > 0) {



            try {

                $pdo->beginTransaction();



                $stmt = $pdo->prepare("

                    UPDATE users

                    SET status = 'aktif'

                    WHERE id = ?

                    AND role IN ('adminTu', 'guru', 'kepsek')

                ");



                $stmt->execute([$id]);



                $stmtGuru = $pdo->prepare("

                    UPDATE guru

                    SET status = 'aktif'

                    WHERE user_id = ?

                ");

                $stmtGuru->execute([$id]);



                $pdo->commit();



                if ($stmt->rowCount() > 0) {

                    $pesan = 'Akun berhasil diaktifkan.';

                } else {

                    $error = 'Akun tidak ditemukan atau sudah aktif.';

                }

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) $pdo->rollBack();

                $error = 'Gagal mengaktifkan akun.';

            }

        }

    }

}



$filterRole = $_GET['role'] ?? '';

$search = trim($_GET['search'] ?? '');



$params = [];

$whereClauses = ["u.role IN ('adminTu', 'guru', 'kepsek')"];



if (in_array($filterRole, ['adminTu', 'guru', 'kepsek'], true)) {

    $whereClauses[] = "u.role = ?";

    $params[] = $filterRole;

}



if ($search !== '') {

    $whereClauses[] = "(u.nama_lengkap LIKE ? OR u.username LIKE ? OR g.nip LIKE ?)";

    $params[] = "%$search%";

    $params[] = "%$search%";

    $params[] = "%$search%";

}



$whereSql = implode(' AND ', $whereClauses);



$stmt = $pdo->prepare("

    SELECT

        u.id,

        u.username,

        u.nama_lengkap,

        u.role,

        u.status,

        g.id AS guru_id,

        g.nip,

        m.kode_mapel,

        m.nama_mapel

    FROM users u

    LEFT JOIN guru g ON g.user_id = u.id

    LEFT JOIN mapel m ON m.id = g.mapel_utama_id

    WHERE $whereSql

    ORDER BY

        CASE u.role

            WHEN 'adminTu' THEN 1

            WHEN 'kepsek' THEN 2

            WHEN 'guru' THEN 3

        END,

        u.nama_lengkap ASC

");



$stmt->execute($params);

$userList = $stmt->fetchAll();



$editUser = null;



if (isset($_GET['edit'])) {



    $id = (int) $_GET['edit'];



    $stmtEdit = $pdo->prepare("

        SELECT

            u.id,

            u.username,

            u.nama_lengkap,

            u.role,

            u.status,

            g.id AS guru_id,

            g.nip,

            g.mapel_utama_id

        FROM users u

        LEFT JOIN guru g ON g.user_id = u.id

        WHERE u.id = ?

        AND u.role IN ('adminTu', 'guru', 'kepsek')

        LIMIT 1

    ");



    $stmtEdit->execute([$id]);



    $editUser = $stmtEdit->fetch();



    if (!$editUser) {



        $error = 'Akun tidak ditemukan atau tidak dapat diedit.';

    }

}



$activeMenu   = 'users';

$pageTitle    = 'Manajemen Akun';

$pageSubtitle = 'Kelola akun Admin TU, Guru, dan Kepala Sekolah';

?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Manajemen Akun - Admin TU</title>



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



                        <span>Manajemen Akun</span>



                    </div>



                    <h1 class="page-title">

                        Manajemen Akun

                    </h1>



                    <p class="page-description">

                        Kelola akun pengguna sistem untuk Admin TU, Guru, dan Kepala Sekolah.

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

                                <?= $editUser ? appIcon('edit', '', 16) : '+' ?>

                            </span>



                            <?= $editUser ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna' ?>



                        </h2>



                    </div>



                    <div class="dashboard-card-body">



                        <form method="POST" id="formUser">



                            <?php if ($editUser): ?>



                                <input

                                    type="hidden"

                                    name="id"

                                    value="<?= e($editUser['id']) ?>"

                                >



                            <?php endif; ?>



                            <div class="form-grid">



                                <div class="form-group">



                                    <label

                                        for="nama_lengkap"

                                        class="form-label"

                                    >

                                        Nama Lengkap <span style="color:var(--danger-500, #ef4444);">*</span>

                                    </label>



                                    <input

                                        type="text"

                                        id="nama_lengkap"

                                        name="nama_lengkap"

                                        required

                                        maxlength="100"

                                        placeholder="Contoh: Budi Santoso, S.Pd."

                                        value="<?= e($editUser['nama_lengkap'] ?? '') ?>"

                                    >



                                </div>



                                <div class="form-group">



                                    <label

                                        for="username"

                                        class="form-label"

                                    >

                                        Username <span style="color:var(--danger-500, #ef4444);">*</span>

                                    </label>



                                    <input

                                        type="text"

                                        id="username"

                                        name="username"

                                        required

                                        maxlength="50"

                                        placeholder="Contoh: budi.santoso"

                                        value="<?= e($editUser['username'] ?? '') ?>"

                                    >



                                </div>



                                <div class="form-group">



                                    <label

                                        for="role"

                                        class="form-label"

                                    >

                                        Role / Peran <span style="color:var(--danger-500, #ef4444);">*</span>

                                    </label>



                                    <select

                                        id="role"

                                        name="role"

                                        required

                                        onchange="toggleGuruFields(this.value)"

                                    >



                                        <option value="">

                                            -- Pilih Role --

                                        </option>



                                        <option

                                            value="adminTu"

                                            <?= isset($editUser['role']) &&

                                                $editUser['role'] === 'adminTu'

                                                ? 'selected'

                                                : '' ?>

                                        >

                                            Admin TU

                                        </option>



                                        <option

                                            value="guru"

                                            <?= isset($editUser['role']) &&

                                                $editUser['role'] === 'guru'

                                                ? 'selected'

                                                : '' ?>

                                        >

                                            Guru

                                        </option>



                                        <option

                                            value="kepsek"

                                            <?= isset($editUser['role']) &&

                                                $editUser['role'] === 'kepsek'

                                                ? 'selected'

                                                : '' ?>

                                        >

                                            Kepala Sekolah

                                        </option>



                                    </select>



                                </div>



                                <?php if ($editUser): ?>



                                    <div class="form-group">



                                        <label

                                            for="password_baru"

                                            class="form-label"

                                        >

                                            Password Baru (opsional)

                                        </label>



                                        <input

                                            type="password"

                                            id="password_baru"

                                            name="password_baru"

                                            minlength="6"

                                            maxlength="255"

                                            autocomplete="new-password"

                                            placeholder="Kosongkan jika tidak ingin mengganti"

                                        >



                                    </div>



                                <?php else: ?>



                                    <div class="form-group">



                                        <label

                                            for="password"

                                            class="form-label"

                                        >

                                            Password <span style="color:var(--danger-500, #ef4444);">*</span>

                                        </label>



                                        <input

                                            type="password"

                                            id="password"

                                            name="password"

                                            required

                                            minlength="6"

                                            maxlength="255"

                                            autocomplete="new-password"

                                            placeholder="Minimal 6 karakter"

                                        >



                                    </div>



                                <?php endif; ?>



                                <div class="form-group">



                                    <label

                                        for="status"

                                        class="form-label"

                                    >

                                        Status Akun

                                    </label>



                                    <select

                                        id="status"

                                        name="status"

                                    >



                                        <option

                                            value="aktif"

                                            <?= !isset($editUser['status']) ||

                                                $editUser['status'] === 'aktif'

                                                ? 'selected'

                                                : '' ?>

                                        >

                                            Aktif

                                        </option>



                                        <option

                                            value="nonaktif"

                                            <?= isset($editUser['status']) &&

                                                $editUser['status'] === 'nonaktif'

                                                ? 'selected'

                                                : '' ?>

                                        >

                                            Nonaktif

                                        </option>



                                    </select>



                                </div>



                            </div>



                            

                            <?php

                            $isGuruRole = (isset($editUser['role']) && $editUser['role'] === 'guru');

                            ?>

                            <div

                                id="guruFields"

                                style="display: <?= $isGuruRole ? 'block' : 'none' ?>; margin-top: 15px; padding: 16px; background: rgba(0,0,0,0.02); border: 1px dashed var(--border-color, #e5e7eb); border-radius: 8px;"

                            >

                                <div style="font-weight: 600; margin-bottom: 12px; color: var(--text-700, #374151); font-size: 14px;">

                                    Informasi Profil Guru (Tabel Guru)

                                </div>



                                <div class="form-grid">



                                    <div class="form-group">

                                        <label for="nip" class="form-label">

                                            NIP / ID Guru

                                        </label>

                                        <input

                                            type="text"

                                            id="nip"

                                            name="nip"

                                            maxlength="30"

                                            placeholder="Opsional (otomatis digenerate jika kosong)"

                                            value="<?= e($editUser['nip'] ?? '') ?>"

                                        >

                                    </div>



                                    <div class="form-group">

                                        <label for="mapel_utama_id" class="form-label">

                                            Mata Pelajaran Pokok

                                        </label>

                                        <select id="mapel_utama_id" name="mapel_utama_id">

                                            <option value="">-- Pilih Mata Pelajaran --</option>

                                            <?php foreach ($mapelList as $m): ?>

                                                <option

                                                    value="<?= (int) $m['id'] ?>"

                                                    <?= (isset($editUser['mapel_utama_id']) && (int) $editUser['mapel_utama_id'] === (int) $m['id']) ? 'selected' : '' ?>

                                                >

                                                    <?= e($m['kode_mapel']) ?> - <?= e($m['nama_mapel']) ?>

                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </div>



                                </div>

                            </div>



                            <div class="form-actions" style="margin-top: 20px;">



                                <?php if ($editUser): ?>



                                    <button

                                        type="submit"

                                        name="edit_user"

                                        class="btn btn-primary"

                                    >

                                        Simpan Perubahan

                                    </button>



                                    <a

                                        href="users.php"

                                        class="btn btn-outline"

                                    >

                                        Batal

                                    </a>



                                <?php else: ?>



                                    <button

                                        type="submit"

                                        name="tambah_user"

                                        class="btn btn-primary"

                                    >

                                        Tambah Akun

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

                                <?= appIcon("account", "", 18) ?>

                            </span>



                            Daftar Akun Pengguna



                        </h2>



                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >

                            Total: <?= count($userList) ?> akun ditemukan

                        </div>



                    </div>



                    <div class="dashboard-card-body">



                        <form method="GET" class="filter-bar" style="margin-bottom: 20px;">



                            <div class="filter-bar-item">

                                <label for="filter_role" class="form-label">Filter Role</label>

                                <select id="filter_role" name="role" onchange="this.form.submit()">

                                    <option value="">Semua Role (Admin, Guru, Kepsek)</option>

                                    <option value="adminTu" <?= $filterRole === 'adminTu' ? 'selected' : '' ?>>Admin TU</option>

                                    <option value="guru" <?= $filterRole === 'guru' ? 'selected' : '' ?>>Guru</option>

                                    <option value="kepsek" <?= $filterRole === 'kepsek' ? 'selected' : '' ?>>Kepala Sekolah</option>

                                </select>

                            </div>



                            <div class="filter-bar-item" style="flex: 1;">

                                <label for="search" class="form-label">Pencarian</label>

                                <input

                                    type="text"

                                    id="search"

                                    name="search"

                                    placeholder="Cari nama, username, atau NIP guru..."

                                    value="<?= e($search) ?>"

                                >

                            </div>



                            <div class="filter-bar-actions">

                                <button type="submit" class="btn btn-primary">

                                    Cari

                                </button>

                                <?php if ($filterRole !== '' || $search !== ''): ?>

                                    <a href="users.php" class="btn btn-outline">

                                        Reset

                                    </a>

                                <?php endif; ?>

                            </div>



                        </form>



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>

                                        <th>Nama Lengkap</th>

                                        <th>Username</th>

                                        <th>Role / Jabatan</th>

                                        <th>Keterangan / Mapel</th>

                                        <th>Status</th>

                                        <th>Aksi</th>



                                    </tr>



                                </thead>



                                <tbody>



                                <?php if (!$userList): ?>



                                    <tr>



                                        <td

                                            colspan="7"

                                            style="text-align:center; padding: 30px; color: var(--text-500);"

                                        >

                                            Tidak ada akun yang sesuai kriteria pencarian.

                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($userList as $i => $user): ?>



                                        <?php



                                        $badgeClass = ($user['status'] === 'aktif')

                                            ? 'success'

                                            : 'secondary';



                                        if ($user['role'] === 'adminTu') {

                                            $roleLabel = 'Admin TU';

                                            $roleBadgeClass = 'info';

                                        } elseif ($user['role'] === 'guru') {

                                            $roleLabel = 'Guru';

                                            $roleBadgeClass = 'success';

                                        } else {

                                            $roleLabel = 'Kepala Sekolah';

                                            $roleBadgeClass = 'warning';

                                        }



                                        ?>



                                        <tr>



                                            <td>

                                                <?= $i + 1 ?>

                                            </td>



                                            <td class="schedule-subject">

                                                <strong><?= e($user['nama_lengkap']) ?></strong>

                                                <?php if ($user['role'] === 'guru' && !empty($user['nip'])): ?>

                                                    <div style="font-size: 12px; color: var(--text-500, #6b7280);">

                                                        NIP: <?= e($user['nip']) ?>

                                                    </div>

                                                <?php endif; ?>

                                            </td>



                                            <td>

                                                <code><?= e($user['username']) ?></code>

                                            </td>



                                            <td>



                                                <span

                                                    class="status-badge <?= e($roleBadgeClass) ?>"

                                                >

                                                    <?= e($roleLabel) ?>

                                                </span>



                                            </td>



                                            <td>

                                                <?php if ($user['role'] === 'guru'): ?>

                                                    <?= e($user['nama_mapel'] ?? '-') ?>

                                                <?php else: ?>

                                                    <span style="color: var(--text-400, #9ca3af);">-</span>

                                                <?php endif; ?>

                                            </td>



                                            <td>



                                                <span

                                                    class="status-badge <?= e($badgeClass) ?>"

                                                >

                                                    <?= e(ucfirst($user['status'])) ?>

                                                </span>



                                            </td>



                                            <td>



                                                <a

                                                    href="?edit=<?= e($user['id']) ?>"

                                                    class="btn btn-sm btn-outline"

                                                >

                                                    Edit

                                                </a>



                                                <?php if ((int) $user['id'] !== (int) currentUserId()): ?>



                                                    <?php if ($user['status'] === 'aktif'): ?>



                                                        <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Nonaktifkan akun <?= e(addslashes($user['nama_lengkap'])) ?>?')">

                                                            <input type="hidden" name="aksi_status" value="nonaktif">

                                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">

                                                            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--danger-600, #dc2626);">

                                                                Nonaktifkan

                                                            </button>

                                                        </form>



                                                    <?php else: ?>



                                                        <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirm('Aktifkan akun <?= e(addslashes($user['nama_lengkap'])) ?>?')">

                                                            <input type="hidden" name="aksi_status" value="aktifkan">

                                                            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">

                                                            <button type="submit" class="btn btn-sm btn-outline" style="color: var(--success-600, #16a34a);">

                                                                Aktifkan

                                                            </button>

                                                        </form>



                                                    <?php endif; ?>



                                                <?php endif; ?>



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



<script>

function toggleGuruFields(role) {

    const guruDiv = document.getElementById('guruFields');

    if (!guruDiv) return;

    if (role === 'guru') {

        guruDiv.style.display = 'block';

    } else {

        guruDiv.style.display = 'none';

    }

}

</script>



</body>



</html>