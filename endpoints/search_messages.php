<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'messages' => [], 'error' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['error'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$other_id = isset($_GET['other_id']) ? intval($_GET['other_id']) : 0;
$search_term = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

if ($other_id === 0) {
    $response['error'] = 'Other user ID is required.';
    echo json_encode($response);
    exit();
}

if (empty($search_term)) {
    $response['error'] = 'Search term is required.';
    echo json_encode($response);
    exit();
}

// Search messages
$search_result = searchMessages($user_id, $user_role, $other_id, $search_term, $limit);

if ($search_result['success']) {
    $response['success'] = true;
    $response['messages'] = $search_result['messages'];
} else {
    $response['error'] = $search_result['error'];
}

echo json_encode($response);
?>
