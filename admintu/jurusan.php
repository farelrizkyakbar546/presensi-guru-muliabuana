<?php



require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/helpers.php';



requireRole('adminTu');







if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $aksi = $_POST['aksi'] ?? 'tambah';



    

    if ($aksi === 'hapus') {



        $id = (int) ($_POST['id'] ?? 0);



        if ($id <= 0) {

            flash('error', 'ID jurusan tidak valid.');

            redirect('jurusan.php');

        }



        

        $stmt = $pdo->prepare("

            SELECT COUNT(*)

            FROM kelas

            WHERE jurusan_id = :id

        ");



        $stmt->execute(['id' => $id]);



        $jumlahKelas = (int) $stmt->fetchColumn();



        if ($jumlahKelas > 0) {

            flash('error', 'Jurusan tidak dapat dihapus karena masih digunakan oleh kelas.');

            redirect('jurusan.php');

        }



        $stmt = $pdo->prepare("

            DELETE FROM jurusan

            WHERE id = :id

        ");



        $stmt->execute(['id' => $id]);



        flash('success', 'Jurusan berhasil dihapus.');

        redirect('jurusan.php');

    }



    

    if ($aksi === 'tambah') {



        $kode = strtoupper(trim($_POST['kode_jurusan'] ?? ''));

        $nama = trim($_POST['nama_jurusan'] ?? '');



        if ($kode === '' || $nama === '') {



            flash('error', 'Kode dan nama jurusan wajib diisi.');

            redirect('jurusan.php');

        }



        

        $stmt = $pdo->prepare("

            SELECT id

            FROM jurusan

            WHERE kode_jurusan = :kode

            LIMIT 1

        ");



        $stmt->execute(['kode' => $kode]);



        if ($stmt->fetch()) {



            flash('error', 'Kode jurusan sudah digunakan.');

            redirect('jurusan.php');

        }



        

        $stmt = $pdo->prepare("

            INSERT INTO jurusan

            (kode_jurusan, nama_jurusan)

            VALUES

            (:kode, :nama)

        ");



        $stmt->execute([

            'kode' => $kode,

            'nama' => $nama

        ]);



        flash('success', 'Jurusan berhasil ditambahkan.');

        redirect('jurusan.php');

    }

}







$stmt = $pdo->query("

    SELECT id, kode_jurusan, nama_jurusan

    FROM jurusan

    ORDER BY nama_jurusan ASC

");



$jurusan = $stmt->fetchAll();







$success = flash('success');

$error = flash('error');







$activeMenu   = 'jurusan';

$pageTitle    = 'Data Jurusan';

$pageSubtitle = 'Kelola data jurusan sekolah';

?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Data Jurusan - Admin TU</title>



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



                        <span>Data Jurusan</span>



                    </div>





                    <h1 class="page-title">

                        Data Jurusan

                    </h1>





                    <p class="page-description">

                        Kelola data jurusan sekolah.

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



                            Tambah Jurusan



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form method="POST">



                            <div class="form-grid">





                                <div class="form-group">



                                    <label for="kode_jurusan" class="form-label">

                                        Kode Jurusan

                                    </label>



                                    <input

                                        type="text"

                                        id="kode_jurusan"

                                        name="kode_jurusan"

                                        placeholder="Contoh: TKJ"

                                        maxlength="20"

                                        required

                                    >



                                </div>





                                <div class="form-group">



                                    <label for="nama_jurusan" class="form-label">

                                        Nama Jurusan

                                    </label>



                                    <input

                                        type="text"

                                        id="nama_jurusan"

                                        name="nama_jurusan"

                                        placeholder="Contoh: Teknik Komputer dan Jaringan"

                                        maxlength="100"

                                        required

                                    >



                                </div>



                            </div>





                            <div class="form-actions">



                                <button

                                    type="submit"

                                    name="aksi"

                                    value="tambah"

                                    class="btn btn-primary"

                                >

                                    Tambah Jurusan

                                </button>



                            </div>



                        </form>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("jurusan", "", 18) ?>

                            </span>



                            Daftar Jurusan



                        </h2>





                        <div class="dashboard-card-link" style="cursor: default;">

                            Total: <?= count($jurusan) ?> jurusan

                        </div>



                    </div>





                    <div class="dashboard-card-body">





                        <?php if (empty($jurusan)): ?>





                            <div class="empty-state">



                                <div class="empty-state-icon">

                                    <?= appIcon("jurusan", "", 48) ?>

                                </div>



                                <h3>

                                    Belum Ada Jurusan

                                </h3>



                                <p>

                                    Belum ada data jurusan yang terdaftar.

                                </p>



                            </div>





                        <?php else: ?>





                            <div class="schedule-table-wrap">



                                <table class="schedule-table">



                                    <thead>



                                        <tr>



                                            <th>No</th>

                                            <th>Kode</th>

                                            <th>Nama Jurusan</th>

                                            <th>Aksi</th>



                                        </tr>



                                    </thead>





                                    <tbody>



                                        <?php foreach ($jurusan as $index => $data): ?>



                                            <tr>



                                                <td>

                                                    <?= $index + 1 ?>

                                                </td>



                                                <td>

                                                    <span class="status-badge info">

                                                        <?= e($data['kode_jurusan']) ?>

                                                    </span>

                                                </td>



                                                <td class="schedule-subject">

                                                    <?= e($data['nama_jurusan']) ?>

                                                </td>



                                                <td>



                                                    <form method="POST" action="jurusan.php" style="display:inline;" onsubmit="return confirm('Hapus jurusan ini?')">

                                                        <input type="hidden" name="aksi" value="hapus">

                                                        <input type="hidden" name="id" value="<?= (int) $data['id'] ?>">

                                                        <button type="submit" class="btn btn-sm btn-outline">

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





                



                <a href="dashboard.php" class="back-link">

                    ← Kembali ke Dashboard

                </a>





            </div>



        </main>



    </div>



</div>



</body>



</html>