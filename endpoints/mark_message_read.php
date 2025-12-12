<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['error'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $message_id = isset($input['message_id']) ? intval($input['message_id']) : 0;
    
    if ($message_id === 0) {
        $response['error'] = 'Message ID is required.';
        echo json_encode($response);
        exit();
    }
    
    // Mark the message as read
    $result = markMessageAsRead($message_id, $user_id, $user_role);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = 'Message marked as read.';
    } else {
        $response['error'] = 'Failed to mark message as read.';
    }
} else {
    $response['error'] = 'Invalid request method.';
}

echo json_encode($response);
?>