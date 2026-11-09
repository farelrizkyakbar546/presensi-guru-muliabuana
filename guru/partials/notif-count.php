<?php
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require_once __DIR__ . '/../../config/database.php';
}

$userId = function_exists('currentUserId')
    ? (int) currentUserId()
    : (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    $notifCount = 0;
    return;
}

$stmt = $pdo->prepare("SELECT id, nama_lengkap FROM guru WHERE user_id = :user_id LIMIT 1");
$stmt->execute(['user_id' => $userId]);
$guru = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guru) {
    $notifCount = 0;
    return;
}

$guruId = (int) $guru['id'];
$tanggalHariIni = date('Y-m-d');
$namaHari = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$hariIni = $namaHari[date('l')];

$buatNotifikasi = static function (
    PDO $pdo,
    int $userId,
    string $eventKey,
    string $tipe,
    string $judul,
    string $pesan,
    ?string $link
): void {
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO notifikasi (user_id, tipe, event_key, judul, pesan, link, dibaca, created_at)
        VALUES (:user_id, :tipe, :event_key, :judul, :pesan, :link, 0, :created_at)
    ");
    $stmt->execute([
        'user_id' => $userId,
        'tipe' => $tipe,
        'event_key' => $eventKey,
        'judul' => $judul,
        'pesan' => $pesan,
        'link' => $link,
        'created_at' => date('Y-m-d H:i:s')
    ]);
};

$stmt = $pdo->prepare("SELECT id, waktu_masuk FROM presensi_guru WHERE guru_id = :guru_id AND tanggal = :tanggal LIMIT 1");
$stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
$presensi = $stmt->fetch(PDO::FETCH_ASSOC);

if ($presensi && !empty($presensi['waktu_masuk'])) {
    $buatNotifikasi(
        $pdo,
        $userId,
        'presensi_masuk:' . $guruId . ':' . $tanggalHariIni,
        'presensi_masuk',
        'Presensi Datang Tercatat',
        'Presensi datang Anda hari ini telah tercatat.',
        'presensi.php'
    );

    $stmtPulang = $pdo->prepare("SELECT waktu_pulang FROM presensi_guru WHERE id = :id LIMIT 1");
    $stmtPulang->execute(['id' => (int) $presensi['id']]);
    $presensiPulang = $stmtPulang->fetch(PDO::FETCH_ASSOC);

    if ($presensiPulang && !empty($presensiPulang['waktu_pulang'])) {
        $buatNotifikasi(
            $pdo,
            $userId,
            'presensi_pulang:' . $guruId . ':' . $tanggalHariIni,
            'presensi_pulang',
            'Presensi Pulang Tercatat',
            'Presensi pulang Anda hari ini telah tercatat.',
            'riwayat.php'
        );
    }
}

$stmt = $pdo->prepare("SELECT id, keterangan FROM jadwal_piket WHERE guru_id = :guru_id AND hari = :hari LIMIT 1");
$stmt->execute(['guru_id' => $guruId, 'hari' => $hariIni]);
$piket = $stmt->fetch(PDO::FETCH_ASSOC);

if ($piket) {
    $buatNotifikasi(
        $pdo,
        $userId,
        'piket:' . $guruId . ':' . $tanggalHariIni,
        'piket',
        'Tugas Guru Piket',
        !empty($piket['keterangan']) ? $piket['keterangan'] : 'Anda bertugas sebagai Guru Piket hari ini.',
        'dashboard_piket.php'
    );
}

$stmt = $pdo->prepare("
    SELECT p.id, ga.nama_lengkap AS nama_guru_asal, m.nama_mapel, k.nama_kelas
    FROM pengampu p
    INNER JOIN jadwal j ON j.id = p.jadwal_id
    INNER JOIN guru ga ON ga.id = p.guru_asal_id
    INNER JOIN mapel m ON m.id = j.mapel_id
    INNER JOIN kelas k ON k.id = j.kelas_id
    WHERE p.guru_pengganti_id = :guru_id AND p.tanggal = :tanggal AND p.status = 'Aktif'
    ORDER BY p.id DESC
");
$stmt->execute(['guru_id' => $guruId, 'tanggal' => $tanggalHariIni]);
$ambilAlihList = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ambilAlihList as $alih) {
    $buatNotifikasi(
        $pdo,
        $userId,
        'pengampu:' . (int) $alih['id'],
        'pengampu',
        'Ambil Alih Jadwal',
        'Anda mengambil alih jadwal ' . $alih['nama_guru_asal'] . ' — ' . $alih['nama_mapel'] . ' (' . $alih['nama_kelas'] . ').',
        'presensi-siswa.php'
    );
}

$namaFileSaatIni = basename($_SERVER['PHP_SELF'] ?? '');
if ($namaFileSaatIni === 'notifikasi.php') {
    $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1, dibaca_at = :dibaca_at WHERE user_id = :user_id AND dibaca = 0");
    $stmt->execute(['dibaca_at' => date('Y-m-d H:i:s'), 'user_id' => $userId]);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = :user_id AND dibaca = 0");
$stmt->execute(['user_id' => $userId]);
$notifCount = (int) $stmt->fetchColumn();