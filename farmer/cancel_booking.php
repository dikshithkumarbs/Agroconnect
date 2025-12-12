<?php
error_log("cancel_booking.php: Script started.");
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

error_log("cancel_booking.php: REQUEST_METHOD = " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['booking_id'])) {
        $response['message'] = 'Invalid request: Booking ID missing.';
        error_log("cancel_booking.php: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    $booking_id = $_POST['booking_id'];
    $farmer_id = $_SESSION['user_id'];

    error_log("cancel_booking.php: Booking ID = " . $booking_id . ", Farmer ID = " . $farmer_id);

    // Check if the booking belongs to the current farmer and is not already cancelled or completed
    $stmt = $conn->prepare("SELECT status FROM bookings WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $booking_id, $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if (!$booking) {
        $response['message'] = 'Booking not found or you do not have permission to cancel it.';
        error_log("cancel_booking.php: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    error_log("cancel_booking.php: Current booking status = " . $booking['status']);

    if ($booking['status'] === 'cancelled') {
        $response['message'] = 'Booking is already cancelled.';
        error_log("cancel_booking.php: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    if ($booking['status'] === 'completed') {
        $response['message'] = 'Completed bookings cannot be cancelled.';
        error_log("cancel_booking.php: " . $response['message']);
        echo json_encode($response);
        exit();
    }

    // Update booking status to 'cancelled'
    $update_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND farmer_id = ?");
    $update_stmt->bind_param("ii", $booking_id, $farmer_id);

    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Booking cancelled successfully.';
            error_log("cancel_booking.php: Booking " . $booking_id . " cancelled successfully.");
        } else {
            $response['message'] = 'Booking not found or no changes were made.';
            error_log("cancel_booking.php: " . $response['message']);
        }
    } else {
        $response['message'] = 'Database error: ' . $conn->error;
        error_log("cancel_booking.php: Database error: " . $conn->error);
    }

    $update_stmt->close();
    $stmt->close();
}

$conn->close();

echo json_encode($response);
error_log("cancel_booking.php: Script finished.");
?>