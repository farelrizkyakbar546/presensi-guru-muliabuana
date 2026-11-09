<?php



require_once '../config/database.php';

require_once '../config/session.php';

require_once '../config/helpers.php';



requireRole('guru');



date_default_timezone_set('Asia/Jakarta');



$userId = currentUserId();









$stmt = $pdo->prepare("

    SELECT

        id,

        nama_lengkap,

        status

    FROM guru

    WHERE user_id = ?

    LIMIT 1

");



$stmt->execute([

    $userId

]);



$guru = $stmt->fetch();



if (!$guru) {

    die('Data guru tidak ditemukan.');

}



if ($guru['status'] !== 'aktif') {

    die('Data guru tidak aktif.');

}



$guruId = (int) $guru['id'];









if (

    isset($_GET['ajax']) &&

    $_GET['ajax'] === 'jadwal'

) {



    header('Content-Type: application/json; charset=utf-8');



    $tanggalMulai = trim(

        $_GET['tanggal_mulai'] ?? ''

    );



    $tanggalSelesai = trim(

        $_GET['tanggal_selesai'] ?? ''

    );





    



    if (

        $tanggalMulai === '' ||

        $tanggalSelesai === ''

    ) {



        echo json_encode([

            'success' => false,

            'message' => 'Tanggal belum lengkap.'

        ]);



        exit;

    }





    $start = DateTime::createFromFormat(

        'Y-m-d',

        $tanggalMulai

    );



    $end = DateTime::createFromFormat(

        'Y-m-d',

        $tanggalSelesai

    );





    if (

        !$start ||

        !$end ||

        $start->format('Y-m-d') !== $tanggalMulai ||

        $end->format('Y-m-d') !== $tanggalSelesai

    ) {



        echo json_encode([

            'success' => false,

            'message' => 'Format tanggal tidak valid.'

        ]);



        exit;

    }





    if ($start > $end) {



        echo json_encode([

            'success' => false,

            'message' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.'

        ]);



        exit;

    }





    $selisihHari =

        (int) $start->diff($end)->days + 1;





    if ($selisihHari > 31) {



        echo json_encode([

            'success' => false,

            'message' => 'Periode izin maksimal 31 hari.'

        ]);



        exit;

    }





    



    $hariIndonesia = [

        'Monday'    => 'Senin',

        'Tuesday'   => 'Selasa',

        'Wednesday' => 'Rabu',

        'Thursday'  => 'Kamis',

        'Friday'    => 'Jumat',

        'Saturday'  => 'Sabtu',

        'Sunday'    => 'Minggu'

    ];





    



    $tanggalBerjalan = clone $start;



    $hariDalamPeriode = [];



    while ($tanggalBerjalan <= $end) {



        $hariInggris =

            $tanggalBerjalan->format('l');





        if (

            isset(

                $hariIndonesia[$hariInggris]

            )

        ) {



            $hariDalamPeriode[] = [

                'tanggal' =>

                    $tanggalBerjalan->format('Y-m-d'),



                'hari' =>

                    $hariIndonesia[$hariInggris]

            ];

        }





        $tanggalBerjalan->modify('+1 day');

    }





    if (empty($hariDalamPeriode)) {



        echo json_encode([

            'success' => true,

            'jadwal' => []

        ]);



        exit;

    }





    



    $stmt = $pdo->prepare("

        SELECT

            j.id,

            j.hari,

            j.jam_mulai,

            j.jam_selesai,



            m.nama_mapel,

            m.kode_mapel,



            k.nama_kelas,

            k.tingkat,



            jur.nama_jurusan



        FROM jadwal j



        INNER JOIN mapel m

            ON m.id = j.mapel_id



        INNER JOIN kelas k

            ON k.id = j.kelas_id



        INNER JOIN jurusan jur

            ON jur.id = k.jurusan_id



        WHERE j.guru_id = ?



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

            j.jam_mulai

    ");



    $stmt->execute([

        $guruId

    ]);



    $semuaJadwal =

        $stmt->fetchAll();





    



    $jadwalHasil = [];





    foreach (

        $hariDalamPeriode

        as $hariItem

    ) {



        foreach (

            $semuaJadwal

            as $jadwal

        ) {



            if (

                $jadwal['hari'] !==

                $hariItem['hari']

            ) {

                continue;

            }





            $jadwalHasil[] = [



                'id' =>

                    (int) $jadwal['id'],



                'tanggal' =>

                    $hariItem['tanggal'],



                'hari' =>

                    $jadwal['hari'],



                'jam_mulai' =>

                    $jadwal['jam_mulai'],



                'jam_selesai' =>

                    $jadwal['jam_selesai'],



                'nama_mapel' =>

                    $jadwal['nama_mapel'],



                'kode_mapel' =>

                    $jadwal['kode_mapel'],



                'nama_kelas' =>

                    $jadwal['nama_kelas'],



                'tingkat' =>

                    $jadwal['tingkat'],



                'nama_jurusan' =>

                    $jadwal['nama_jurusan']

            ];

        }

    }





    echo json_encode([

        'success' => true,

        'jadwal' => $jadwalHasil

    ]);



    exit;

}









$stmt = $pdo->prepare("

    SELECT

        p.id,

        p.tanggal_mulai,

        p.tanggal_selesai,

        p.jenis_izin,

        p.alasan,

        p.bukti,

        p.status,

        p.diproses_oleh,

        p.diproses_at,

        p.catatan_admin,

        p.created_at



    FROM perizinan p



    WHERE p.guru_id = ?



    ORDER BY

        p.id DESC

");



$stmt->execute([

    $guruId

]);



$izinList =

    $stmt->fetchAll();









$jadwalIzin = [];





if (!empty($izinList)) {



    $izinIds =

        array_map(

            'intval',

            array_column(

                $izinList,

                'id'

            )

        );





    $placeholders = implode(

        ',',

        array_fill(

            0,

            count($izinIds),

            '?'

        )

    );





    $stmt = $pdo->prepare("

        SELECT



            pj.perizinan_id,



            j.id AS jadwal_id,

            j.hari,

            j.jam_mulai,

            j.jam_selesai,



            m.nama_mapel,

            m.kode_mapel,



            k.nama_kelas,

            k.tingkat,



            jur.nama_jurusan



        FROM perizinan_jadwal pj



        INNER JOIN jadwal j

            ON j.id = pj.jadwal_id



        INNER JOIN mapel m

            ON m.id = j.mapel_id



        INNER JOIN kelas k

            ON k.id = j.kelas_id



        INNER JOIN jurusan jur

            ON jur.id = k.jurusan_id



        WHERE pj.perizinan_id IN ($placeholders)



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



            j.jam_mulai

    ");



    $stmt->execute(

        $izinIds

    );



    $jadwalIzinRows =

        $stmt->fetchAll();





    foreach (

        $jadwalIzinRows

        as $row

    ) {



        $perizinanId =

            (int) $row['perizinan_id'];





        if (

            !isset(

                $jadwalIzin[$perizinanId]

            )

        ) {



            $jadwalIzin[

                $perizinanId

            ] = [];

        }





        $jadwalIzin[

            $perizinanId

        ][] = $row;

    }

}









$flashSuccess =

    flash('success');



$flashError =

    flash('error');









$namaHari = [



    'Sunday' =>

        'Minggu',



    'Monday' =>

        'Senin',



    'Tuesday' =>

        'Selasa',



    'Wednesday' =>

        'Rabu',



    'Thursday' =>

        'Kamis',



    'Friday' =>

        'Jumat',



    'Saturday' =>

        'Sabtu'

];





$hariIni =

    $namaHari[

        date('l')

    ];





$stmtPiket = $pdo->prepare("

    SELECT

        id

    FROM jadwal_piket

    WHERE guru_id = ?

      AND hari = ?

    LIMIT 1

");



$stmtPiket->execute([

    $guruId,

    $hariIni

]);



$isPiket =

    $stmtPiket->fetch();









$statusBadgeMap = [



    'Menunggu' =>

        'warning',



    'Disetujui' =>

        'success',



    'Ditolak' =>

        'danger'

];









$activeMenu =

    'perizinan';



$piketHariIni =

    (bool) $isPiket;



$pageTitle =

    'Perizinan';



$pageSubtitle =

    'Ajukan izin & lihat status';

$todayYmd = date('Y-m-d');
$currentYear = (int) date('Y');
$currentMonth = (int) date('m');
$currentDay = (int) date('d');
$bulanIndo = [
    1 => 'Januari',
    2 => 'Februari',
    3 => 'Maret',
    4 => 'April',
    5 => 'Mei',
    6 => 'Juni',
    7 => 'Juli',
    8 => 'Agustus',
    9 => 'September',
    10 => 'Oktober',
    11 => 'November',
    12 => 'Desember'
];

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

        Perizinan - SMKS Mulia Buana

    </title>



    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

    <style>
        /* Dropdown Mode Grid */
        .date-select-row {
            display: grid;
            grid-template-columns: 80px 1fr 100px;
            gap: 8px;
        }

        .date-select-input {
            width: 100%;
            padding: 9px 10px;
            border: 1px solid #d6deea;
            border-radius: 8px;
            background: #ffffff;
            font-size: 13px;
            color: var(--text-900);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .date-select-input:focus {
            border-color: #6b9cf7;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .date-badge-preview {
            display: inline-block;
            margin-left: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #059669;
        }

        /* Riwayat Pengajuan Table */
        .schedule-table td {
            vertical-align: top !important;
            white-space: normal;
        }

        .period-cell {
            min-width: 135px;
            line-height: 1.45;
        }

        .period-date-main {
            font-weight: 600;
            color: var(--navy-900);
            font-size: 12px;
        }

        .period-date-sub {
            font-size: 11px;
            color: var(--text-500);
            margin-top: 2px;
        }

        .period-badge-sub {
            display: inline-block;
            padding: 2px 7px;
            background: #f1f5f9;
            color: #475569;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-top: 5px;
        }

        .affected-wrapper {
            min-width: 260px;
            max-width: 420px;
        }

        .affected-summary-badge {
            margin-bottom: 7px;
        }

        .badge-count-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .affected-groups-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .affected-day-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
            transition: all 0.15s ease;
        }

        .affected-day-card:hover {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        .affected-day-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 5px;
            margin-bottom: 6px;
            border-bottom: 1px dashed #cbd5e1;
        }

        .affected-day-badge {
            font-weight: 700;
            font-size: 12px;
            color: #1e3a8a;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .affected-day-count {
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 1px 7px;
            border-radius: 10px;
        }

        .affected-session-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .affected-session-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 11px;
            line-height: 1.4;
            padding: 3px 0;
        }

        .affected-session-item:not(:last-child) {
            border-bottom: 1px solid #f1f5f9;
        }

        .session-time-pill {
            flex-shrink: 0;
            font-size: 10.5px;
            font-weight: 600;
            color: #2563eb;
            background: #eff6ff;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #dbeafe;
        }

        .session-detail-text {
            color: var(--text-700);
        }

        .session-detail-text strong {
            color: var(--navy-900);
        }

        .session-class-text {
            font-weight: 600;
            color: #334155;
        }

        .session-jurusan-text {
            color: #94a3b8;
            font-size: 10.5px;
        }

        .btn-toggle-selengkapnya {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 650;
            cursor: pointer;
            transition: all 0.15s ease;
            width: 100%;
        }

        .btn-toggle-selengkapnya:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }

        .toggle-arrow {
            font-size: 10px;
            transition: transform 0.2s ease;
        }

        .link-selengkapnya {
            display: inline-block;
            margin-left: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #2563eb;
            text-decoration: underline;
            cursor: pointer;
        }

        .link-selengkapnya:hover {
            color: #1d4ed8;
        }

        .btn-quick-chip {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-quick-chip:hover {
            background: #dbeafe;
            border-color: #bfdbfe;
        }

        @media (max-width: 640px) {
            .date-select-row {
                grid-template-columns: 70px 1fr 80px;
                gap: 6px;
            }
        }
    </style>
</head>



<body>



<div class="app-shell">





    



    <?php

    include __DIR__ . '/partials/sidebar.php';

    ?>





    



    <div class="app-main">





        



        <?php

        include __DIR__ . '/partials/notif-count.php';

        ?>



        <?php

        include __DIR__ . '/partials/topbar.php';

        ?>





        



        <main class="dashboard-content">



            <div class="dashboard-container">





                



                <div class="page-header">



                    <div class="breadcrumb">



                        <a href="dashboard.php">

                            Dashboard

                        </a>



                        <span>/</span>



                        <span>

                            Perizinan

                        </span>



                    </div>





                    <h1 class="page-title">

                        Perizinan

                    </h1>





                    <p class="page-description">

                        Ajukan izin dan lihat status pengajuan Anda.

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

                                <?= appIcon("perizinan", "", 18) ?>

                            </span>



                            Ajukan Perizinan



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <p

                            class="page-description"

                            style="margin-top: 0;"

                        >

                            Pilih tanggal ketidakhadiran Anda.

                            Sistem akan menampilkan jadwal mengajar

                            yang sesuai dengan tanggal tersebut.

                        </p>





                        <form

                            method="POST"

                            action="../proses/perizinan.php"

                            enctype="multipart/form-data"

                            id="formPerizinan"

                        >



                            <div class="form-grid">





                                



                                <!-- ACTUAL HIDDEN INPUTS (DIKIRIM KE BACKEND & DIBACA JAVASCRIPT) -->
                                <input type="hidden" name="tanggal_mulai" id="tanggal_mulai" value="">
                                <input type="hidden" name="tanggal_selesai" id="tanggal_selesai" value="">

                                <div class="form-group">
                                    <label class="form-label">
                                        Tanggal Mulai
                                        <span class="date-badge-preview" id="previewBadgeMulai"></span>
                                    </label>
                                    <div class="date-select-row">
                                        <select id="dropMulaiHari" class="date-select-input" onchange="handleDropdownChange('mulai')">
                                            <option value="">Tgl</option>
                                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                                <option value="<?= sprintf('%02d', $d) ?>"><?= sprintf('%02d', $d) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <select id="dropMulaiBulan" class="date-select-input" onchange="handleDropdownChange('mulai')">
                                            <option value="">Bulan</option>
                                            <?php foreach ($bulanIndo as $mNum => $mName): ?>
                                                <option value="<?= sprintf('%02d', $mNum) ?>"><?= sprintf('%02d', $mNum) ?> - <?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="dropMulaiTahun" class="date-select-input" onchange="handleDropdownChange('mulai')">
                                            <option value="">Tahun</option>
                                            <?php for ($y = $currentYear; $y <= $currentYear + 2; $y++): ?>
                                                <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="note">Pilih tanggal, bulan, dan tahun mulai izin.</div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        Tanggal Selesai
                                        <span class="date-badge-preview" id="previewBadgeSelesai"></span>
                                    </label>
                                    <div class="date-select-row">
                                        <select id="dropSelesaiHari" class="date-select-input" onchange="handleDropdownChange('selesai')">
                                            <option value="">Tgl</option>
                                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                                <option value="<?= sprintf('%02d', $d) ?>"><?= sprintf('%02d', $d) ?></option>
                                            <?php endfor; ?>
                                        </select>
                                        <select id="dropSelesaiBulan" class="date-select-input" onchange="handleDropdownChange('selesai')">
                                            <option value="">Bulan</option>
                                            <?php foreach ($bulanIndo as $mNum => $mName): ?>
                                                <option value="<?= sprintf('%02d', $mNum) ?>"><?= sprintf('%02d', $mNum) ?> - <?= $mName ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select id="dropSelesaiTahun" class="date-select-input" onchange="handleDropdownChange('selesai')">
                                            <option value="">Tahun</option>
                                            <?php for ($y = $currentYear; $y <= $currentYear + 2; $y++): ?>
                                                <option value="<?= $y ?>" <?= $y === $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="note">Pilih tanggal selesai izin (otomatis disamakan jika 1 hari).</div>
                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="jenis_izin"

                                        class="form-label"

                                    >

                                        Jenis Izin

                                    </label>



                                    <select

                                        name="jenis_izin"

                                        id="jenis_izin"

                                        required

                                    >



                                        <option value="">

                                            -- Pilih Jenis --

                                        </option>



                                        <option value="Izin">

                                            Izin

                                        </option>



                                        <option value="Sakit">

                                            Sakit

                                        </option>



                                        <option value="Keperluan Pribadi">

                                            Keperluan Pribadi

                                        </option>



                                        <option value="Lainnya">

                                            Lainnya

                                        </option>



                                    </select>



                                </div>





                                



                                <div class="form-group">



                                    <label

                                        for="bukti"

                                        class="form-label"

                                    >

                                        Bukti

                                    </label>



                                    <input

                                        type="file"

                                        name="bukti"

                                        id="bukti"

                                        accept=".jpg,.jpeg,.png,.pdf"

                                    >



                                    <div class="note">

                                        JPG, JPEG, PNG, atau PDF.

                                        Maksimal 2 MB.

                                    </div>



                                </div>





                                



                                <div class="form-group full">



                                    <label class="form-label">

                                        Jadwal yang Terdampak

                                    </label>



                                    <div class="note">

                                        Setelah tanggal dipilih,

                                        jadwal mengajar yang sesuai

                                        akan muncul otomatis.

                                        Centang jadwal yang benar-benar

                                        terdampak oleh ketidakhadiran Anda.

                                    </div>





                                    <div id="scheduleContainer">



                                        <div class="empty-schedule">

                                            Pilih tanggal mulai dan

                                            tanggal selesai terlebih dahulu.

                                        </div>



                                    </div>





                                    <div

                                        id="scheduleSummary"

                                        class="schedule-summary"

                                        style="display:none;"

                                    ></div>



                                </div>





                                



                                <div class="form-group full">



                                    <label

                                        for="alasan"

                                        class="form-label"

                                    >

                                        Alasan

                                    </label>



                                    <textarea

                                        name="alasan"

                                        id="alasan"

                                        required

                                        maxlength="5000"

                                        placeholder="Tuliskan alasan pengajuan..."

                                    ></textarea>



                                    <div class="note">

                                        Maksimal 5000 karakter.

                                    </div>



                                </div>





                                



                                <div class="form-group full">



                                    <button

                                        type="submit"

                                        class="btn btn-primary"

                                        id="submitButton"

                                    >

                                        Ajukan Perizinan

                                    </button>



                                </div>



                            </div>



                        </form>



                    </div>



                </div>





                



                <div class="dashboard-card">



                    <div class="dashboard-card-header">



                        <h2 class="dashboard-card-title">



                            <span class="dashboard-card-title-icon">

                                <?= appIcon("riwayat", "", 18) ?>

                            </span>



                            Riwayat Pengajuan



                        </h2>



                    </div>





                    <div class="dashboard-card-body">



                        <?php if (empty($izinList)): ?>





                            <div class="empty-state">



                                <div class="empty-state-icon"><?= appIcon("perizinan", "", 48) ?></div>



                                <h3>

                                    Belum Ada Pengajuan

                                </h3>



                                <p>

                                    Belum ada pengajuan perizinan.

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

                                                Periode

                                            </th>



                                            <th>

                                                Jenis

                                            </th>



                                            <th>

                                                Jadwal Terdampak

                                            </th>



                                            <th>

                                                Alasan

                                            </th>



                                            <th>

                                                Status

                                            </th>



                                            <th>

                                                Catatan Admin

                                            </th>



                                        </tr>



                                    </thead>





                                    <tbody>



                                    <?php

                                    foreach (

                                        $izinList

                                        as $index => $izin

                                    ):

                                    ?>



                                        <?php



                                        $badgeClass =

                                            $statusBadgeMap[

                                                $izin['status']

                                            ]

                                            ?? 'secondary';



                                        $perizinanId =

                                            (int) $izin['id'];



                                        ?>





                                        <tr>



                                            



                                            <td>

                                                <?= $index + 1 ?>

                                            </td>





                                            



                                            <td>
                                                <div class="period-cell">
                                                    <?php if ($izin['tanggal_mulai'] === $izin['tanggal_selesai']): ?>
                                                        <div class="period-date-main"><?= e(formatTanggal($izin['tanggal_mulai'])) ?></div>
                                                        <span class="period-badge-sub">1 Hari</span>
                                                    <?php else:
                                                        $dStart = new DateTime($izin['tanggal_mulai']);
                                                        $dEnd = new DateTime($izin['tanggal_selesai']);
                                                        $diffDays = (int) $dStart->diff($dEnd)->days + 1;
                                                    ?>
                                                        <div class="period-date-main"><?= e(formatTanggal($izin['tanggal_mulai'])) ?></div>
                                                        <div class="period-date-sub">s/d <?= e(formatTanggal($izin['tanggal_selesai'])) ?></div>
                                                        <span class="period-badge-sub"><?= $diffDays ?> Hari</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td>
                                                <?= e($izin['jenis_izin']) ?>
                                            </td>

                                            <td>
                                                <?php
                                                $jadwalItems = $jadwalIzin[$perizinanId] ?? [];
                                                $totalAffected = count($jadwalItems);

                                                // Grouping per hari
                                                $groupedByHari = [];
                                                foreach ($jadwalItems as $item) {
                                                    $h = !empty($item['hari']) ? $item['hari'] : 'Lainnya';
                                                    $groupedByHari[$h][] = $item;
                                                }
                                                ?>
                                                <?php if ($totalAffected > 0): ?>
                                                    <?php $globalItemIndex = 0; ?>
                                                    <div class="affected-wrapper">
                                                        <div class="affected-summary-badge">
                                                            <span class="badge-count-pill"><?= $totalAffected ?> Jadwal Terdampak</span>
                                                        </div>

                                                        <div class="affected-groups-list">
                                                            <?php foreach ($groupedByHari as $hari => $items): ?>
                                                                <?php $firstIndexInGroup = $globalItemIndex; ?>
                                                                <div class="affected-day-card <?= ($firstIndexInGroup >= 2) ? 'extra-item-' . $perizinanId : '' ?>"
                                                                     <?= ($firstIndexInGroup >= 2) ? 'style="display:none;"' : '' ?>>
                                                                    <div class="affected-day-header">
                                                                        <span class="affected-day-badge">
                                                                            <?= appIcon("jadwal", "", 13) ?> <?= e($hari) ?>
                                                                        </span>
                                                                        <span class="affected-day-count"><?= count($items) ?> Sesi Mengajar</span>
                                                                    </div>
                                                                    <div class="affected-session-list">
                                                                        <?php foreach ($items as $item): ?>
                                                                            <?php $isExtraItem = ($globalItemIndex >= 2); ?>
                                                                            <div class="affected-session-item <?= $isExtraItem ? 'extra-item-' . $perizinanId : '' ?>"
                                                                                 <?= $isExtraItem ? 'style="display:none;"' : '' ?>>
                                                                                <span class="session-time-pill"><?= e(formatJam($item['jam_mulai'])) ?> - <?= e(formatJam($item['jam_selesai'])) ?></span>
                                                                                <span class="session-detail-text">
                                                                                    <strong><?= e($item['nama_mapel']) ?></strong>
                                                                                    <span class="session-class-text"> — <?= e($item['nama_kelas']) ?></span>
                                                                                    <?php if (!empty($item['nama_jurusan'])): ?>
                                                                                        <span class="session-jurusan-text" title="<?= e($item['nama_jurusan']) ?>"> (<?= e($item['nama_jurusan']) ?>)</span>
                                                                                    <?php endif; ?>
                                                                                </span>
                                                                            </div>
                                                                            <?php $globalItemIndex++; ?>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>

                                                        <?php if ($totalAffected > 2): ?>
                                                            <button type="button" 
                                                                    class="btn-toggle-selengkapnya" 
                                                                    onclick="toggleGroupedJadwal(<?= $perizinanId ?>, this)" 
                                                                    data-count="<?= $totalAffected - 2 ?>">
                                                                <span class="toggle-text">+ <?= $totalAffected - 2 ?> jadwal lainnya (Selengkapnya)</span>
                                                                <span class="toggle-arrow">▾</span>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--text-400); font-size: 11px;">
                                                        Tidak ada jadwal dipilih.
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php
                                                $alasanRaw = $izin['alasan'] ?? '';
                                                if (mb_strlen($alasanRaw) > 120):
                                                    $shortAlasan = mb_substr($alasanRaw, 0, 120) . '...';
                                                ?>
                                                    <div id="alasan-short-<?= $perizinanId ?>">
                                                        <?= nl2br(e($shortAlasan)) ?>
                                                        <a href="javascript:void(0)" onclick="toggleAlasan(<?= $perizinanId ?>)" class="link-selengkapnya">Selengkapnya</a>
                                                    </div>
                                                    <div id="alasan-full-<?= $perizinanId ?>" style="display: none;">
                                                        <?= nl2br(e($alasanRaw)) ?>
                                                        <a href="javascript:void(0)" onclick="toggleAlasan(<?= $perizinanId ?>)" class="link-selengkapnya">Sembunyikan</a>
                                                    </div>
                                                <?php else: ?>
                                                    <?= nl2br(e($alasanRaw)) ?>
                                                <?php endif; ?>
                                            </td>





                                            



                                            <td>



                                                <span

                                                    class="status-badge <?= e(

                                                        $badgeClass

                                                    ) ?>"

                                                >

                                                    <?= e(

                                                        $izin[

                                                            'status'

                                                        ]

                                                    ) ?>

                                                </span>



                                            </td>





                                            



                                            <td>



                                                <?php if (

                                                    !empty(

                                                        $izin[

                                                            'catatan_admin'

                                                        ]

                                                    )

                                                ): ?>



                                                    <?= nl2br(

                                                        e(

                                                            $izin[

                                                                'catatan_admin'

                                                            ]

                                                        )

                                                    ) ?>



                                                <?php else: ?>



                                                    <span

                                                        style="color: var(--text-400);"

                                                    >

                                                        -

                                                    </span>



                                                <?php endif; ?>



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





<script>
/* =====================================================
   DROPDOWN DATE PICKER & INTERAKSI
===================================================== */
function syncDropdownFromHidden() {
    const tanggalMulai = document.getElementById('tanggal_mulai');
    const tanggalSelesai = document.getElementById('tanggal_selesai');
    if (!tanggalMulai || !tanggalSelesai) return;

    const mulaiVal = tanggalMulai.value;
    if (mulaiVal && mulaiVal.length === 10) {
        const parts = mulaiVal.split('-');
        const yEl = document.getElementById('dropMulaiTahun');
        const mEl = document.getElementById('dropMulaiBulan');
        const dEl = document.getElementById('dropMulaiHari');
        if (yEl) yEl.value = parts[0];
        if (mEl) mEl.value = parts[1];
        if (dEl) dEl.value = parts[2];
        adjustDayOptions(dEl, parts[1], parts[0]);
        updatePreview('mulai', mulaiVal);
    }
    const selesaiVal = tanggalSelesai.value;
    if (selesaiVal && selesaiVal.length === 10) {
        const parts = selesaiVal.split('-');
        const yEl = document.getElementById('dropSelesaiTahun');
        const mEl = document.getElementById('dropSelesaiBulan');
        const dEl = document.getElementById('dropSelesaiHari');
        if (yEl) yEl.value = parts[0];
        if (mEl) mEl.value = parts[1];
        if (dEl) dEl.value = parts[2];
        adjustDayOptions(dEl, parts[1], parts[0]);
        updatePreview('selesai', selesaiVal);
    }
}

function adjustDayOptions(dayEl, monthVal, yearVal) {
    if (!dayEl) return;
    const m = parseInt(monthVal, 10);
    const y = parseInt(yearVal, 10) || new Date().getFullYear();
    if (!m) return;

    // Menghitung jumlah hari kalender asli (termasuk tahun kabisat untuk Februari)
    const maxDays = new Date(y, m, 0).getDate();

    for (let i = 1; i < dayEl.options.length; i++) {
        const opt = dayEl.options[i];
        const dayNum = parseInt(opt.value, 10);
        if (dayNum > maxDays) {
            opt.style.display = 'none';
            opt.disabled = true;
        } else {
            opt.style.display = '';
            opt.disabled = false;
        }
    }

    if (dayEl.value && parseInt(dayEl.value, 10) > maxDays) {
        dayEl.value = String(maxDays).padStart(2, '0');
    }
}

function handleDropdownChange(which) {
    const tanggalMulai = document.getElementById('tanggal_mulai');
    const tanggalSelesai = document.getElementById('tanggal_selesai');
    const today = new Date();

    let dayEl, monthEl, yearEl;
    if (which === 'mulai') {
        dayEl = document.getElementById('dropMulaiHari');
        monthEl = document.getElementById('dropMulaiBulan');
        yearEl = document.getElementById('dropMulaiTahun');
    } else {
        dayEl = document.getElementById('dropSelesaiHari');
        monthEl = document.getElementById('dropSelesaiBulan');
        yearEl = document.getElementById('dropSelesaiTahun');
    }

    if (!dayEl || !monthEl || !yearEl) return;

    // Selalu sesuaikan jumlah hari kalender saat bulan atau tahun dipilih
    adjustDayOptions(dayEl, monthEl.value, yearEl.value);

    if (dayEl.value && monthEl.value && !yearEl.value) {
        yearEl.value = String(today.getFullYear());
    }

    if (!dayEl.value || !monthEl.value || !yearEl.value) {
        return;
    }

    const y = yearEl.value;
    const m = monthEl.value;
    let d = dayEl.value;

    const maxDays = new Date(parseInt(y, 10), parseInt(m, 10), 0).getDate();
    if (parseInt(d, 10) > maxDays) {
        d = String(maxDays).padStart(2, '0');
        dayEl.value = d;
    }

    const dateStr = `${y}-${m}-${d}`;

    if (which === 'mulai') {
        tanggalMulai.value = dateStr;
        updatePreview('mulai', dateStr);

        if (!tanggalSelesai.value || tanggalSelesai.value < dateStr) {
            tanggalSelesai.value = dateStr;
            const yEnd = document.getElementById('dropSelesaiTahun');
            const mEnd = document.getElementById('dropSelesaiBulan');
            const dEnd = document.getElementById('dropSelesaiHari');
            if (yEnd) yEnd.value = y;
            if (mEnd) mEnd.value = m;
            if (dEnd) dEnd.value = d;
            adjustDayOptions(dEnd, m, y);
            updatePreview('selesai', dateStr);
        }
    } else {
        if (tanggalMulai.value && dateStr < tanggalMulai.value) {
            alert('Tanggal selesai tidak boleh sebelum tanggal mulai.');
            const mParts = tanggalMulai.value.split('-');
            yearEl.value = mParts[0];
            monthEl.value = mParts[1];
            dayEl.value = mParts[2];
            tanggalSelesai.value = tanggalMulai.value;
            updatePreview('selesai', tanggalMulai.value);
            return;
        }
        tanggalSelesai.value = dateStr;
        updatePreview('selesai', dateStr);
    }

    if (typeof window.loadJadwal === 'function') {
        window.loadJadwal();
    }
}

function updatePreview(which, dateStr) {
    const formatted = formatTanggal(dateStr);
    const badge = which === 'mulai' ? document.getElementById('previewBadgeMulai') : document.getElementById('previewBadgeSelesai');
    if (badge) {
        badge.className = 'date-badge-preview';
        badge.textContent = formatted ? '✓ ' + formatted : '';
    }
}

function toggleGroupedJadwal(id, btn) {
    const extras = document.querySelectorAll('.extra-item-' + id);
    if (extras.length === 0) return;
    const count = btn.getAttribute('data-count') || '';
    const isHidden = (extras[0].style.display === 'none');

    extras.forEach(function (el) {
        el.style.display = isHidden ? '' : 'none';
    });

    const textSpan = btn.querySelector('.toggle-text');
    const arrowSpan = btn.querySelector('.toggle-arrow');

    if (isHidden) {
        if (textSpan) textSpan.textContent = 'Sembunyikan';
        if (arrowSpan) arrowSpan.textContent = '▴';
    } else {
        if (textSpan) textSpan.textContent = '+ ' + count + ' jadwal lainnya (Selengkapnya)';
        if (arrowSpan) arrowSpan.textContent = '▾';
    }
}

function toggleAlasan(id) {
    const shortEl = document.getElementById('alasan-short-' + id);
    const fullEl = document.getElementById('alasan-full-' + id);
    if (!shortEl || !fullEl) return;

    if (shortEl.style.display === 'none') {
        shortEl.style.display = 'block';
        fullEl.style.display = 'none';
    } else {
        shortEl.style.display = 'none';
        fullEl.style.display = 'block';
    }
}

function toggleAllScheduleCheckboxes(checked) {
    const checkboxes = document.querySelectorAll('input[name="jadwal_id[]"]');
    checkboxes.forEach(function (cb) {
        cb.checked = checked;
    });
}

function formatTanggal(tanggal) {
    if (!tanggal) return '';
    const parts = tanggal.split('-');
    if (parts.length !== 3) {
        return tanggal;
    }
    const tahun = parts[0];
    const bulan = parseInt(parts[1], 10);
    const hari = parseInt(parts[2], 10);
    const namaBulan = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    return hari + ' ' + (namaBulan[bulan] || '') + ' ' + tahun;
}

function formatJam(waktu) {
    if (!waktu) return '-';
    return waktu.substring(0, 5);
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* =====================================================
   MAIN INITIALIZATION & AJAX JADWAL
===================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const tanggalMulai = document.getElementById('tanggal_mulai');
    const tanggalSelesai = document.getElementById('tanggal_selesai');
    const scheduleContainer = document.getElementById('scheduleContainer');
    const scheduleSummary = document.getElementById('scheduleSummary');
    const formPerizinan = document.getElementById('formPerizinan');
    const submitButton = document.getElementById('submitButton');

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const todayString = year + '-' + month + '-' + day;

    syncDropdownFromHidden();
    if (tanggalMulai.value && tanggalSelesai.value) {
        setTimeout(function() {
            if (typeof window.loadJadwal === 'function') window.loadJadwal();
        }, 50);
    }

    window.loadJadwal = function () {
        const mulai = tanggalMulai.value;
        const selesai = tanggalSelesai.value;

        if (!mulai || !selesai) {
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Pilih tanggal mulai dan tanggal selesai terlebih dahulu.
                </div>
            `;
            scheduleSummary.style.display = 'none';
            return;
        }

        if (mulai < todayString) {
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Tanggal mulai tidak boleh sebelum hari ini.
                </div>
            `;
            scheduleSummary.style.display = 'none';
            return;
        }

        if (selesai < mulai) {
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Tanggal selesai tidak boleh sebelum tanggal mulai.
                </div>
            `;
            scheduleSummary.style.display = 'none';
            return;
        }

        const startDate = new Date(mulai + 'T00:00:00');
        const endDate = new Date(selesai + 'T00:00:00');
        const diffTime = endDate.getTime() - startDate.getTime();
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;

        if (diffDays > 31) {
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Periode izin maksimal 31 hari.
                </div>
            `;
            scheduleSummary.style.display = 'none';
            return;
        }

        scheduleContainer.innerHTML = `
            <div class="loading-schedule">
                Memuat jadwal sesuai tanggal...
            </div>
        `;
        scheduleSummary.style.display = 'none';

        const url = 'perizinan.php?ajax=jadwal' +
            '&tanggal_mulai=' + encodeURIComponent(mulai) +
            '&tanggal_selesai=' + encodeURIComponent(selesai);

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Gagal mengambil jadwal.');
            }
            return response.json();
        })
        .then(function (data) {
            if (!data.success) {
                scheduleContainer.innerHTML = `
                    <div class="empty-schedule">
                        ${escapeHtml(data.message || 'Jadwal tidak dapat dimuat.')}
                    </div>
                `;
                scheduleSummary.style.display = 'none';
                return;
            }

            renderJadwal(data.jadwal);
        })
        .catch(function (error) {
            console.error(error);
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Jadwal gagal dimuat. Silakan coba lagi.
                </div>
            `;
            scheduleSummary.style.display = 'none';
        });
    };

    function renderJadwal(jadwalList) {
        if (!jadwalList || jadwalList.length === 0) {
            scheduleContainer.innerHTML = `
                <div class="empty-schedule">
                    Tidak ada jadwal mengajar pada tanggal/periode tersebut.
                </div>
            `;
            scheduleSummary.style.display = 'none';
            return;
        }

        const grouped = {};
        jadwalList.forEach(function (jadwal) {
            if (!grouped[jadwal.tanggal]) {
                grouped[jadwal.tanggal] = {
                    tanggal: jadwal.tanggal,
                    hari: jadwal.hari,
                    jadwal: []
                };
            }
            grouped[jadwal.tanggal].jadwal.push(jadwal);
        });

        let html = '';
        let total = 0;

        Object.values(grouped).forEach(function (group) {
            html += `
                <div class="schedule-day">
                    <div class="schedule-day-title">
                        ${escapeHtml(group.hari)} — ${escapeHtml(formatTanggal(group.tanggal))}
                    </div>
            `;

            group.jadwal.forEach(function (jadwal) {
                total++;
                html += `
                    <label class="schedule-item">
                        <input type="checkbox" name="jadwal_id[]" value="${jadwal.id}">
                        <div class="schedule-content">
                            <div class="schedule-title">
                                ${escapeHtml(formatJam(jadwal.jam_mulai))} - ${escapeHtml(formatJam(jadwal.jam_selesai))}
                            </div>
                            <div class="schedule-detail">
                                <strong>${escapeHtml(jadwal.nama_mapel)}</strong>
                                ${jadwal.kode_mapel ? ' (' + escapeHtml(jadwal.kode_mapel) + ')' : ''}
                                <br>
                                Kelas: ${escapeHtml(jadwal.nama_kelas)}
                                ${jadwal.nama_jurusan ? ' - ' + escapeHtml(jadwal.nama_jurusan) : ''}
                                ${jadwal.tingkat ? ' (' + escapeHtml(jadwal.tingkat) + ')' : ''}
                            </div>
                        </div>
                    </label>
                `;
            });

            html += `</div>`;
        });

        scheduleContainer.innerHTML = `
            <div class="schedule-box">
                ${html}
            </div>
        `;

        scheduleSummary.innerHTML = `
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;">
                <span><strong>${total}</strong> jadwal ditemukan. Centang jadwal yang terdampak:</span>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="btn-quick-chip" onclick="toggleAllScheduleCheckboxes(true)">Centang Semua</button>
                    <button type="button" class="btn-quick-chip" style="background:#f1f5f9; border-color:#cbd5e1; color:#475569;" onclick="toggleAllScheduleCheckboxes(false)">Hapus Semua</button>
                </div>
            </div>
        `;
        scheduleSummary.style.display = 'block';
    }

    formPerizinan.addEventListener('submit', function (event) {
        const mulai = tanggalMulai.value;
        const selesai = tanggalSelesai.value;

        if (!mulai || !selesai) {
            event.preventDefault();
            alert('Tanggal mulai dan tanggal selesai wajib diisi.');
            return;
        }

        if (mulai < todayString) {
            event.preventDefault();
            alert('Tanggal mulai tidak boleh sebelum hari ini.');
            return;
        }

        if (selesai < mulai) {
            event.preventDefault();
            alert('Tanggal selesai tidak boleh sebelum tanggal mulai.');
            return;
        }

        const startDate = new Date(mulai + 'T00:00:00');
        const endDate = new Date(selesai + 'T00:00:00');
        const diffTime = endDate.getTime() - startDate.getTime();
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)) + 1;

        if (diffDays > 31) {
            event.preventDefault();
            alert('Periode izin maksimal 31 hari.');
            return;
        }

        const scheduleInputs = document.querySelectorAll('input[name="jadwal_id[]"]');
        if (scheduleInputs.length > 0) {
            const checked = document.querySelectorAll('input[name="jadwal_id[]"]:checked');
            if (checked.length === 0) {
                event.preventDefault();
                alert('Centang minimal satu jadwal yang terdampak.');
                return;
            }
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Mengirim...';
    });
});
</script>



</body>



</html>