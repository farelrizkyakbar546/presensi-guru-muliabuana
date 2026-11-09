<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = function_exists('currentUserId')
    ? (int) currentUserId()
    : (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Sesi tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] !== 'aktif') {
        http_response_code(403);
        echo json_encode(['success' => false, 'count' => 0, 'message' => 'Pengguna tidak aktif.']);
        exit;
    }

    if ($user['role'] === 'guru') {
        ob_start();
        include __DIR__ . '/../guru/partials/notif-count.php';
        ob_end_clean();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
    $stmt->execute([$userId]);
    $count = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'count' => 0, 'message' => 'Gagal membaca notifikasi.'], JSON_UNESCAPED_UNICODE);
}
