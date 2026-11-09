<?php

$pageTitle    = $pageTitle ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$notifCount   = (int) ($notifCount ?? 0);

?>

<header class="topbar">

    <div class="topbar-left">

        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Buka menu"
        >
            <?= appIcon('menu', '', 20) ?>
        </button>

        <?php if ($pageTitle !== ''): ?>
            <div class="topbar-page">
                <div class="topbar-page-title">
                    <?= e($pageTitle) ?>
                </div>

                <?php if ($pageSubtitle !== ''): ?>
                    <div class="topbar-page-subtitle">
                        <?= e($pageSubtitle) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="topbar-right">

        <a
            href="notifikasi.php"
            class="topbar-notif"
            title="Notifikasi"
            aria-label="Notifikasi"
        >
            <?= appIcon('bell', '', 20) ?>

            <?php if ($notifCount > 0): ?>
                <span class="notification-dot">
                    <?= (int) $notifCount ?>
                </span>
            <?php endif; ?>
        </a>

    </div>

</header>

<script>
(function () {
    'use strict';

    if (document.body) {
        document.body.setAttribute(
            'data-notification-endpoint',
            '../proses/notif-count.php'
        );
    }

    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');

        if (sidebar) sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('show');
    }

    window.toggleSidebar = toggleSidebar;

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('menuToggle');
        var overlay = document.getElementById('sidebarOverlay');

        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (overlay) {
            overlay.addEventListener('click', toggleSidebar);
        }
    });
})();
</script>
<script src="../assets/js/main.js" defer></script>
