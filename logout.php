<?php
require_once 'config.php';

// Clear all session variables
$_SESSION = array();

// Destroy session
session_destroy();

// Redirect to login page
header('Location: index.php');
exit();
?>
