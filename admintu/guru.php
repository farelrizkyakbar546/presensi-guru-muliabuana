<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('adminTu');



date_default_timezone_set('Asia/Jakarta');



$pesan = '';

$error = '';









$statusValid = [

    'aktif',

    'nonaktif'

];









if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['tambah_guru'])

) {



    $nama = trim(

        $_POST['nama_lengkap'] ?? ''

    );



    $nip = trim(

        $_POST['nip'] ?? ''

    );



    $username = trim(

        $_POST['username'] ?? ''

    );



    $password = $_POST['password'] ?? '';



    $mapelId = (int) (

        $_POST['mapel_utama_id'] ?? 0

    );



    $status = trim(

        $_POST['status'] ?? 'aktif'

    );





    



    if (

        $nama === '' ||

        $username === '' ||

        $password === '' ||

        $mapelId <= 0

    ) {



        $error =

            'Nama, username, password, dan mapel utama wajib diisi.';



    } elseif (

        mb_strlen($nama) > 100

    ) {



        $error =

            'Nama lengkap maksimal 100 karakter.';



    } elseif (

        mb_strlen($username) > 50

    ) {



        $error =

            'Username maksimal 50 karakter.';



    } elseif (

        $nip !== '' &&

        mb_strlen($nip) > 30

    ) {



        $error =

            'NIP / ID Guru maksimal 30 karakter.';



    } elseif (

        strlen($password) < 6

    ) {



        $error =

            'Password minimal 6 karakter.';



    } elseif (

        !in_array(

            $status,

            $statusValid,

            true

        )

    ) {



        $error =

            'Status guru tidak valid.';



    } else {



        try {



            $pdo->beginTransaction();





            



            $stmt = $pdo->prepare("

                SELECT id

                FROM users

                WHERE username = ?

                LIMIT 1

            ");



            $stmt->execute([

                $username

            ]);





            if ($stmt->fetch()) {



                throw new RuntimeException(

                    'Username sudah digunakan.'

                );

            }





            



            if ($nip !== '') {



                $stmt = $pdo->prepare("

                    SELECT id

                    FROM guru

                    WHERE nip = ?

                    LIMIT 1

                ");



                $stmt->execute([

                    $nip

                ]);





                if ($stmt->fetch()) {



                    throw new RuntimeException(

                        'NIP/ID Guru sudah digunakan.'

                    );

                }

            }





            



            $stmt = $pdo->prepare("

                SELECT id

                FROM mapel

                WHERE id = ?

                LIMIT 1

            ");



            $stmt->execute([

                $mapelId

            ]);





            if (!$stmt->fetch()) {



                throw new RuntimeException(

                    'Mata pelajaran tidak ditemukan.'

                );

            }





            



            $passwordHash = password_hash(

                $password,

                PASSWORD_DEFAULT

            );





            if ($passwordHash === false) {



                throw new RuntimeException(

                    'Password gagal diproses.'

                );

            }





            



            $stmtUser = $pdo->prepare("

                INSERT INTO users

                (

                    username,

                    password,

                    nama_lengkap,

                    role,

                    status

                )

                VALUES

                (

                    ?,

                    ?,

                    ?,

                    'guru',

                    ?

                )

            ");



            $stmtUser->execute([

                $username,

                $passwordHash,

                $nama,

                $status

            ]);





            $userId = (int) $pdo->lastInsertId();





            



            $stmtGuru = $pdo->prepare("

                INSERT INTO guru

                (

                    user_id,

                    nip,

                    nama_lengkap,

                    mapel_utama_id,

                    status

                )

                VALUES

                (

                    ?,

                    ?,

                    ?,

                    ?,

                    ?

                )

            ");



            $stmtGuru->execute([

                $userId,

                $nip !== '' ? $nip : null,

                $nama,

                $mapelId,

                $status

            ]);





            $pdo->commit();





            $pesan =

                'Guru dan akun login berhasil dibuat.';



        } catch (Throwable $e) {



            if (

                $pdo->inTransaction()

            ) {



                $pdo->rollBack();

            }





            $error =

                $e->getMessage();

        }

    }

}









if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['edit_guru'])

) {



    $guruId = (int) (

        $_POST['guru_id'] ?? 0

    );



    $nama = trim(

        $_POST['nama_lengkap'] ?? ''

    );



    $nip = trim(

        $_POST['nip'] ?? ''

    );



    $username = trim(

        $_POST['username'] ?? ''

    );



    $mapelId = (int) (

        $_POST['mapel_utama_id'] ?? 0

    );



    $status = trim(

        $_POST['status'] ?? 'aktif'

    );



    $passwordBaru =

        $_POST['password_baru'] ?? '';





    



    if (

        $guruId <= 0 ||

        $nama === '' ||

        $username === '' ||

        $mapelId <= 0

    ) {



        $error =

            'Data guru belum lengkap.';



    } elseif (

        mb_strlen($nama) > 100

    ) {



        $error =

            'Nama lengkap maksimal 100 karakter.';



    } elseif (

        mb_strlen($username) > 50

    ) {



        $error =

            'Username maksimal 50 karakter.';



    } elseif (

        $nip !== '' &&

        mb_strlen($nip) > 30

    ) {



        $error =

            'NIP / ID Guru maksimal 30 karakter.';



    } elseif (

        !in_array(

            $status,

            $statusValid,

            true

        )

    ) {



        $error =

            'Status guru tidak valid.';



    } elseif (

        $passwordBaru !== '' &&

        strlen($passwordBaru) < 6

    ) {



        $error =

            'Password baru minimal 6 karakter.';



    } else {



        try {



            $pdo->beginTransaction();





            



            $stmt = $pdo->prepare("

                SELECT

                    g.id,

                    g.user_id,

                    g.nip,

                    g.nama_lengkap,

                    g.mapel_utama_id,

                    g.status,

                    u.username,

                    u.role,

                    u.status AS user_status

                FROM guru g

                INNER JOIN users u

                    ON u.id = g.user_id

                WHERE g.id = ?

                LIMIT 1

            ");



            $stmt->execute([

                $guruId

            ]);



            $guruLama = $stmt->fetch();





            if (!$guruLama) {



                throw new RuntimeException(

                    'Data guru tidak ditemukan.'

                );

            }





            



            if (

                $guruLama['role'] !== 'guru'

            ) {



                throw new RuntimeException(

                    'Akun yang terhubung bukan akun Guru.'

                );

            }





            $userId = (int) $guruLama['user_id'];





            



            $stmt = $pdo->prepare("

                SELECT id

                FROM users

                WHERE username = ?

                  AND id <> ?

                LIMIT 1

            ");



            $stmt->execute([

                $username,

                $userId

            ]);





            if ($stmt->fetch()) {



                throw new RuntimeException(

                    'Username sudah digunakan akun lain.'

                );

            }





            



            if ($nip !== '') {



                $stmt = $pdo->prepare("

                    SELECT id

                    FROM guru

                    WHERE nip = ?

                      AND id <> ?

                    LIMIT 1

                ");



                $stmt->execute([

                    $nip,

                    $guruId

                ]);





                if ($stmt->fetch()) {



                    throw new RuntimeException(

                        'NIP/ID Guru sudah digunakan.'

                    );

                }

            }





            



            $stmt = $pdo->prepare("

                SELECT id

                FROM mapel

                WHERE id = ?

                LIMIT 1

            ");



            $stmt->execute([

                $mapelId

            ]);





            if (!$stmt->fetch()) {



                throw new RuntimeException(

                    'Mata pelajaran tidak ditemukan.'

                );

            }





            



            $stmt = $pdo->prepare("

                UPDATE users

                SET

                    username = ?,

                    nama_lengkap = ?,

                    status = ?

                WHERE id = ?

                  AND role = 'guru'

            ");



            $stmt->execute([

                $username,

                $nama,

                $status,

                $userId

            ]);





            if (

                $stmt->rowCount() < 0

            ) {



                throw new RuntimeException(

                    'Akun pengguna gagal diperbarui.'

                );

            }





            



            if (

                $passwordBaru !== ''

            ) {



                $hash = password_hash(

                    $passwordBaru,

                    PASSWORD_DEFAULT

                );





                if ($hash === false) {



                    throw new RuntimeException(

                        'Password baru gagal diproses.'

                    );

                }





                $stmt = $pdo->prepare("

                    UPDATE users

                    SET password = ?

                    WHERE id = ?

                      AND role = 'guru'

                ");



                $stmt->execute([

                    $hash,

                    $userId

                ]);

            }





            



            $stmt = $pdo->prepare("

                UPDATE guru

                SET

                    nip = ?,

                    nama_lengkap = ?,

                    mapel_utama_id = ?,

                    status = ?

                WHERE id = ?

                  AND user_id = ?

            ");



            $stmt->execute([

                $nip !== '' ? $nip : null,

                $nama,

                $mapelId,

                $status,

                $guruId,

                $userId

            ]);





            if (

                $stmt->rowCount() !== 1

            ) {



                throw new RuntimeException(

                    'Data guru gagal diperbarui.'

                );

            }





            



            $pdo->commit();





            $pesan =

                'Data guru berhasil diperbarui.';



        } catch (Throwable $e) {



            if (

                $pdo->inTransaction()

            ) {



                $pdo->rollBack();

            }





            $error =

                $e->getMessage();

        }

    }

}









$stmtMapel = $pdo->query("

    SELECT

        id,

        kode_mapel,

        nama_mapel

    FROM mapel

    ORDER BY nama_mapel

");



$mapelList = $stmtMapel->fetchAll();









$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
if (!in_array($filterStatus, ['', 'aktif', 'nonaktif'], true)) {
    $filterStatus = '';
}

$whereClauses = ['1=1'];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(g.nama_lengkap LIKE ? OR g.nip LIKE ? OR u.username LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filterStatus !== '') {
    $whereClauses[] = 'g.status = ?';
    $params[] = $filterStatus;
}

$whereSql = implode(' AND ', $whereClauses);

$sqlGuru = "
    SELECT
        g.id,
        g.user_id,
        g.nip,
        g.nama_lengkap,
        g.mapel_utama_id,
        g.status,
        u.username,
        m.kode_mapel,
        m.nama_mapel
    FROM guru g
    INNER JOIN users u ON u.id = g.user_id
    LEFT JOIN mapel m ON m.id = g.mapel_utama_id
    WHERE $whereSql
    ORDER BY g.nama_lengkap ASC
";

$stmtGuru = $pdo->prepare($sqlGuru);
$stmtGuru->execute($params);
$guruList = $stmtGuru->fetchAll();









$editGuru = null;





if (

    isset($_GET['edit'])

) {



    $id = (int) $_GET['edit'];





    if ($id > 0) {



        $stmt = $pdo->prepare("

            SELECT

                g.id,

                g.user_id,

                g.nip,

                g.nama_lengkap,

                g.mapel_utama_id,

                g.status,

                u.username

            FROM guru g

            INNER JOIN users u

                ON u.id = g.user_id

            WHERE g.id = ?

            LIMIT 1

        ");



        $stmt->execute([

            $id

        ]);



        $editGuru = $stmt->fetch();

    }

}









$activeMenu   = 'guru';



$pageTitle    = 'Data Guru';



$pageSubtitle = 'Kelola data guru & akun login';



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

        Data Guru - Admin TU

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

                            Data Guru

                        </span>



                    </div>





                    <h1 class="page-title">

                        Data Guru

                    </h1>





                    <p class="page-description">

                        Kelola data guru dan akun login Guru.

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



                                <?= $editGuru

                                    ? appIcon('edit', '', 16)

                                    : '+'

                                ?>



                            </span>





                            <?= $editGuru

                                ? 'Edit Data Guru'

                                : 'Tambah Guru'

                            ?>



                        </h2>



                    </div>





                    <div class="dashboard-card-body">





                        <form

                            method="POST"

                            autocomplete="off"

                        >





                            <?php if ($editGuru): ?>



                                <input

                                    type="hidden"

                                    name="guru_id"

                                    value="<?= (int) $editGuru['id'] ?>"

                                >



                            <?php endif; ?>





                            <div class="form-grid">





                                



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

                                        value="<?= e(

                                            $editGuru[

                                                'nama_lengkap'

                                            ] ?? ''

                                        ) ?>"

                                    >



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="nip"

                                        class="form-label"

                                    >

                                        NIP / ID Guru

                                    </label>





                                    <input

                                        type="text"

                                        id="nip"

                                        name="nip"

                                        maxlength="30"

                                        value="<?= e(

                                            $editGuru['nip']

                                                ?? ''

                                        ) ?>"

                                    >



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="mapel_utama_id"

                                        class="form-label"

                                    >

                                        Mata Pelajaran Utama

                                    </label>





                                    <select

                                        id="mapel_utama_id"

                                        name="mapel_utama_id"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Mapel --

                                        </option>





                                        <?php foreach (

                                            $mapelList

                                            as $mapel

                                        ): ?>



                                            <option

                                                value="<?= (int) $mapel['id'] ?>"

                                                <?= isset(

                                                    $editGuru[

                                                        'mapel_utama_id'

                                                    ]

                                                ) &&

                                                (int) $editGuru[

                                                    'mapel_utama_id'

                                                ] === (int) $mapel[

                                                    'id'

                                                ]

                                                    ? 'selected'

                                                    : ''

                                                ?>

                                            >



                                                <?= e(

                                                    $mapel[

                                                        'kode_mapel'

                                                    ]

                                                ) ?>



                                                -



                                                <?= e(

                                                    $mapel[

                                                        'nama_mapel'

                                                    ]

                                                ) ?>



                                            </option>



                                        <?php endforeach; ?>





                                    </select>



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="username"

                                        class="form-label"

                                    >

                                        Username

                                    </label>





                                    <input

                                        type="text"

                                        id="username"

                                        name="username"

                                        maxlength="50"

                                        required

                                        value="<?= e(

                                            $editGuru[

                                                'username'

                                            ] ?? ''

                                        ) ?>"

                                    >



                                </div>





                                



                                <?php if ($editGuru): ?>



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

                                            autocomplete="new-password"

                                            placeholder="Kosongkan jika tidak diganti"

                                        >



                                    </div>



                                <?php else: ?>



                                    <div class="form-group">



                                        <label

                                            for="password"

                                            class="form-label"

                                        >

                                            Password

                                        </label>





                                        <input

                                            type="password"

                                            id="password"

                                            name="password"

                                            minlength="6"

                                            required

                                            autocomplete="new-password"

                                        >



                                    </div>



                                <?php endif; ?>





                                



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

                                            <?= (

                                                !isset(

                                                    $editGuru[

                                                        'status'

                                                    ]

                                                ) ||

                                                $editGuru[

                                                    'status'

                                                ] === 'aktif'

                                            )

                                                ? 'selected'

                                                : ''

                                            ?>

                                        >

                                            Aktif

                                        </option>





                                        <option

                                            value="nonaktif"

                                            <?= (

                                                isset(

                                                    $editGuru[

                                                        'status'

                                                    ]

                                                ) &&

                                                $editGuru[

                                                    'status'

                                                ] === 'nonaktif'

                                            )

                                                ? 'selected'

                                                : ''

                                            ?>

                                        >

                                            Nonaktif

                                        </option>



                                    </select>



                                </div>





                            </div>





                            



                            <div class="form-actions">





                                <?php if ($editGuru): ?>



                                    <button

                                        type="submit"

                                        name="edit_guru"

                                        class="btn btn-primary"

                                    >

                                        Simpan Perubahan

                                    </button>





                                    <a

                                        href="guru.php"

                                        class="btn btn-outline"

                                    >

                                        Batal

                                    </a>





                                <?php else: ?>



                                    <button

                                        type="submit"

                                        name="tambah_guru"

                                        class="btn btn-primary"

                                    >

                                        Tambah Guru

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

                                <?= appIcon("guru", "", 18) ?>

                            </span>



                            Daftar Guru



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor:default;"

                        >



                            Total:

                            <?= count($guruList) ?>

                            guru



                        </div>



                    </div>





                    <div class="dashboard-card-body">

                        <!-- FILTER & SEARCH GURU -->
                        <form method="GET" class="filter-bar" style="margin-bottom: 20px;">
                            <div class="filter-bar-item" style="max-width: 320px;">
                                <label for="search" class="form-label">
                                    Cari Nama / NIP / Username
                                </label>
                                <input
                                    type="text"
                                    id="search"
                                    name="search"
                                    value="<?= e($search) ?>"
                                    placeholder="Ketik nama, NIP, atau username..."
                                >
                            </div>

                            <div class="filter-bar-item" style="max-width: 180px;">
                                <label for="filter_status" class="form-label">
                                    Status
                                </label>
                                <select id="filter_status" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                                </select>
                            </div>

                            <div class="filter-bar-actions">
                                <button type="submit" class="btn btn-primary">
                                    Cari
                                </button>
                                <?php if ($search !== '' || $filterStatus !== ''): ?>
                                    <a href="guru.php" class="btn btn-outline">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="schedule-table-wrap">





                            <table class="schedule-table">





                                <thead>



                                    <tr>



                                        <th>

                                            No

                                        </th>



                                        <th>

                                            Nama

                                        </th>



                                        <th>

                                            NIP / ID

                                        </th>



                                        <th>

                                            Mapel Utama

                                        </th>



                                        <th>

                                            Username

                                        </th>



                                        <th>

                                            Status

                                        </th>



                                        <th>

                                            Aksi

                                        </th>



                                    </tr>



                                </thead>





                                <tbody>





                                <?php if (!$guruList): ?>





                                    <tr>



                                        <td

                                            colspan="7"

                                            style="

                                                text-align:center;

                                                padding:30px;

                                                color:var(--text-500);

                                            "

                                        >



                                            Belum ada data guru.



                                        </td>



                                    </tr>





                                <?php else: ?>





                                    <?php foreach (

                                        $guruList

                                        as $i => $guru

                                    ): ?>





                                        <?php



                                        $badgeClass =

                                            $guru['status'] === 'aktif'

                                                ? 'success'

                                                : 'secondary';



                                        ?>





                                        <tr>





                                            <td>



                                                <?= $i + 1 ?>



                                            </td>





                                            <td

                                                class="schedule-subject"

                                            >



                                                <?= e(

                                                    $guru[

                                                        'nama_lengkap'

                                                    ]

                                                ) ?>



                                            </td>





                                            <td>



                                                <?= e(

                                                    $guru['nip'] ?: '-'

                                                ) ?>



                                            </td>





                                            <td>



                                                <?= e(

                                                    $guru[

                                                        'nama_mapel'

                                                    ] ?: '-'

                                                ) ?>



                                            </td>





                                            <td>



                                                <?= e(

                                                    $guru[

                                                        'username'

                                                    ]

                                                ) ?>



                                            </td>





                                            <td>





                                                <span

                                                    class="status-badge <?= e(

                                                        $badgeClass

                                                    ) ?>"

                                                >



                                                    <?= e(

                                                        $guru[

                                                            'status'

                                                        ] === 'aktif'

                                                            ? 'Aktif'

                                                            : 'Nonaktif'

                                                    ) ?>



                                                </span>





                                            </td>





                                            <td>





                                                <a

                                                    href="?edit=<?= (int) $guru['id'] ?>"

                                                    class="btn btn-sm btn-outline"

                                                >

                                                    Edit

                                                </a>



                                                <a

                                                    href="users.php?edit=<?= (int) $guru['user_id'] ?>"

                                                    class="btn btn-sm btn-outline"

                                                    title="Kelola Akun Login di Manajemen Akun"

                                                >

                                                    Akun

                                                </a>



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