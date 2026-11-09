<?php

require_once __DIR__ . '/config/session.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

switch ($_SESSION['role']) {
    case 'adminTu':
        header('Location: admintu/dashboard.php');
        break;
    case 'guru':
        header('Location: guru/dashboard.php');
        break;
    case 'kepsek':
        header('Location: kepsek/dashboard.php');
        break;
    default:
        header('Location: login.php');
}
exit;
