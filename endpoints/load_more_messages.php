<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'messages' => [], 'error' => '', 'has_more' => false];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['error'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$other_id = isset($_GET['other_id']) ? intval($_GET['other_id']) : 0;
$before_message_id = isset($_GET['before_message_id']) ? intval($_GET['before_message_id']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20; // Load 20 more messages

if ($other_id === 0) {
    $response['error'] = 'Other user ID is required.';
    echo json_encode($response);
    exit();
}

if ($before_message_id === 0) {
    $response['error'] = 'Before message ID is required.';
    echo json_encode($response);
    exit();
}

// Fetch older messages with pagination
$chat_result = getChatMessagesPaginated($user_id, $user_role, $other_id, 0, $limit, $before_message_id);

if ($chat_result['success']) {
    $response['success'] = true;
    $response['messages'] = $chat_result['messages'];
    $response['has_more'] = $chat_result['has_more'];
} else {
    $response['error'] = $chat_result['error'];
}

echo json_encode($response);
?>