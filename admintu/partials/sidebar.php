<?php
$activeMenu = $activeMenu ?? '';

$menuGrup = [
    'Utama' => [
        'dashboard' => ['label' => 'Dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard'],
    ],
    'Master Data' => [
        'guru'    => ['label' => 'Data Guru',    'href' => 'guru.php',    'icon' => 'guru'],
        'siswa'   => ['label' => 'Data Siswa',   'href' => 'siswa.php',   'icon' => 'siswa'],
        'kelas'   => ['label' => 'Data Kelas',   'href' => 'kelas.php',   'icon' => 'kelas'],
        'jurusan' => ['label' => 'Data Jurusan', 'href' => 'jurusan.php', 'icon' => 'jurusan'],
        'mapel'   => ['label' => 'Mata Pelajaran', 'href' => 'mapel.php', 'icon' => 'mapel'],
    ],
    'Operasional' => [
        'jadwal'       => ['label' => 'Jadwal Mengajar', 'href' => 'jadwal.php',       'icon' => 'jadwal'],
        'jadwal-piket' => ['label' => 'Jadwal Piket',    'href' => 'jadwal-piket.php', 'icon' => 'piket'],
        'perizinan'    => ['label' => 'Perizinan',       'href' => 'perizinan.php',    'icon' => 'perizinan'],
        'rekap'        => ['label' => 'Rekap Presensi',  'href' => 'rekap.php',        'icon' => 'rekap'],
    ],
    'Data & Akun' => [
        'import' => ['label' => 'Import Data',    'href' => 'import.php', 'icon' => 'import'],
        'export' => ['label' => 'Export Data',    'href' => 'export.php', 'icon' => 'export'],
        'users'  => ['label' => 'Manajemen Akun', 'href' => 'users.php',  'icon' => 'account'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logosmks-removebg-preview-removebg-preview.png" alt="Logo SMKS Mulia Buana" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <h1 class="sidebar-brand-title">SMKS Mulia Buana</h1>
            <div class="sidebar-brand-subtitle">Admin TU</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuGrup as $grup => $menuList): ?>
            <div class="sidebar-group-title"><?= e($grup) ?></div>
            <?php foreach ($menuList as $key => $menu): ?>
                <a href="<?= e($menu['href']) ?>" class="sidebar-link <?= $activeMenu === $key ? 'active' : '' ?>">
                    <span class="sidebar-link-icon"><?= appIcon($menu['icon']) ?></span>
                    <span class="sidebar-link-text"><?= e($menu['label']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <div style="margin: 10px 0 8px; border-top: 1px solid rgba(255,255,255,0.08);"></div>
        <a href="../logout.php" class="sidebar-link">
            <span class="sidebar-link-icon"><?= appIcon('logout') ?></span>
            <span class="sidebar-link-text">Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <p class="sidebar-footer-title">&copy; <?= date('Y') ?> SMKS Mulia Buana</p>
        <p class="sidebar-footer-copy">Sistem Digitalisasi Presensi & Monitoring</p>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>