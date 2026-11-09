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



$filterHari = $_GET['hari'] ?? '';



if (!in_array($filterHari, $hariList, true)) {

    $filterHari = '';

}









$stmt = $pdo->query("

    SELECT

        g.id,

        g.nip,

        g.nama_lengkap,

        m.nama_mapel

    FROM guru g

    LEFT JOIN mapel m

        ON m.id = g.mapel_utama_id

    WHERE g.status = 'aktif'

    ORDER BY g.nama_lengkap ASC

");



$guruList = $stmt->fetchAll();









$stmt = $pdo->query("

    SELECT

        id,

        kode_mapel,

        nama_mapel

    FROM mapel

    ORDER BY nama_mapel ASC

");



$mapelList = $stmt->fetchAll();









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

        j.nama_jurusan ASC,

        k.tingkat ASC,

        k.nama_kelas ASC

");



$kelasList = $stmt->fetchAll();









$sql = "

    SELECT

        j.id,

        j.guru_id,

        j.kelas_id,

        j.mapel_id,

        j.hari,

        j.jam_mulai,

        j.jam_selesai,



        g.nama_lengkap AS nama_guru,

        g.nip,



        m.nama_mapel,

        m.kode_mapel,



        k.nama_kelas,

        k.tingkat,



        jur.nama_jurusan



    FROM jadwal j



    INNER JOIN guru g

        ON g.id = j.guru_id



    INNER JOIN mapel m

        ON m.id = j.mapel_id



    INNER JOIN kelas k

        ON k.id = j.kelas_id



    INNER JOIN jurusan jur

        ON jur.id = k.jurusan_id

";



$params = [];



if ($filterHari !== '') {



    $sql .= " WHERE j.hari = ?";

    $params[] = $filterHari;

}



$sql .= "

    ORDER BY

        FIELD(

            j.hari,

            'Senin',

            'Selasa',

            'Rabu',

            'Kamis',

            'Jumat',

            'Sabtu'

        ),

        j.jam_mulai ASC,

        k.tingkat ASC,

        k.nama_kelas ASC,

        g.nama_lengkap ASC

";



$stmt = $pdo->prepare($sql);

$stmt->execute($params);



$jadwalList = $stmt->fetchAll();









$editData = null;



if (isset($_GET['edit'])) {



    $editId = (int) $_GET['edit'];



    if ($editId > 0) {



        $stmtEdit = $pdo->prepare("

            SELECT

                id,

                guru_id,

                kelas_id,

                mapel_id,

                hari,

                jam_mulai,

                jam_selesai

            FROM jadwal

            WHERE id = ?

            LIMIT 1

        ");



        $stmtEdit->execute([$editId]);



        $editData = $stmtEdit->fetch();



        if (!$editData) {

            $editData = null;

        }

    }

}









$flashSuccess = flash('success');

$flashError   = flash('error');









$activeMenu   = 'jadwal';

$pageTitle    = 'Jadwal Mengajar';

$pageSubtitle = 'Kelola jadwal mengajar';



?>



<!DOCTYPE html>

<html lang="id">



<head>



    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Jadwal Mengajar - Admin TU</title>



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

                            Jadwal Mengajar

                        </span>



                    </div>





                    <h1 class="page-title">

                        Jadwal Mengajar

                    </h1>





                    <p class="page-description">

                        Kelola jadwal mengajar guru dan kelas.

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

                                ? 'Edit Jadwal'

                                : 'Tambah Jadwal'

                            ?>



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <form

                            method="POST"

                            action="../proses/jadwal.php"

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

                                        Guru

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

                                        for="mapel_id"

                                        class="form-label"

                                    >

                                        Mata Pelajaran

                                    </label>



                                    <select

                                        name="mapel_id"

                                        id="mapel_id"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Mata Pelajaran --

                                        </option>



                                        <?php foreach ($mapelList as $mapel): ?>



                                            <option

                                                value="<?= (int) $mapel['id'] ?>"

                                                <?= (

                                                    $editData &&

                                                    (int) $editData['mapel_id'] === (int) $mapel['id']

                                                )

                                                    ? 'selected'

                                                    : ''

                                                ?>

                                            >



                                                <?= e($mapel['kode_mapel']) ?>

                                                -

                                                <?= e($mapel['nama_mapel']) ?>



                                            </option>



                                        <?php endforeach; ?>



                                    </select>



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="kelas_id"

                                        class="form-label"

                                    >

                                        Kelas

                                    </label>



                                    <select

                                        name="kelas_id"

                                        id="kelas_id"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Kelas --

                                        </option>



                                        <?php foreach ($kelasList as $kelas): ?>



                                            <option

                                                value="<?= (int) $kelas['id'] ?>"

                                                <?= (

                                                    $editData &&

                                                    (int) $editData['kelas_id'] === (int) $kelas['id']

                                                )

                                                    ? 'selected'

                                                    : ''

                                                ?>

                                            >



                                                <?= e($kelas['tingkat']) ?>

                                                -

                                                <?= e($kelas['nama_kelas']) ?>



                                                (

                                                <?= e($kelas['nama_jurusan']) ?>

                                                )



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





                                



                                <div class="form-group">
                                    <label for="jam_mulai" class="form-label">
                                        Jam Mulai (Format 24 Jam)
                                    </label>
                                    <input
                                        type="text"
                                        name="jam_mulai"
                                        id="jam_mulai"
                                        placeholder="Contoh: 07:15 atau 13:00"
                                        pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$"
                                        title="Format 24 jam (00:00 - 23:59), contoh: 07:15 atau 13:00"
                                        maxlength="5"
                                        list="list_jam_options"
                                        autocomplete="off"
                                        required
                                        value="<?= $editData ? e(substr($editData['jam_mulai'], 0, 5)) : '' ?>"
                                    >
                                    <small style="display: block; color: #64748b; font-size: 11px; margin-top: 4px;">
                                        Gunakan format 24 jam (misal: <strong>07:15</strong> atau <strong>13:00</strong>)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="jam_selesai" class="form-label">
                                        Jam Selesai (Format 24 Jam)
                                    </label>
                                    <input
                                        type="text"
                                        name="jam_selesai"
                                        id="jam_selesai"
                                        placeholder="Contoh: 08:45 atau 14:30"
                                        pattern="^([01][0-9]|2[0-3]):[0-5][0-9]$"
                                        title="Format 24 jam (00:00 - 23:59), contoh: 08:45 atau 14:30"
                                        maxlength="5"
                                        list="list_jam_options"
                                        autocomplete="off"
                                        required
                                        value="<?= $editData ? e(substr($editData['jam_selesai'], 0, 5)) : '' ?>"
                                    >
                                    <small style="display: block; color: #64748b; font-size: 11px; margin-top: 4px;">
                                        Gunakan format 24 jam (misal: <strong>08:45</strong> atau <strong>14:30</strong>)
                                    </small>
                                </div>



                            </div>





                            <div class="form-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >



                                    <?= $editData

                                        ? 'Simpan Perubahan'

                                        : 'Tambah Jadwal'

                                    ?>



                                </button>





                                <?php if ($editData): ?>



                                    <a

                                        href="jadwal.php"

                                        class="btn btn-outline"

                                    >

                                        Batal Edit

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

                                <?= appIcon("jadwal", "", 18) ?>

                            </span>



                            Daftar Jadwal



                        </h2>





                        <div

                            class="dashboard-card-link"

                            style="cursor: default;"

                        >

                            Total: <?= count($jadwalList) ?> jadwal

                        </div>



                    </div>





                    <div class="dashboard-card-body">





                        



                        <form

                            method="GET"

                            class="filter-bar"

                            style="margin-bottom: 20px;"

                        >



                            <div class="filter-bar-item">



                                <label

                                    for="filter_hari"

                                    class="form-label"

                                >

                                    Filter Hari

                                </label>



                                <select

                                    name="hari"

                                    id="filter_hari"

                                >



                                    <option value="">

                                        Semua Hari

                                    </option>



                                    <?php foreach ($hariList as $hari): ?>



                                        <option

                                            value="<?= e($hari) ?>"

                                            <?= $filterHari === $hari

                                                ? 'selected'

                                                : ''

                                            ?>

                                        >

                                            <?= e($hari) ?>

                                        </option>



                                    <?php endforeach; ?>



                                </select>



                            </div>





                            <div class="filter-bar-actions">



                                <button

                                    type="submit"

                                    class="btn btn-primary"

                                >

                                    Filter

                                </button>



                                <a

                                    href="jadwal.php"

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

                                        <th>Hari</th>

                                        <th>Jam</th>

                                        <th>Guru</th>

                                        <th>Mata Pelajaran</th>

                                        <th>Kelas</th>

                                        <th>Aksi</th>



                                    </tr>



                                </thead>





                                <tbody>



                                <?php if (!$jadwalList): ?>



                                    <tr>



                                        <td

                                            colspan="7"

                                            style="text-align:center; padding: 30px; color: var(--text-500);"

                                        >

                                            Belum ada jadwal mengajar.

                                        </td>



                                    </tr>



                                <?php else: ?>



                                    <?php foreach ($jadwalList as $index => $jadwal): ?>



                                        <tr>



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>





                                            <td>



                                                <span class="day-badge">

                                                    <?= e($jadwal['hari']) ?>

                                                </span>



                                            </td>





                                            <td class="schedule-time">



                                                <?= e(substr($jadwal['jam_mulai'], 0, 5)) ?>



                                                -



                                                <?= e(substr($jadwal['jam_selesai'], 0, 5)) ?>



                                            </td>





                                            <td>



                                                <span class="schedule-subject">

                                                    <?= e($jadwal['nama_guru']) ?>

                                                </span>



                                                <?php if (!empty($jadwal['nip'])): ?>



                                                    <div class="schedule-code">

                                                        NIP:

                                                        <?= e($jadwal['nip']) ?>

                                                    </div>



                                                <?php endif; ?>



                                            </td>





                                            <td>



                                                <?= e($jadwal['nama_mapel']) ?>



                                                <div class="schedule-code">

                                                    <?= e($jadwal['kode_mapel']) ?>

                                                </div>



                                            </td>





                                            <td>



                                                <?= e($jadwal['tingkat']) ?>

                                                <?= e($jadwal['nama_kelas']) ?>



                                                <div class="schedule-code">

                                                    <?= e($jadwal['nama_jurusan']) ?>

                                                </div>



                                            </td>





                                            <td>



                                                <a

                                                    href="jadwal.php?edit=<?= (int) $jadwal['id'] ?>"

                                                    class="btn btn-sm btn-outline"

                                                >

                                                    Edit

                                                </a>





                                                <form

                                                    method="POST"

                                                    action="../proses/jadwal.php"

                                                    style="display:inline;"

                                                    onsubmit="return confirm('Yakin ingin menghapus jadwal ini?')"

                                                >



                                                    <input

                                                        type="hidden"

                                                        name="aksi"

                                                        value="hapus"

                                                    >



                                                    <input

                                                        type="hidden"

                                                        name="id"

                                                        value="<?= (int) $jadwal['id'] ?>"

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



<datalist id="list_jam_options">
    <option value="07:00">07:00</option>
    <option value="07:15">07:15 (Jam ke-1)</option>
    <option value="07:30">07:30</option>
    <option value="07:45">07:45</option>
    <option value="08:00">08:00 (Jam ke-2)</option>
    <option value="08:15">08:15</option>
    <option value="08:30">08:30</option>
    <option value="08:45">08:45 (Jam ke-3)</option>
    <option value="09:00">09:00</option>
    <option value="09:15">09:15</option>
    <option value="09:30">09:30 (Istirahat)</option>
    <option value="09:45">09:45 (Jam ke-4)</option>
    <option value="10:00">10:00</option>
    <option value="10:15">10:15</option>
    <option value="10:30">10:30 (Jam ke-5)</option>
    <option value="10:45">10:45</option>
    <option value="11:00">11:00</option>
    <option value="11:15">11:15 (Jam ke-6)</option>
    <option value="11:30">11:30</option>
    <option value="11:45">11:45</option>
    <option value="12:00">12:00 (Dzuhur)</option>
    <option value="12:30">12:30</option>
    <option value="13:00">13:00 (1 Siang / Jam ke-7)</option>
    <option value="13:15">13:15</option>
    <option value="13:30">13:30</option>
    <option value="13:45">13:45 (Jam ke-8)</option>
    <option value="14:00">14:00 (2 Siang)</option>
    <option value="14:15">14:15</option>
    <option value="14:30">14:30 (Jam ke-9)</option>
    <option value="14:45">14:45</option>
    <option value="15:00">15:00 (3 Sore)</option>
    <option value="15:15">15:15</option>
    <option value="15:30">15:30</option>
    <option value="15:45">15:45</option>
    <option value="16:00">16:00 (4 Sore)</option>
    <option value="16:30">16:30</option>
    <option value="17:00">17:00 (5 Sore)</option>
</datalist>

<script>
// Auto-format time input to 24-hour HH:MM (e.g. typing 1300 becomes 13:00)
function setup24HourInput(id) {
    const el = document.getElementById(id);
    if (!el) return;

    el.addEventListener('input', function(e) {
        let val = el.value.replace(/[^0-9:]/g, '');
        // If user typed 4 consecutive digits like "1300", auto-insert colon
        if (/^\d{4}$/.test(val)) {
            val = val.substring(0, 2) + ':' + val.substring(2, 4);
        } else if (/^\d{3}$/.test(val) && !val.includes(':')) {
            val = val.substring(0, 2) + ':' + val.substring(2);
        }
        el.value = val;
    });

    el.addEventListener('blur', function() {
        let val = el.value.trim();
        // If single digit hour like 7:15, pad with 0 -> 07:15
        if (/^\d{1}:\d{2}$/.test(val)) {
            el.value = '0' + val;
        }
    });
}
setup24HourInput('jam_mulai');
setup24HourInput('jam_selesai');
</script>

</body>



</html>