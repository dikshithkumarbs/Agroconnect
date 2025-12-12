<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header('Content-Type: application/json');
require_once '../../config.php';
require_once '../../includes/functions.php';
include '../includes/header.php';

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Please log in to book equipment.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_id = intval($_POST['equipment_id']);
    $start_date = sanitize($_POST['start_date']);
    $end_date = sanitize($_POST['end_date']);

    // Calculate cost
    $cost = calculateBookingCost($equipment_id, $start_date, $end_date);

    if ($cost > 0) {
        $stmt = $conn->prepare("INSERT INTO bookings (farmer_id, equipment_id, start_date, end_date, total_cost) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iissd", $user_id, $equipment_id, $start_date, $end_date, $cost);

        if ($stmt->execute()) {
            // Send notification to admin (user_role = 'admin')
            sendNotification(1, 'admin', 'New Equipment Booking', 'A new equipment booking has been made.', 'info'); // Assuming admin ID is 1
            $response['success'] = true;
            $response['message'] = 'Booking successful!';
        } else {
            $response['message'] = 'Booking failed!';
        }
    } else {
        $response['message'] = 'Invalid booking dates!';
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>