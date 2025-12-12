<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get and validate input
$message_id = intval($_POST['message_id'] ?? 0);

if (!$message_id) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Delete the message
$result = deleteMessage($message_id, $user_id, $user_role);

echo json_encode($result);
?>