<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_status':
        // Get connection status for a specific user
        $other_id = intval($_GET['other_id'] ?? 0);
        if (!$other_id) {
            echo json_encode(['error' => 'Other user ID required']);
            exit;
        }

        // Check if users are connected (expert-farmer pair)
        $is_connected = isValidExpertFarmerPair($user_id, $other_id) || isValidExpertFarmerPair($other_id, $user_id);

        if (!$is_connected) {
            echo json_encode(['error' => 'Users are not connected']);
            exit;
        }

        // Check if the other user is online (has been active in last 5 minutes)
        // Determine the role of the other user based on the current user's role
        $other_role = $user_role === 'farmer' ? 'expert' : 'farmer';
        $stmt = $conn->prepare("
            SELECT last_activity, is_online
            FROM user_activity
            WHERE user_id = ? AND user_role = ?
        ");
        $stmt->bind_param("is", $other_id, $other_role);
        $stmt->execute();
        $result = $stmt->get_result();
        $activity = $result->fetch_assoc();

        $is_online = false;
        if ($activity) {
            $last_activity = strtotime($activity['last_activity']);
            $is_online = $activity['is_online'] && (time() - $last_activity) < 300; // 5 minutes
        }

        echo json_encode([
            'success' => true,
            'is_online' => $is_online,
            'last_seen' => $activity ? $activity['last_activity'] : null
        ]);
        break;

    case 'update_activity':
        // Update user's last activity using the function we defined
        updateUserActivity($user_id, $user_role, true);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>