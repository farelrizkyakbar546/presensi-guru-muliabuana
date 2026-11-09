<?php
$activeMenu = $activeMenu ?? '';
$piketHariIni = $piketHariIni ?? false;

$menuUtama = [
    'dashboard'      => ['label' => 'Dashboard',       'href' => 'dashboard.php',      'icon' => 'dashboard'],
    'presensi'       => ['label' => 'Presensi Guru',   'href' => 'presensi.php',       'icon' => 'presensi'],
    'presensi-siswa' => ['label' => 'Presensi Siswa',  'href' => 'presensi-siswa.php', 'icon' => 'presensi-siswa'],
    'perizinan'      => ['label' => 'Perizinan',       'href' => 'perizinan.php',      'icon' => 'perizinan'],
    'riwayat'        => ['label' => 'Riwayat',         'href' => 'riwayat.php',        'icon' => 'riwayat'],
    'jadwal'         => ['label' => 'Jadwal Mengajar', 'href' => 'jadwal.php',         'icon' => 'jadwal'],
];

$menuBawah = [
    'profil' => [
        'label' => 'Profil',
        'href'  => 'profil.php',
        'icon'  => 'profil'
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logosmks-removebg-preview-removebg-preview.png" alt="Logo SMKS Mulia Buana" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <h1 class="sidebar-brand-title">SMKS Mulia Buana</h1>
            <div class="sidebar-brand-subtitle">Sistem Digitalisasi Presensi</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menuUtama as $key => $menu): ?>
            <a href="<?= e($menu['href']) ?>" class="sidebar-link <?= $activeMenu === $key ? 'active' : '' ?>">
                <span class="sidebar-link-icon"><?= appIcon($menu['icon']) ?></span>
                <span class="sidebar-link-text"><?= e($menu['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <?php if ($piketHariIni): ?>
            <a href="dashboard_piket.php" class="sidebar-link <?= $activeMenu === 'dashboard-piket' ? 'active' : '' ?>">
                <span class="sidebar-link-icon"><?= appIcon('piket') ?></span>
                <span class="sidebar-link-text">Guru Piket</span>
            </a>
        <?php endif; ?>

        <?php foreach ($menuBawah as $key => $menu): ?>
            <a href="<?= e($menu['href']) ?>" class="sidebar-link <?= $activeMenu === $key ? 'active' : '' ?>">
                <span class="sidebar-link-icon"><?= appIcon($menu['icon']) ?></span>
                <span class="sidebar-link-text"><?= e($menu['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <div style="margin: 10px 0 8px; border-top: 1px solid rgba(255,255,255,0.08);"></div>
        <a href="../logout.php" class="sidebar-link">
            <span class="sidebar-link-icon"><?= appIcon('logout') ?></span>
            <span class="sidebar-link-text">Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <p class="sidebar-footer-title">&copy; <?= date('Y') ?> SMKS Mulia Buana</p>
        <p class="sidebar-footer-copy">Sistem Digitalisasi Presensi dan Monitoring Kehadiran Guru</p>
    </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>