<?php

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $token = $_GET['token'] ?? '';

    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        echo json_encode(['success' => false, 'used' => false, 'expired' => false]);
        exit;
    }

    $tokenHash = hash('sha256', $token);
    $sekarang = new DateTime('now', new DateTimeZone('Asia/Jakarta'));

    $stmt = $pdo->prepare("SELECT digunakan_at, berlaku_sampai FROM qr_presensi WHERE token_hash = :token_hash LIMIT 1");
    $stmt->execute(['token_hash' => $tokenHash]);
    $qr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$qr) {
        echo json_encode(['success' => true, 'used' => true, 'expired' => true]);
        exit;
    }

    $expired = ($sekarang->format('Y-m-d H:i:s') >= $qr['berlaku_sampai']);
    $used = !empty($qr['digunakan_at']);

    echo json_encode([
        'success' => true,
        'used'    => $used,
        'expired' => $expired
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'used'    => false,
        'expired' => false,
        'message' => 'Gagal memeriksa status QR.'
    ]);
}