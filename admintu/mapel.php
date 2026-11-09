<?php







require_once '../config/database.php';



require_once '../config/session.php';



require_once '../config/helpers.php';







requireRole('adminTu');







$pesan = '';



$error = '';















function validasiMapel(string $kode, string $nama): ?string



{



    if ($kode === '' || $nama === '') {



        return 'Kode dan nama mata pelajaran wajib diisi.';



    }







    if (strlen($kode) > 20) {



        return 'Kode mata pelajaran maksimal 20 karakter.';



    }







    if (strlen($nama) > 100) {



        return 'Nama mata pelajaran maksimal 100 karakter.';



    }







    return null;



}



















if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_mapel'])) {







    $kode = strtoupper(trim($_POST['kode_mapel'] ?? ''));



    $nama = trim($_POST['nama_mapel'] ?? '');







    $validasi = validasiMapel($kode, $nama);







    if ($validasi !== null) {







        $error = $validasi;







    } else {







        $cek = $pdo->prepare("



            SELECT id



            FROM mapel



            WHERE kode_mapel = ?



            LIMIT 1



        ");







        $cek->execute([$kode]);







        if ($cek->fetch()) {







            $error = 'Kode mata pelajaran sudah digunakan.';







        } else {







            try {







                $pdo->beginTransaction();







                







                $stmt = $pdo->prepare("



                    INSERT INTO mapel



                    (



                        kode_mapel,



                        nama_mapel



                    )



                    VALUES (?, ?)



                ");







                $stmt->execute([



                    $kode,



                    $nama



                ]);







                $mapelId = (int) $pdo->lastInsertId();







                







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



                    'id' => $mapelId,



                    'kode_mapel' => $kode,



                    'nama_mapel' => $nama



                ], JSON_UNESCAPED_UNICODE);







                $audit->execute([



                    currentUserId(),



                    'mapel',



                    $mapelId,



                    'INSERT',



                    null,



                    $dataSesudah,



                    'Penambahan mata pelajaran oleh Admin TU.'



                ]);







                $pdo->commit();







                $pesan = 'Mata pelajaran berhasil ditambahkan.';







            } catch (Throwable $e) {







                if ($pdo->inTransaction()) {



                    $pdo->rollBack();



                }







                $error = 'Mata pelajaran gagal ditambahkan.';



            }



        }



    }



}



















if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_mapel'])) {







    $id = (int) ($_POST['id'] ?? 0);



    $kode = strtoupper(trim($_POST['kode_mapel'] ?? ''));



    $nama = trim($_POST['nama_mapel'] ?? '');







    if ($id <= 0) {







        $error = 'ID mata pelajaran tidak valid.';







    } else {







        







        $stmtLama = $pdo->prepare("



            SELECT



                id,



                kode_mapel,



                nama_mapel



            FROM mapel



            WHERE id = ?



            LIMIT 1



        ");







        $stmtLama->execute([$id]);







        $dataLama = $stmtLama->fetch();







        if (!$dataLama) {







            $error = 'Mata pelajaran tidak ditemukan.';







        } else {







            $validasi = validasiMapel($kode, $nama);







            if ($validasi !== null) {







                $error = $validasi;







            } else {







                







                $cek = $pdo->prepare("



                    SELECT id



                    FROM mapel



                    WHERE kode_mapel = ?



                    AND id != ?



                    LIMIT 1



                ");







                $cek->execute([



                    $kode,



                    $id



                ]);







                if ($cek->fetch()) {







                    $error = 'Kode mata pelajaran sudah digunakan.';







                } else {







                    try {







                        $pdo->beginTransaction();







                        







                        $stmt = $pdo->prepare("



                            UPDATE mapel



                            SET



                                kode_mapel = ?,



                                nama_mapel = ?



                            WHERE id = ?



                        ");







                        $stmt->execute([



                            $kode,



                            $nama,



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



                            'kode_mapel' => $dataLama['kode_mapel'],



                            'nama_mapel' => $dataLama['nama_mapel']



                        ], JSON_UNESCAPED_UNICODE);







                        $dataSesudah = json_encode([



                            'id' => $id,



                            'kode_mapel' => $kode,



                            'nama_mapel' => $nama



                        ], JSON_UNESCAPED_UNICODE);







                        $audit->execute([



                            currentUserId(),



                            'mapel',



                            $id,



                            'UPDATE',



                            $dataSebelum,



                            $dataSesudah,



                            'Perubahan mata pelajaran oleh Admin TU.'



                        ]);







                        $pdo->commit();







                        $pesan = 'Mata pelajaran berhasil diperbarui.';







                    } catch (Throwable $e) {







                        if ($pdo->inTransaction()) {



                            $pdo->rollBack();



                        }







                        $error = 'Mata pelajaran gagal diperbarui.';



                    }



                }



            }



        }



    }



}



















if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_mapel'])) {







    $id = (int) ($_POST['id'] ?? 0);







    if ($id <= 0) {







        $error = 'ID mata pelajaran tidak valid.';







    } else {







        







        $stmtMapel = $pdo->prepare("



            SELECT



                id,



                kode_mapel,



                nama_mapel



            FROM mapel



            WHERE id = ?



            LIMIT 1



        ");







        $stmtMapel->execute([$id]);







        $dataMapel = $stmtMapel->fetch();







        if (!$dataMapel) {







            $error = 'Mata pelajaran tidak ditemukan.';







        } else {







            







            $cekGuru = $pdo->prepare("



                SELECT id



                FROM guru



                WHERE mapel_utama_id = ?



                LIMIT 1



            ");







            $cekGuru->execute([$id]);







            







            $cekJadwal = $pdo->prepare("



                SELECT id



                FROM jadwal



                WHERE mapel_id = ?



                LIMIT 1



            ");







            $cekJadwal->execute([$id]);







            if ($cekGuru->fetch()) {







                $error = 'Mapel tidak dapat dihapus karena masih digunakan sebagai mapel utama guru.';







            } elseif ($cekJadwal->fetch()) {







                $error = 'Mapel tidak dapat dihapus karena masih digunakan dalam jadwal.';







            } else {







                try {







                    $pdo->beginTransaction();







                    







                    $stmt = $pdo->prepare("



                        DELETE FROM mapel



                        WHERE id = ?



                    ");







                    $stmt->execute([$id]);







                    







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



                        'id' => $dataMapel['id'],



                        'kode_mapel' => $dataMapel['kode_mapel'],



                        'nama_mapel' => $dataMapel['nama_mapel']



                    ], JSON_UNESCAPED_UNICODE);







                    $audit->execute([



                        currentUserId(),



                        'mapel',



                        $id,



                        'DELETE',



                        $dataSebelum,



                        null,



                        'Penghapusan mata pelajaran oleh Admin TU.'



                    ]);







                    $pdo->commit();







                    $pesan = 'Mata pelajaran berhasil dihapus.';







                } catch (Throwable $e) {







                    if ($pdo->inTransaction()) {



                        $pdo->rollBack();



                    }







                    $error = 'Mata pelajaran gagal dihapus.';



                }



            }



        }



    }



}



















$stmt = $pdo->query("



    SELECT



        id,



        kode_mapel,



        nama_mapel,



        created_at



    FROM mapel



    ORDER BY nama_mapel



");







$mapelList = $stmt->fetchAll();



















$editMapel = null;







if (isset($_GET['edit'])) {







    $idEdit = (int) $_GET['edit'];







    if ($idEdit > 0) {







        $stmtEdit = $pdo->prepare("



            SELECT



                id,



                kode_mapel,



                nama_mapel



            FROM mapel



            WHERE id = ?



            LIMIT 1



        ");







        $stmtEdit->execute([$idEdit]);







        $editMapel = $stmtEdit->fetch();



    }



}



















$activeMenu   = 'mapel';



$pageTitle    = 'Mata Pelajaran';



$pageSubtitle = 'Kelola data mata pelajaran';







?>







<!DOCTYPE html>



<html lang="id">







<head>







    <meta charset="UTF-8">







    <meta



        name="viewport"



        content="width=device-width, initial-scale=1.0"



    >







    <title>Data Mata Pelajaran - Admin TU</title>







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



                            Mata Pelajaran



                        </span>







                    </div>







                    <h1 class="page-title">



                        Data Mata Pelajaran



                    </h1>







                    <p class="page-description">



                        Kelola mata pelajaran yang digunakan dalam sistem.



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



                                <?= $editMapel ? appIcon('edit', '', 16) : '+' ?>



                            </span>







                            <?= $editMapel



                                ? 'Edit Mata Pelajaran'



                                : 'Tambah Mata Pelajaran'



                            ?>







                        </h2>







                    </div>











                    <div class="dashboard-card-body">







                        <form method="POST">







                            <?php if ($editMapel): ?>







                                <input



                                    type="hidden"



                                    name="id"



                                    value="<?= e($editMapel['id']) ?>"



                                >







                            <?php endif; ?>











                            <div class="form-grid">











                                







                                <div class="form-group">







                                    <label



                                        for="kode_mapel"



                                        class="form-label"



                                    >



                                        Kode Mata Pelajaran



                                    </label>







                                    <input



                                        type="text"



                                        id="kode_mapel"



                                        name="kode_mapel"



                                        maxlength="20"



                                        required



                                        value="<?= e($editMapel['kode_mapel'] ?? '') ?>"



                                        placeholder="Contoh: BIN"



                                    >







                                </div>











                                







                                <div class="form-group">







                                    <label



                                        for="nama_mapel"



                                        class="form-label"



                                    >



                                        Nama Mata Pelajaran



                                    </label>







                                    <input



                                        type="text"



                                        id="nama_mapel"



                                        name="nama_mapel"



                                        maxlength="100"



                                        required



                                        value="<?= e($editMapel['nama_mapel'] ?? '') ?>"



                                        placeholder="Contoh: Bahasa Indonesia"



                                    >







                                </div>







                            </div>











                            <div class="form-actions">







                                <?php if ($editMapel): ?>







                                    <button



                                        type="submit"



                                        name="edit_mapel"



                                        class="btn btn-primary"



                                    >



                                        Simpan Perubahan



                                    </button>







                                    <a



                                        href="mapel.php"



                                        class="btn btn-outline"



                                    >



                                        Batal



                                    </a>







                                <?php else: ?>







                                    <button



                                        type="submit"



                                        name="tambah_mapel"



                                        class="btn btn-primary"



                                    >



                                        Tambah Mapel



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



                                <?= appIcon("mapel", "", 18) ?>



                            </span>







                            Daftar Mata Pelajaran







                        </h2>







                        <div



                            class="dashboard-card-link"



                            style="cursor: default;"



                        >



                            Total: <?= count($mapelList) ?> mapel



                        </div>







                    </div>











                    <div class="dashboard-card-body">







                        <?php if (!$mapelList): ?>







                            <div class="empty-state">







                                <div class="empty-state-icon">



                                    <?= appIcon("mapel", "", 48) ?>



                                </div>







                                <h3>



                                    Belum Ada Mapel



                                </h3>







                                <p>



                                    Belum ada mata pelajaran yang terdaftar.



                                </p>







                            </div>







                        <?php else: ?>







                            <div class="schedule-table-wrap">







                                <table class="schedule-table">







                                    <thead>







                                        <tr>







                                            <th>No</th>



                                            <th>Kode</th>



                                            <th>Nama Mata Pelajaran</th>



                                            <th>Aksi</th>







                                        </tr>







                                    </thead>







                                    <tbody>







                                    <?php foreach ($mapelList as $i => $mapel): ?>







                                        <tr>







                                            <td>



                                                <?= $i + 1 ?>



                                            </td>







                                            <td>







                                                <span class="status-badge info">



                                                    <?= e($mapel['kode_mapel']) ?>



                                                </span>







                                            </td>







                                            <td class="schedule-subject">



                                                <?= e($mapel['nama_mapel']) ?>



                                            </td>







                                            <td>







                                                <a



                                                    href="?edit=<?= e($mapel['id']) ?>"



                                                    class="btn btn-sm btn-outline"



                                                >



                                                    Edit



                                                </a>











                                                <form



                                                    method="POST"



                                                    style="display:inline;"



                                                    onsubmit="return confirm('Hapus mata pelajaran ini?')"



                                                >







                                                    <input



                                                        type="hidden"



                                                        name="id"



                                                        value="<?= e($mapel['id']) ?>"



                                                    >







                                                    <button



                                                        type="submit"



                                                        name="hapus_mapel"



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