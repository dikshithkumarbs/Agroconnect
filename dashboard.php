<?php
require_once 'config.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$role = getUserRole();
switch ($role) {
    case 'farmer':
        header('Location: farmer/dashboard.php');
        break;
    case 'expert':
        header('Location: expert/dashboard.php');
        break;
    case 'admin':
        header('Location: admin/dashboard.php');
        break;
    default:
        header('Location: login.php');
}
exit();
?>