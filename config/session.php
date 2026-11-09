<?php

if (session_status() === PHP_SESSION_NONE) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $hostOnly = explode(':', $host)[0];

    $isLocal = (
        $hostOnly === 'localhost' ||
        $hostOnly === '127.0.0.1' ||
        filter_var($hostOnly, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
    );

    $isHttps = (
        !$isLocal && (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        )
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit;
    }
}

function requireRole($role)
{
    requireLogin();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}

function requireAnyRole(array $roles)
{
    requireLogin();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}

function currentUserId()
{
    return $_SESSION['user_id'] ?? null;
}

function currentUsername()
{
    return $_SESSION['username'] ?? null;
}

function currentUserFullName()
{
    return $_SESSION['nama_lengkap'] ?? null;
}

function currentRole()
{
    return $_SESSION['role'] ?? null;
}