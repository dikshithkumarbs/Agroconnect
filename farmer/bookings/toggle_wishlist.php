<?php
require_once '../../config.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to manage wishlist.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$equipment_id = intval($_POST['equipment_id']);
$remove = isset($_POST['remove']) && $_POST['remove'] === 'true';

if ($remove) {
    // Remove from wishlist
    $stmt = $conn->prepare("DELETE FROM wishlist WHERE farmer_id = ? AND equipment_id = ?");
    $stmt->bind_param("ii", $user_id, $equipment_id);
    $success = $stmt->execute();
    $action = 'removed';
} else {
    // Check if already in wishlist
    $stmt = $conn->prepare("SELECT id FROM wishlist WHERE farmer_id = ? AND equipment_id = ?");
    $stmt->bind_param("ii", $user_id, $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Remove from wishlist
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE farmer_id = ? AND equipment_id = ?");
        $stmt->bind_param("ii", $user_id, $equipment_id);
        $success = $stmt->execute();
        $action = 'removed';
    } else {
        // Add to wishlist
        $stmt = $conn->prepare("INSERT INTO wishlist (farmer_id, equipment_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $equipment_id);
        $success = $stmt->execute();
        $action = 'added';
    }
}

if ($success) {
    // Check if the item is now in the wishlist
    $stmt = $conn->prepare("SELECT id FROM wishlist WHERE farmer_id = ? AND equipment_id = ?");
    $stmt->bind_param("ii", $user_id, $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $isWishlisted = $result->num_rows > 0;
    
    echo json_encode(['success' => true, 'action' => $action, 'wishlisted' => $isWishlisted]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating wishlist.']);
}
?>
