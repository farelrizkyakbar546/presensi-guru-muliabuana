<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('adminTu');



$hariList = [

    'Senin',

    'Selasa',

    'Rabu',

    'Kamis',

    'Jumat',

    'Sabtu'

];









$stmt = $pdo->query("

    SELECT

        id,

        nama_lengkap,

        nip

    FROM guru

    WHERE status = 'aktif'

    ORDER BY nama_lengkap ASC

");



$guruList = $stmt->fetchAll();









$stmt = $pdo->query("

    SELECT

        jp.id,

        jp.guru_id,

        jp.hari,

        jp.keterangan,

        g.nama_lengkap,

        g.nip

    FROM jadwal_piket jp

    INNER JOIN guru g

        ON g.id = jp.guru_id

    ORDER BY

        FIELD(

            jp.hari,

            'Senin',

            'Selasa',

            'Rabu',

            'Kamis',

            'Jumat',

            'Sabtu'

        ),

        g.nama_lengkap ASC

");



$piketList = $stmt->fetchAll();









$editData = null;



if (isset($_GET['edit'])) {



    $id = (int) $_GET['edit'];



    if ($id > 0) {



        $stmt = $pdo->prepare("

            SELECT

                id,

                guru_id,

                hari,

                keterangan

            FROM jadwal_piket

            WHERE id = ?

            LIMIT 1

        ");



        $stmt->execute([$id]);



        $editData = $stmt->fetch();



        if (!$editData) {

            $editData = null;

        }

    }

}









$flashSuccess = flash('success');

$flashError   = flash('error');









$activeMenu   = 'jadwal-piket';

$pageTitle    = 'Jadwal Piket';

$pageSubtitle = 'Kelola guru piket per hari';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Jadwal Piket - Admin TU</title>



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

                            Jadwal Piket

                        </span>



                    </div>





                    <h1 class="page-title">

                        Jadwal Piket

                    </h1>





                    <p class="page-description">

                        Kelola guru yang bertugas piket berdasarkan hari.

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

                                <?= $editData ? appIcon('edit', '', 16) : '+' ?>

                            </span>



                            <?= $editData

                                ? 'Edit Jadwal Piket'

                                : 'Tambah Jadwal Piket'

                            ?>



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form

                            method="POST"

                            action="../proses/jadwal-piket.php"

                        >



                            <input

                                type="hidden"

                                name="aksi"

                                value="<?= $editData ? 'edit' : 'tambah' ?>"

                            >





                            <?php if ($editData): ?>



                                <input

                                    type="hidden"

                                    name="id"

                                    value="<?= (int) $editData['id'] ?>"

                                >



                            <?php endif; ?>





                            <div class="form-grid">





                                



                                <div class="form-group">



                                    <label

                                        for="guru_id"

                                        class="form-label"

                                    >

                                        Guru Piket

                                    </label>



                                    <select

                                        name="guru_id"

                                        id="guru_id"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Guru --

                                        </option>



                                        <?php foreach ($guruList as $guru): ?>



                                            <option

                                                value="<?= (int) $guru['id'] ?>"

                                                <?= (

                                                    $editData &&

                                                    (int) $editData['guru_id'] === (int) $guru['id']

                                                )

                                                    ? 'selected'

                                                    : ''

                                                ?>

                                            >



                                                <?= e($guru['nama_lengkap']) ?>



                                                <?php if (!empty($guru['nip'])): ?>



                                                    -

                                                    <?= e($guru['nip']) ?>



                                                <?php endif; ?>



                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="hari"

                                        class="form-label"

                                    >

                                        Hari

                                    </label>



                                    <select

                                        name="hari"

                                        id="hari"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Hari --

                                        </option>



                                        <?php foreach ($hariList as $hari): ?>



                                            <option

                                                value="<?= e($hari) ?>"

                                                <?= (

                                                    $editData &&

                                                    $editData['hari'] === $hari

                                                )

                                                    ? 'selected'

                                                    : ''

                                                ?>

                                            >

                                                <?= e($hari) ?>

                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                



                                <div class="form-group full">



                                    <label

                                        for="keterangan"

                                        class="form-label"

                                    >

                                        Keterangan

                                    </label>



                                    <textarea

                                        name="keterangan"

                                        id="keterangan"

                                        maxlength="255"

                                        placeholder="Contoh: Piket pagi / Piket ruang guru"

                                    ><?= $editData

                                        ? e($editData['keterangan'] ?? '')

                                        : ''

                                    ?></textarea>



                                </div>



                            </div>





                            <div class="form-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >



                                    <?= $editData

                                        ? 'Simpan Perubahan'

                                        : 'Tambah Jadwal Piket'

                                    ?>



                                </button>





                                <?php if ($editData): ?>



                                    <a

                                        href="jadwal-piket.php"

                                        class="btn btn-outline"

                                    >

                                        Batal

                                    </a>



                                <?php endif; ?>



                            </div>



                        </form>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("piket", "", 18) ?>

                            </span>



                            Daftar Jadwal Piket



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >

                            Total: <?= count($piketList) ?> jadwal

                        </div>



                    </div>





                    <div class="dashboard-card-body">



                        <div class="schedule-table-wrap">



                            <table class="schedule-table">



                                <thead>



                                    <tr>



                                        <th>No</th>

                                        <th>Hari</th>

                                        <th>Guru</th>

                                        <th>NIP</th>

                                        <th>Keterangan</th>

                                        <th>Aksi</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$piketList): ?>



                                    <tr>



                                        <td

                                            colspan="6"

                                            style="text-align:center; padding: 30px; color: var(--text-500);"

                                        >

                                            Belum ada jadwal piket.

                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($piketList as $index => $piket): ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>





                                            <td>



                                                <span class="day-badge">

                                                    <?= e($piket['hari']) ?>

                                                </span>



                                            </td>





                                            <td class="schedule-subject">

                                                <?= e($piket['nama_lengkap']) ?>

                                            </td>





                                            <td>

                                                <?= e($piket['nip'] ?: '-') ?>

                                            </td>





                                            <td>

                                                <?= e($piket['keterangan'] ?: '-') ?>

                                            </td>





                                            <td>



                                                <a

                                                    href="jadwal-piket.php?edit=<?= (int) $piket['id'] ?>"

                                                    class="btn btn-sm btn-outline"

                                                >

                                                    Edit

                                                </a>





                                                <form

                                                    method="POST"

                                                    action="../proses/jadwal-piket.php"

                                                    style="display:inline;"

                                                    onsubmit="return confirm('Yakin ingin menghapus jadwal piket ini?')"

                                                >



                                                    <input

                                                        type="hidden"

                                                        name="aksi"

                                                        value="hapus"

                                                    >



                                                    <input

                                                        type="hidden"

                                                        name="id"

                                                        value="<?= (int) $piket['id'] ?>"

                                                    >



                                                    <button

                                                        type="submit"

                                                        class="btn btn-sm btn-outline"

                                                    >

                                                        Hapus

                                                    </button>



                                                </form>



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