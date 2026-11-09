(function () {
    'use strict';

    var intervalMs = 5000;

    function updateNotificationBadge(count) {
        var links = document.querySelectorAll('.topbar-notif');
        if (!links.length) return;

        links.forEach(function (link) {
            var badge = link.querySelector('.notification-dot');
            var value = parseInt(count, 10) || 0;

            if (value > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'notification-dot';
                    link.appendChild(badge);
                }
                badge.textContent = value;
                link.setAttribute('aria-label', 'Notifikasi belum dibaca: ' + value);
            } else if (badge) {
                badge.remove();
                link.setAttribute('aria-label', 'Tidak ada notifikasi baru');
            }
        });
    }

    function syncNotificationCount() {
        var endpoint = document.body ? document.body.getAttribute('data-notification-endpoint') : null;
        if (!endpoint) return;

        fetch(endpoint, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Gagal mengambil jumlah notifikasi.');
            return response.json();
        })
        .then(function (data) {
            if (data && data.success === true) {
                updateNotificationBadge(data.count);
            }
        })
        .catch(function () {});
    }

    function injectMobileFooter() {
        if (document.querySelector('.mobile-page-footer')) return;

        var container = document.querySelector('.dashboard-container') ||
                        document.querySelector('.dashboard-content') ||
                        document.querySelector('.app-main');

        if (!container) return;

        var footer = document.createElement('footer');
        footer.className = 'mobile-page-footer';
        footer.innerHTML =
            '<p class="mobile-page-footer-title">&copy; ' + new Date().getFullYear() + ' SMKS Mulia Buana</p>' +
            '<p class="mobile-page-footer-copy">Sistem Digitalisasi Presensi &amp; Monitoring</p>';

        container.appendChild(footer);
    }

    document.addEventListener('DOMContentLoaded', function () {
        injectMobileFooter();
        syncNotificationCount();
        window.setInterval(syncNotificationCount, intervalMs);
    });
})();
