<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json; charset=utf-8');

$hasDisplaySession = !empty($_SESSION['display_screen_active']);

if (!$hasDisplaySession) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Token QR hanya dapat dibuat melalui layar monitor sekolah.'
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $berlakuSampai = clone $sekarang;
    $berlakuSampai->modify('+10 minutes');

    $stmtCleanup = $pdo->prepare("DELETE FROM qr_presensi WHERE digunakan_at IS NOT NULL OR berlaku_sampai < :sekarang");
    $stmtCleanup->execute(['sekarang' => $sekarang->format('Y-m-d H:i:s')]);

    $stmt = $pdo->prepare("INSERT INTO qr_presensi (token_hash, dibuat_at, berlaku_sampai) VALUES (:token_hash, :dibuat_at, :berlaku_sampai)");
    $stmt->execute([
        'token_hash'     => $tokenHash,
        'dibuat_at'      => $sekarang->format('Y-m-d H:i:s'),
        'berlaku_sampai' => $berlakuSampai->format('Y-m-d H:i:s')
    ]);

    echo json_encode([
        'success'    => true,
        'token'      => $token,
        'expires_at' => $berlakuSampai->format('c'),
        'expires_in' => 600
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal membuat QR presensi.'
    ], JSON_UNESCAPED_SLASHES);
}
