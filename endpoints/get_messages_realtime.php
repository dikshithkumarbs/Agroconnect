<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

// Only allow AJAX requests
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header('HTTP/1.0 403 Forbidden');
    exit('Access forbidden');
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access forbidden');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

header('Content-Type: application/json');

$other_id = intval($_GET['other_id'] ?? 0);
$last_message_id = intval($_GET['last_message_id'] ?? 0);

if (!$other_id) {
    echo json_encode(['success' => false, 'error' => 'Other user ID required']);
    exit;
}

// Validate the chat participants
if ($user_role === 'farmer') {
    if (!isValidExpertFarmerPair($other_id, $user_id)) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to view this chat']);
        exit;
    }
} else if ($user_role === 'expert') {
    if (!isValidExpertFarmerPair($user_id, $other_id)) {
        echo json_encode(['success' => false, 'error' => 'Not authorized to view this chat']);
        exit;
    }
}

// Get only new messages since last_message_id
$result = getChatMessagesPaginated($user_id, $user_role, $other_id, $last_message_id, 50);

if ($result['success']) {
    // Update user activity
    updateUserActivity($user_id, $user_role);
    
    echo json_encode([
        'success' => true,
        'messages' => $result['messages'],
        'has_more' => $result['has_more'] ?? false
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $result['error']]);
}
?>