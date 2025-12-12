<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$other_id = intval($_POST['other_id'] ?? 0);
if (!$other_id) {
    echo json_encode(['error' => 'Other user ID required']);
    exit;
}

// Validate the chat participants
if ($user_role === 'farmer') {
    if (!isValidExpertFarmerPair($other_id, $user_id)) {
        echo json_encode(['error' => 'Not authorized to mark messages as read']);
        exit;
    }
} else if ($user_role === 'expert') {
    if (!isValidExpertFarmerPair($user_id, $other_id)) {
        echo json_encode(['error' => 'Not authorized to mark messages as read']);
        exit;
    }
}

// Mark messages as read
$stmt = $conn->prepare("
    UPDATE messages
    SET read_at = NOW()
    WHERE sender_id = ? AND receiver_id = ? AND read_at IS NULL
");
$stmt->bind_param("ii", $other_id, $user_id);
$success = $stmt->execute();

if ($success) {
    echo json_encode([
        'success' => true,
        'marked_count' => $stmt->affected_rows
    ]);
} else {
    echo json_encode(['error' => 'Failed to mark messages as read']);
}
?>