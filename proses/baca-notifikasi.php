<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Silakan login terlebih dahulu.');
}

$userId = (int) $_SESSION['user_id'];
$role = (string) ($_SESSION['role'] ?? '');

function redirectByRole(string $role): void {
    if ($role === 'guru') {
        header('Location: ../guru/notifikasi.php');
        exit;
    }
    if ($role === 'adminTu') {
        header('Location: ../admintu/perizinan.php');
        exit;
    }
    if ($role === 'kepsek') {
        header('Location: ../kepsek/perizinan.php');
        exit;
    }
    header('Location: ../login.php');
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['all']) && $_GET['all'] === '1') {
            $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1, dibaca_at = NOW() WHERE user_id = ? AND dibaca = 0");
            $stmt->execute([$userId]);
            redirectByRole($role);
        }
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Metode tidak diizinkan.');
    }

    $notifikasiId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$notifikasiId || $notifikasiId < 1) {
        http_response_code(400);
        exit('ID notifikasi tidak valid.');
    }

    $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1, dibaca_at = NOW() WHERE id = ? AND user_id = ? AND dibaca = 0");
    $stmt->execute([$notifikasiId, $userId]);
    redirectByRole($role);

} catch (Throwable $e) {
    http_response_code(500);
    exit('Gagal memperbarui status notifikasi.');
}
