<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

requireLogin();

if (currentRole() !== 'guru') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    exit('QR presensi tidak valid.');
}

$tokenHash = hash('sha256', $token);

try {
    $sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $tanggalHariIni = $sekarang->format('Y-m-d');
    $waktuSekarang = $sekarang->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $stmtQr = $pdo->prepare("SELECT id, token_hash, dibuat_at, berlaku_sampai, digunakan_at, digunakan_oleh FROM qr_presensi WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE");
    $stmtQr->execute(['token_hash' => $tokenHash]);
    $qr = $stmtQr->fetch(PDO::FETCH_ASSOC);

    if (!$qr) {
        throw new RuntimeException('QR presensi tidak ditemukan atau sudah tidak berlaku.');
    }

    if ($qr['digunakan_at'] !== null) {
        throw new RuntimeException('QR ini sudah digunakan. Silakan scan QR yang baru.');
    }

    $berlakuSampai = new DateTime($qr['berlaku_sampai'], new DateTimeZone('Asia/Jakarta'));
    if ($sekarang >= $berlakuSampai) {
        throw new RuntimeException('QR sudah kedaluwarsa. Silakan scan QR yang baru.');
    }

    $stmtGuru = $pdo->prepare("SELECT g.id, g.nama_lengkap, g.status, u.id AS user_id FROM guru g INNER JOIN users u ON u.id = g.user_id WHERE g.user_id = :user_id LIMIT 1");
    $stmtGuru->execute(['user_id' => currentUserId()]);
    $guru = $stmtGuru->fetch(PDO::FETCH_ASSOC);

    if (!$guru || $guru['status'] !== 'aktif') {
        throw new RuntimeException('Data guru tidak ditemukan atau akun guru tidak aktif.');
    }

    $stmtIzin = $pdo->prepare("SELECT p.id, p.jenis_izin, p.alasan FROM perizinan p WHERE p.guru_id = :guru_id AND p.status = 'Disetujui' AND :tanggal BETWEEN p.tanggal_mulai AND p.tanggal_selesai ORDER BY p.id DESC LIMIT 1");
    $stmtIzin->execute(['guru_id' => $guru['id'], 'tanggal' => $tanggalHariIni]);
    if ($stmtIzin->fetch(PDO::FETCH_ASSOC)) {
        throw new RuntimeException('Anda sedang memiliki izin yang disetujui hari ini. Presensi Guru tidak dapat dilakukan.');
    }

    $stmtPresensi = $pdo->prepare("SELECT id, waktu_masuk, waktu_pulang, status, keterangan FROM presensi_guru WHERE guru_id = :guru_id AND tanggal = :tanggal LIMIT 1 FOR UPDATE");
    $stmtPresensi->execute(['guru_id' => $guru['id'], 'tanggal' => $tanggalHariIni]);
    $presensi = $stmtPresensi->fetch(PDO::FETCH_ASSOC);

    if (!$presensi) {
        $stmtInsert = $pdo->prepare("INSERT INTO presensi_guru (guru_id, tanggal, waktu_masuk, status, keterangan) VALUES (:guru_id, :tanggal, :waktu_masuk, 'Hadir', 'Presensi masuk melalui QR')");
        $stmtInsert->execute(['guru_id' => $guru['id'], 'tanggal' => $tanggalHariIni, 'waktu_masuk' => $waktuSekarang]);
        $presensiId = (int) $pdo->lastInsertId();

        $stmtUseQr = $pdo->prepare("UPDATE qr_presensi SET digunakan_at = :digunakan_at, digunakan_oleh = :digunakan_oleh WHERE id = :id AND digunakan_at IS NULL");
        $stmtUseQr->execute(['digunakan_at' => $waktuSekarang, 'digunakan_oleh' => currentUserId(), 'id' => $qr['id']]);

        if ($stmtUseQr->rowCount() !== 1) {
            throw new RuntimeException('QR sudah digunakan oleh proses lain.');
        }

        $dataSesudah = json_encode([
            'id' => $presensiId,
            'guru_id' => $guru['id'],
            'tanggal' => $tanggalHariIni,
            'waktu_masuk' => $waktuSekarang,
            'status' => 'Hadir',
            'keterangan' => 'Presensi masuk melalui QR'
        ], JSON_UNESCAPED_UNICODE);

        $stmtAudit = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (:user_id, 'presensi_guru', :record_id, 'INSERT', NULL, :data_sesudah, :alasan)");
        $stmtAudit->execute([
            'user_id' => currentUserId(),
            'record_id' => $presensiId,
            'data_sesudah' => $dataSesudah,
            'alasan' => 'Presensi masuk menggunakan QR'
        ]);

        $pdo->commit();
        echo halamanBerhasil('Presensi Masuk Berhasil', 'Selamat datang, ' . htmlspecialchars($guru['nama_lengkap'], ENT_QUOTES, 'UTF-8') . '.', 'Waktu masuk: ' . htmlspecialchars($waktuSekarang, ENT_QUOTES, 'UTF-8'));
        exit;
    }

    if ($presensi['status'] === 'Hadir' && $presensi['waktu_masuk'] !== null && $presensi['waktu_pulang'] === null) {
        $waktuMasukTime = strtotime($presensi['waktu_masuk']);
        $waktuSekarangTime = $sekarang->getTimestamp();
        $selisihMenit = ($waktuSekarangTime - $waktuMasukTime) / 60;

        if ($selisihMenit < 15) {
            $sisaMenit = max(1, (int) ceil(15 - $selisihMenit));
            $jamMasukTeks = date('H:i', $waktuMasukTime);
            throw new RuntimeException("Presensi masuk Anda sudah tercatat pada pukul {$jamMasukTeks}. Presensi pulang baru dapat dilakukan minimal 15 menit setelah waktu masuk (tersisa {$sisaMenit} menit).");
        }

        $waktuPulang = $sekarang->format('H:i:s');
        $stmtUpdate = $pdo->prepare("UPDATE presensi_guru SET waktu_pulang = :waktu_pulang, keterangan = 'Presensi masuk dan pulang melalui QR' WHERE id = :id");
        $stmtUpdate->execute(['waktu_pulang' => $waktuPulang, 'id' => $presensi['id']]);

        $stmtUseQr = $pdo->prepare("UPDATE qr_presensi SET digunakan_at = :digunakan_at, digunakan_oleh = :digunakan_oleh WHERE id = :id AND digunakan_at IS NULL");
        $stmtUseQr->execute(['digunakan_at' => $waktuSekarang, 'digunakan_oleh' => currentUserId(), 'id' => $qr['id']]);

        if ($stmtUseQr->rowCount() !== 1) {
            throw new RuntimeException('QR sudah digunakan oleh proses lain.');
        }

        $dataSebelum = json_encode([
            'id' => $presensi['id'],
            'guru_id' => $guru['id'],
            'tanggal' => $tanggalHariIni,
            'waktu_masuk' => $presensi['waktu_masuk'],
            'waktu_pulang' => $presensi['waktu_pulang'],
            'status' => $presensi['status'],
            'keterangan' => $presensi['keterangan']
        ], JSON_UNESCAPED_UNICODE);

        $dataSesudah = json_encode([
            'id' => $presensi['id'],
            'guru_id' => $guru['id'],
            'tanggal' => $tanggalHariIni,
            'waktu_masuk' => $presensi['waktu_masuk'],
            'waktu_pulang' => $waktuPulang,
            'status' => 'Hadir',
            'keterangan' => 'Presensi masuk dan pulang melalui QR'
        ], JSON_UNESCAPED_UNICODE);

        $stmtAudit = $pdo->prepare("INSERT INTO audit_trail (user_id, tabel, record_id, aksi, data_sebelum, data_sesudah, alasan) VALUES (:user_id, 'presensi_guru', :record_id, 'UPDATE', :data_sebelum, :data_sesudah, :alasan)");
        $stmtAudit->execute([
            'user_id' => currentUserId(),
            'record_id' => $presensi['id'],
            'data_sebelum' => $dataSebelum,
            'data_sesudah' => $dataSesudah,
            'alasan' => 'Presensi pulang menggunakan QR'
        ]);

        $pdo->commit();
        echo halamanBerhasil('Presensi Pulang Berhasil', 'Sampai jumpa, ' . htmlspecialchars($guru['nama_lengkap'], ENT_QUOTES, 'UTF-8') . '.', 'Waktu pulang: ' . htmlspecialchars($waktuPulang, ENT_QUOTES, 'UTF-8'));
        exit;
    }

    if ($presensi['waktu_pulang'] !== null) {
        throw new RuntimeException('Anda sudah melakukan presensi masuk dan pulang hari ini.');
    }

    throw new RuntimeException('Presensi tidak dapat diproses.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo halamanGagal('Presensi Tidak Berhasil', $e->getMessage());
    exit;
}

function halamanBerhasil(string $judul, string $pesan, string $detail): string
{
    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $judul . '</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: Arial, sans-serif; background: #f4f6f8; color: #1f2937; }
        .card { width: 100%; max-width: 460px; background: #ffffff; border-radius: 16px; padding: 32px 24px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .icon { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; background: #16a34a; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 30px; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { margin: 8px 0; line-height: 1.6; color: #4b5563; }
        .detail { margin-top: 18px; padding: 12px 14px; background: #f3f4f6; border-radius: 10px; font-size: 14px; }
        a { display: inline-block; margin-top: 22px; padding: 11px 18px; border-radius: 9px; background: #111827; color: #ffffff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>' . $judul . '</h1>
        <p>' . $pesan . '</p>
        <div class="detail">' . $detail . '</div>
        <a href="../guru/presensi.php">Kembali ke Presensi</a>
    </div>
</body>
</html>';
}

function halamanGagal(string $judul, string $pesan): string
{
    return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($judul, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; font-family: Arial, sans-serif; background: #f4f6f8; color: #1f2937; }
        .card { width: 100%; max-width: 460px; background: #ffffff; border-radius: 16px; padding: 32px 24px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .icon { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; background: #dc2626; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: bold; }
        h1 { margin: 0 0 12px; font-size: 23px; }
        p { margin: 0; line-height: 1.6; color: #4b5563; }
        a { display: inline-block; margin-top: 22px; padding: 11px 18px; border-radius: 9px; background: #111827; color: #ffffff; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">!</div>
        <h1>' . htmlspecialchars($judul, ENT_QUOTES, 'UTF-8') . '</h1>
        <p>' . htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') . '</p>
        <a href="../guru/presensi.php">Kembali</a>
    </div>
</body>
</html>';
}