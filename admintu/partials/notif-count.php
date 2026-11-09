<?php
$notifCount = 0;

try {
    $userId = function_exists('currentUserId')
        ? (int) currentUserId()
        : (int) ($_SESSION['user_id'] ?? 0);

    if ($userId > 0) {
        $stmtPending = $pdo->query("
            SELECT p.id, g.nama_lengkap, p.jenis_izin, p.tanggal_mulai, p.tanggal_selesai
            FROM perizinan p
            INNER JOIN guru g ON g.id = p.guru_id
            WHERE p.status = 'Menunggu'
            ORDER BY p.created_at ASC, p.id ASC
        ");
        $pendingList = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        $stmtNotif = $pdo->prepare("
            INSERT IGNORE INTO notifikasi (user_id, tipe, event_key, judul, pesan, link, dibaca, created_at)
            VALUES (?, 'perizinan', ?, ?, ?, 'perizinan.php', 0, NOW())
        ");

        $stmtPenerima = $pdo->prepare("
            SELECT id FROM users WHERE id = ? AND role IN ('adminTu', 'kepsek') AND status = 'aktif' LIMIT 1
        ");

        $tanggalFormat = static function (string $tanggal): string {
            $dt = DateTime::createFromFormat('Y-m-d', $tanggal);
            return $dt ? $dt->format('d-m-Y') : $tanggal;
        };

        foreach ($pendingList as $pending) {
            $stmtPenerima->execute([$userId]);
            if (!$stmtPenerima->fetchColumn()) {
                continue;
            }

            $eventKey = 'perizinan:diajukan:' . (int) $pending['id'];
            $judul = 'Pengajuan Perizinan Baru';
            $pesan = $pending['nama_lengkap'] . ' mengajukan ' . $pending['jenis_izin'] .
                     ' untuk tanggal ' . $tanggalFormat($pending['tanggal_mulai']) .
                     ' sampai ' . $tanggalFormat($pending['tanggal_selesai']) . '. Menunggu proses Admin TU.';

            $stmtNotif->execute([$userId, $eventKey, $judul, $pesan]);
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
        $stmt->execute([$userId]);
        $notifCount = (int) $stmt->fetchColumn();
    }
} catch (Throwable $e) {
    $notifCount = 0;
}
