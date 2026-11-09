<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('adminTu');



date_default_timezone_set('Asia/Jakarta');









$tingkatValid = [

    'X',

    'XI',

    'XII'

];









if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['tambah_kelas'])

) {



    $jurusanId = (int) (

        $_POST['jurusan_id'] ?? 0

    );



    $tingkat = trim(

        $_POST['tingkat'] ?? ''

    );



    $namaKelas = trim(

        $_POST['nama_kelas'] ?? ''

    );





    



    if (

        $jurusanId <= 0 ||

        $tingkat === '' ||

        $namaKelas === ''

    ) {



        flash(

            'error',

            'Semua data kelas wajib diisi.'

        );



        redirect('kelas.php');

    }





    if (

        !in_array(

            $tingkat,

            $tingkatValid,

            true

        )

    ) {



        flash(

            'error',

            'Tingkat kelas tidak valid.'

        );



        redirect('kelas.php');

    }





    if (

        mb_strlen($namaKelas) > 50

    ) {



        flash(

            'error',

            'Nama kelas maksimal 50 karakter.'

        );



        redirect('kelas.php');

    }





    



    $stmt = $pdo->prepare("

        SELECT

            id,

            kode_jurusan,

            nama_jurusan

        FROM jurusan

        WHERE id = ?

        LIMIT 1

    ");



    $stmt->execute([

        $jurusanId

    ]);



    $jurusan = $stmt->fetch();





    if (!$jurusan) {



        flash(

            'error',

            'Jurusan tidak ditemukan.'

        );



        redirect('kelas.php');

    }





    



    $stmt = $pdo->prepare("

        SELECT id

        FROM kelas

        WHERE nama_kelas = ?

        LIMIT 1

    ");



    $stmt->execute([

        $namaKelas

    ]);





    if ($stmt->fetch()) {



        flash(

            'error',

            'Nama kelas sudah digunakan.'

        );



        redirect('kelas.php');

    }





    



    try {



        $pdo->beginTransaction();





        $stmt = $pdo->prepare("

            INSERT INTO kelas

            (

                jurusan_id,

                tingkat,

                nama_kelas

            )

            VALUES

            (

                ?,

                ?,

                ?

            )

        ");



        $stmt->execute([

            $jurusanId,

            $tingkat,

            $namaKelas

        ]);





        $kelasId = (int) $pdo->lastInsertId();





        



        $dataSesudah = [

            'id' => $kelasId,

            'jurusan_id' => $jurusanId,

            'tingkat' => $tingkat,

            'nama_kelas' => $namaKelas

        ];





        $stmtAudit = $pdo->prepare("

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

            VALUES

            (

                ?,

                'kelas',

                ?,

                'INSERT',

                NULL,

                ?,

                ?

            )

        ");



        $stmtAudit->execute([

            currentUserId(),

            $kelasId,

            json_encode(

                $dataSesudah,

                JSON_UNESCAPED_UNICODE

            ),

            'Penambahan data kelas oleh Admin TU'

        ]);





        $pdo->commit();





        flash(

            'success',

            'Kelas berhasil ditambahkan.'

        );



    } catch (Throwable $e) {



        if (

            $pdo->inTransaction()

        ) {



            $pdo->rollBack();

        }





        flash(

            'error',

            'Kelas gagal ditambahkan. ' . $e->getMessage()

        );

    }





    redirect('kelas.php');

}









if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['hapus_kelas'])

) {



    $kelasId = (int) (

        $_POST['kelas_id'] ?? 0

    );





    if (

        $kelasId <= 0

    ) {



        flash(

            'error',

            'ID kelas tidak valid.'

        );



        redirect('kelas.php');

    }





    try {



        $pdo->beginTransaction();





        



        $stmt = $pdo->prepare("

            SELECT

                k.id,

                k.jurusan_id,

                k.tingkat,

                k.nama_kelas,

                j.kode_jurusan,

                j.nama_jurusan

            FROM kelas k

            INNER JOIN jurusan j

                ON j.id = k.jurusan_id

            WHERE k.id = ?

            LIMIT 1

        ");



        $stmt->execute([

            $kelasId

        ]);



        $kelasLama = $stmt->fetch();





        if (!$kelasLama) {



            throw new RuntimeException(

                'Data kelas tidak ditemukan.'

            );

        }





        



        $stmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM siswa

            WHERE kelas_id = ?

        ");



        $stmt->execute([

            $kelasId

        ]);



        $jumlahSiswa = (int) $stmt->fetchColumn();





        if ($jumlahSiswa > 0) {



            throw new RuntimeException(

                'Kelas tidak dapat dihapus karena masih memiliki ' .

                $jumlahSiswa .

                ' siswa.'

            );

        }





        



        $stmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM jadwal

            WHERE kelas_id = ?

        ");



        $stmt->execute([

            $kelasId

        ]);



        $jumlahJadwal = (int) $stmt->fetchColumn();





        if ($jumlahJadwal > 0) {



            throw new RuntimeException(

                'Kelas tidak dapat dihapus karena masih digunakan dalam ' .

                $jumlahJadwal .

                ' jadwal.'

            );

        }





        



        $dataSebelum = [

            'id' => (int) $kelasLama['id'],

            'jurusan_id' => (int) $kelasLama['jurusan_id'],

            'tingkat' => $kelasLama['tingkat'],

            'nama_kelas' => $kelasLama['nama_kelas'],

            'kode_jurusan' => $kelasLama['kode_jurusan'],

            'nama_jurusan' => $kelasLama['nama_jurusan']

        ];





        



        $stmt = $pdo->prepare("

            DELETE FROM kelas

            WHERE id = ?

        ");



        $stmt->execute([

            $kelasId

        ]);





        if (

            $stmt->rowCount() !== 1

        ) {



            throw new RuntimeException(

                'Data kelas gagal dihapus.'

            );

        }





        



        $stmtAudit = $pdo->prepare("

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

            VALUES

            (

                ?,

                'kelas',

                ?,

                'DELETE',

                ?,

                NULL,

                ?

            )

        ");



        $stmtAudit->execute([

            currentUserId(),

            $kelasId,

            json_encode(

                $dataSebelum,

                JSON_UNESCAPED_UNICODE

            ),

            'Penghapusan data kelas oleh Admin TU'

        ]);





        $pdo->commit();





        flash(

            'success',

            'Kelas berhasil dihapus.'

        );



    } catch (Throwable $e) {



        if (

            $pdo->inTransaction()

        ) {



            $pdo->rollBack();

        }





        flash(

            'error',

            $e->getMessage()

        );

    }





    redirect('kelas.php');

}









$stmt = $pdo->query("

    SELECT

        id,

        kode_jurusan,

        nama_jurusan

    FROM jurusan

    ORDER BY

        nama_jurusan ASC

");



$daftarJurusan = $stmt->fetchAll();









$stmt = $pdo->query("

    SELECT

        k.id,

        k.jurusan_id,

        k.tingkat,

        k.nama_kelas,

        j.kode_jurusan,

        j.nama_jurusan



    FROM kelas k



    INNER JOIN jurusan j

        ON j.id = k.jurusan_id



    ORDER BY

        j.nama_jurusan ASC,

        CASE k.tingkat

            WHEN 'X' THEN 1

            WHEN 'XI' THEN 2

            WHEN 'XII' THEN 3

            ELSE 4

        END,

        k.nama_kelas ASC

");



$daftarKelas = $stmt->fetchAll();









$success = flash('success');



$error = flash('error');









$activeMenu   = 'kelas';



$pageTitle    = 'Data Kelas';



$pageSubtitle = 'Kelola data kelas per jurusan';



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

        Data Kelas - Admin TU

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

                            Data Kelas

                        </span>



                    </div>





                    <h1 class="page-title">

                        Data Kelas

                    </h1>





                    <p class="page-description">

                        Kelola data kelas berdasarkan jurusan dan tingkat.

                    </p>



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

                                +

                            </span>



                            Tambah Kelas



                        </h2>



                    </div>





                    <div class="dashboard-card-body">





                        <?php if (

                            empty($daftarJurusan)

                        ): ?>





                            <div class="alert alert-warning">



                                Belum ada jurusan.



                                <a

                                    href="jurusan.php"

                                    style="

                                        font-weight:700;

                                        text-decoration:underline;

                                    "

                                >

                                    Tambahkan jurusan terlebih dahulu.

                                </a>



                            </div>





                        <?php else: ?>





                            <form

                                method="POST"

                                autocomplete="off"

                            >



                                <div class="form-grid">





                                    



                                    <div class="form-group">



                                        <label

                                            for="jurusan_id"

                                            class="form-label"

                                        >

                                            Jurusan

                                        </label>





                                        <select

                                            name="jurusan_id"

                                            id="jurusan_id"

                                            required

                                        >



                                            <option value="">

                                                -- Pilih Jurusan --

                                            </option>





                                            <?php foreach (

                                                $daftarJurusan

                                                as $jurusan

                                            ): ?>



                                                <option

                                                    value="<?= (int) $jurusan['id'] ?>"

                                                >



                                                    <?= e(

                                                        $jurusan[

                                                            'kode_jurusan'

                                                        ]

                                                    ) ?>



                                                    -



                                                    <?= e(

                                                        $jurusan[

                                                            'nama_jurusan'

                                                        ]

                                                    ) ?>



                                                </option>



                                            <?php endforeach; ?>





                                        </select>



                                    </div>





                                    



                                    <div class="form-group">



                                        <label

                                            for="tingkat"

                                            class="form-label"

                                        >

                                            Tingkat

                                        </label>





                                        <select

                                            name="tingkat"

                                            id="tingkat"

                                            required

                                        >



                                            <option value="">

                                                -- Pilih Tingkat --

                                            </option>



                                            <option value="X">

                                                X

                                            </option>



                                            <option value="XI">

                                                XI

                                            </option>



                                            <option value="XII">

                                                XII

                                            </option>



                                        </select>



                                    </div>





                                    



                                    <div class="form-group full">



                                        <label

                                            for="nama_kelas"

                                            class="form-label"

                                        >

                                            Nama Kelas

                                        </label>





                                        <input

                                            type="text"

                                            id="nama_kelas"

                                            name="nama_kelas"

                                            placeholder="Contoh: X TKJ 1"

                                            maxlength="50"

                                            required

                                        >



                                    </div>





                                </div>





                                <div class="form-actions">



                                    <button

                                        type="submit"

                                        name="tambah_kelas"

                                        class="btn btn-primary"

                                    >

                                        Tambah Kelas

                                    </button>



                                </div>





                            </form>





                        <?php endif; ?>





                    </div>



                </div>





                



                <div class="dashboard-card">





                    <div class="dashboard-card-header">





                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("kelas", "", 18) ?>

                            </span>



                            Daftar Kelas



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor:default;"

                        >



                            Total:

                            <?= count($daftarKelas) ?>

                            kelas



                        </div>





                    </div>





                    <div class="dashboard-card-body">





                        <?php if (

                            empty($daftarKelas)

                        ): ?>





                            <div class="empty-state">



                                <div class="empty-state-icon">

                                    <?= appIcon("kelas", "", 48) ?>

                                </div>





                                <h3>

                                    Belum Ada Kelas

                                </h3>





                                <p>

                                    Belum ada data kelas yang terdaftar.

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

                                                Kode Jurusan

                                            </th>



                                            <th>

                                                Jurusan

                                            </th>



                                            <th>

                                                Tingkat

                                            </th>



                                            <th>

                                                Nama Kelas

                                            </th>



                                            <th>

                                                Aksi

                                            </th>



                                        </tr>



                                    </thead>





                                    <tbody>





                                        <?php foreach (

                                            $daftarKelas

                                            as $index => $kelas

                                        ): ?>





                                            <tr>





                                                <td>



                                                    <?= $index + 1 ?>



                                                </td>





                                                <td>



                                                    <?= e(

                                                        $kelas[

                                                            'kode_jurusan'

                                                        ]

                                                    ) ?>



                                                </td>





                                                <td

                                                    class="schedule-subject"

                                                >



                                                    <?= e(

                                                        $kelas[

                                                            'nama_jurusan'

                                                        ]

                                                    ) ?>



                                                </td>





                                                <td>



                                                    <?= e(

                                                        $kelas[

                                                            'tingkat'

                                                        ]

                                                    ) ?>



                                                </td>





                                                <td>



                                                    <?= e(

                                                        $kelas[

                                                            'nama_kelas'

                                                        ]

                                                    ) ?>



                                                </td>





                                                <td>





                                                    <form

                                                        method="POST"

                                                        style="display:inline;"

                                                        onsubmit="return confirm('Hapus kelas <?= e(

                                                            $kelas['nama_kelas']

                                                        ) ?>?')"

                                                    >



                                                        <input

                                                            type="hidden"

                                                            name="kelas_id"

                                                            value="<?= (int) $kelas['id'] ?>"

                                                        >





                                                        <button

                                                            type="submit"

                                                            name="hapus_kelas"

                                                            class="btn btn-sm btn-outline"

                                                        >

                                                            Hapus

                                                        </button>



                                                    </form>





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