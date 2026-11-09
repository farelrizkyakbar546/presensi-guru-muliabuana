<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login.php');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    flash('error', 'Username dan password wajib diisi.');
    redirect('../login.php');
}

if (strlen($username) > 50) {
    flash('error', 'Username atau password salah.');
    redirect('../login.php');
}

$stmt = $pdo->prepare("SELECT id, username, password, nama_lengkap, role, status FROM users WHERE username = :username LIMIT 1");
$stmt->execute(['username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password'])) {
    flash('error', 'Username atau password salah.');
    redirect('../login.php');
}

if ($user['status'] !== 'aktif') {
    flash('error', 'Akun kamu sedang tidak aktif.');
    redirect('../login.php');
}

$roleValid = ['adminTu', 'guru', 'kepsek'];
if (!in_array($user['role'], $roleValid, true)) {
    flash('error', 'Role akun tidak dikenali.');
    redirect('../login.php');
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['role'] = $user['role'];

switch ($user['role']) {
    case 'guru':
        redirect('../guru/dashboard.php');
        break;
    case 'adminTu':
        redirect('../admintu/dashboard.php');
        break;
    case 'kepsek':
        redirect('../kepsek/dashboard.php');
        break;
    default:
        session_unset();
        session_destroy();
        flash('error', 'Role akun tidak dikenali.');
        redirect('../login.php');
}