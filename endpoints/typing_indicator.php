<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'error' => '', 'is_typing' => false];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['error'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$other_id = isset($_REQUEST['other_id']) ? intval($_REQUEST['other_id']) : 0;

if ($other_id === 0) {
    $response['error'] = 'Other user ID is required.';
    echo json_encode($response);
    exit();
}

if ($action === 'set_typing') {
    $is_typing = isset($_POST['is_typing']) ? (bool)$_POST['is_typing'] : false;
    $result = setTypingStatus($user_id, $user_role, $other_id, $is_typing);
    if ($result) {
        $response['success'] = true;
    } else {
        $response['error'] = 'Failed to update typing status.';
    }
} elseif ($action === 'get_typing') {
    $is_typing = getTypingStatus($user_id, $user_role, $other_id);
    $response['success'] = true;
    $response['is_typing'] = $is_typing;
    
    // Get the name of the user who is typing
    if ($is_typing) {
        // Determine the role of the other user
        if ($user_role === 'farmer') {
            $other_role = 'expert';
        } else {
            $other_role = 'farmer';
        }
        
        $other_name = getUserNameById($other_id, $other_role);
        $response['typing_user'] = $other_name;
    }
} else {
    $response['error'] = 'Invalid action.';
}

echo json_encode($response);
?>