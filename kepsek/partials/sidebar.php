<?php
$activeMenu = $activeMenu ?? '';

$menuGrup = [
    'Utama' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'href'  => 'dashboard.php',
            'icon'  => 'dashboard',
        ],
    ],
    'Monitoring' => [
        'monitoring-guru' => [
            'label' => 'Monitoring Guru',
            'href'  => 'monitoring-guru.php',
            'icon'  => 'monitoring-guru',
        ],
        'monitoring-siswa' => [
            'label' => 'Monitoring Siswa',
            'href'  => 'monitoring-siswa.php',
            'icon'  => 'monitoring-siswa',
        ],
        'perizinan' => [
            'label' => 'Perizinan',
            'href'  => 'perizinan.php',
            'icon'  => 'perizinan',
        ],
        'rekap' => [
            'label' => 'Rekap Presensi',
            'href'  => 'rekap.php',
            'icon'  => 'rekap',
        ],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/img/logosmks-removebg-preview-removebg-preview.png" alt="Logo SMKS Mulia Buana" class="sidebar-logo">
        <div class="sidebar-brand-text">
            <h1 class="sidebar-brand-title">SMKS Mulia Buana</h1>
            <div class="sidebar-brand-subtitle">Kepala Sekolah</div>
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