<?php

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/helpers.php';

requireRole('adminTu');

date_default_timezone_set('Asia/Jakarta');




$statusFilter = trim(
    $_GET['status'] ?? ''
);

$statusValid = [
    'Menunggu',
    'Disetujui',
    'Ditolak'
];

$where = '';
$params = [];

if (
    in_array(
        $statusFilter,
        $statusValid,
        true
    )
) {

    $where = "
        WHERE p.status = ?
    ";

    $params[] = $statusFilter;
}




$stmt = $pdo->prepare("
    SELECT
        p.id,
        p.guru_id,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.jenis_izin,
        p.alasan,
        p.bukti,
        p.status,
        p.diproses_oleh,
        p.diproses_at,
        p.catatan_admin,
        p.created_at,

        g.nama_lengkap,
        g.nip

    FROM perizinan p

    INNER JOIN guru g
        ON g.id = p.guru_id

    $where

    ORDER BY

        CASE

            WHEN p.status = 'Menunggu'
                THEN 1

            WHEN p.status = 'Disetujui'
                THEN 2

            WHEN p.status = 'Ditolak'
                THEN 3

            ELSE 4

        END,

        p.id DESC
");

$stmt->execute($params);

$izinList = $stmt->fetchAll();




$flashSuccess = flash('success');
$flashError   = flash('error');




$badgeMap = [

    'Menunggu'  => 'warning',

    'Disetujui' => 'success',

    'Ditolak'   => 'danger',

];




$activeMenu   = 'perizinan';

$pageTitle    = 'Perizinan Guru';

$pageSubtitle = 'Approval pengajuan izin';

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
        Perizinan Guru - Admin TU
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
                            Perizinan
                        </span>

                    </div>


                    <h1 class="page-title">
                        Perizinan Guru
                    </h1>


                    <p class="page-description">
                        Kelola dan proses pengajuan izin guru.
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

                            Daftar Perizinan

                        </h2>


                        <div
                            class="dashboard-card-link"
                            style="cursor: default;"
                        >

                            Total:
                            <?= count($izinList) ?>
                            pengajuan

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
                                    for="filter_status"
                                    class="form-label"
                                >
                                    Filter Status
                                </label>


                                <select
                                    name="status"
                                    id="filter_status"
                                >

                                    <option value="">
                                        Semua Status
                                    </option>


                                    <?php foreach ($statusValid as $status): ?>

                                        <option
                                            value="<?= e($status) ?>"
                                            <?= $statusFilter === $status
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >

                                            <?= e($status) ?>

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
                                    href="perizinan.php"
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

                                        <th>
                                            No
                                        </th>

                                        <th>
                                            Guru
                                        </th>

                                        <th>
                                            Periode
                                        </th>

                                        <th>
                                            Jenis
                                        </th>

                                        <th>
                                            Alasan
                                        </th>

                                        <th>
                                            Bukti
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


                                <?php if (!$izinList): ?>


                                    <tr>

                                        <td
                                            colspan="8"
                                            style="
                                                text-align:center;
                                                padding:30px;
                                                color:var(--text-500);
                                            "
                                        >

                                            Belum ada pengajuan perizinan.

                                        </td>

                                    </tr>


                                <?php else: ?>


                                    <?php foreach (
                                        $izinList
                                        as $index => $izin
                                    ): ?>


                                        <?php

                                        $badgeClass =
                                            $badgeMap[
                                                $izin['status']
                                            ]
                                            ?? 'secondary';

                                        ?>


                                        <tr>


                                            

                                            <td>

                                                <?= $index + 1 ?>

                                            </td>


                                            

                                            <td>

                                                <span
                                                    class="schedule-subject"
                                                >

                                                    <?= e(
                                                        $izin['nama_lengkap']
                                                    ) ?>

                                                </span>


                                                <div
                                                    class="schedule-code"
                                                >

                                                    NIP:
                                                    <?= e(
                                                        $izin['nip'] ?: '-'
                                                    ) ?>

                                                </div>

                                            </td>


                                            

                                            <td>

                                                <?= e(
                                                    formatTanggal(
                                                        $izin['tanggal_mulai']
                                                    )
                                                ) ?>


                                                <br>


                                                <span
                                                    style="
                                                        color:var(--text-400);
                                                        font-size:10px;
                                                    "
                                                >
                                                    s/d
                                                </span>


                                                <br>


                                                <?= e(
                                                    formatTanggal(
                                                        $izin['tanggal_selesai']
                                                    )
                                                ) ?>

                                            </td>


                                            

                                            <td>

                                                <?= e(
                                                    $izin['jenis_izin']
                                                ) ?>

                                            </td>


                                            

                                            <td
                                                style="
                                                    white-space:normal;
                                                    max-width:220px;
                                                "
                                            >

                                                <?= nl2br(
                                                    e(
                                                        $izin['alasan']
                                                    )
                                                ) ?>

                                            </td>


                                            

                                            <td>


                                                <?php if (
                                                    !empty(
                                                        $izin['bukti']
                                                    )
                                                ): ?>


                                                    <a
                                                        href="../<?= e(
                                                            $izin['bukti']
                                                        ) ?>"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="btn btn-sm btn-outline"
                                                    >

                                                        Lihat Bukti

                                                    </a>


                                                <?php else: ?>


                                                    <span
                                                        style="
                                                            color:var(--text-400);
                                                            font-size:11px;
                                                        "
                                                    >

                                                        Tidak ada

                                                    </span>


                                                <?php endif; ?>


                                            </td>


                                            

                                            <td>


                                                <span
                                                    class="status-badge <?= e(
                                                        $badgeClass
                                                    ) ?>"
                                                >

                                                    <?= e(
                                                        $izin['status']
                                                    ) ?>

                                                </span>


                                                <?php if (
                                                    !empty(
                                                        $izin['diproses_at']
                                                    )
                                                ): ?>

                                                    <div
                                                        style="
                                                            margin-top:6px;
                                                            color:var(--text-400);
                                                            font-size:10px;
                                                            line-height:1.5;
                                                        "
                                                    >

                                                        Diproses:

                                                        <?= e(
                                                            date(
                                                                'd/m/Y H:i',
                                                                strtotime(
                                                                    $izin[
                                                                        'diproses_at'
                                                                    ]
                                                                )
                                                            )
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                            </td>


                                            

                                            <td>


                                                <?php if (
                                                    $izin['status']
                                                    === 'Menunggu'
                                                ): ?>


                                                    

                                                    <form
                                                        method="POST"
                                                        action="../proses/approval-izin.php"
                                                        style="
                                                            display:inline;
                                                        "
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="aksi"
                                                            value="setujui"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $izin['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-success"
                                                            onclick="
                                                                return confirm(
                                                                    'Setujui pengajuan izin <?= e(
                                                                        $izin['nama_lengkap']
                                                                    ) ?>?'
                                                                );
                                                            "
                                                        >

                                                            Setujui

                                                        </button>

                                                    </form>


                                                    

                                                    <form
                                                        method="POST"
                                                        action="../proses/approval-izin.php"
                                                        style="
                                                            display:inline;
                                                        "
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="aksi"
                                                            value="tolak"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="id"
                                                            value="<?= (int) $izin['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="btn btn-sm btn-danger"
                                                            onclick="
                                                                return confirm(
                                                                    'Tolak pengajuan izin <?= e(
                                                                        $izin['nama_lengkap']
                                                                    ) ?>?'
                                                                );
                                                            "
                                                        >

                                                            Tolak

                                                        </button>

                                                    </form>


                                                <?php else: ?>


                                                    <span
                                                        style="
                                                            color:var(--text-400);
                                                            font-size:11px;
                                                        "
                                                    >

                                                        Sudah diproses

                                                    </span>


                                                    <?php if (
                                                        !empty(
                                                            $izin[
                                                                'catatan_admin'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div
                                                            style="
                                                                margin-top:6px;
                                                                max-width:180px;
                                                                white-space:normal;
                                                                font-size:10px;
                                                                color:var(--text-500);
                                                            "
                                                        >

                                                            <strong>
                                                                Catatan:
                                                            </strong>

                                                            <?= nl2br(
                                                                e(
                                                                    $izin[
                                                                        'catatan_admin'
                                                                    ]
                                                                )
                                                            ) ?>

                                                        </div>

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


</body>

</html>