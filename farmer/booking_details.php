<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

if (!isset($_GET['id'])) {
    die('Invalid request.');
}

$booking_id = $_GET['id'];
$farmer_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT b.*, e.name AS equipment_name, e.image AS equipment_image, e.location, e.price_per_day
    FROM bookings b
    JOIN equipment e ON b.equipment_id = e.id
    WHERE b.id = ? AND b.farmer_id = ?
");
$stmt->bind_param("ii", $booking_id, $farmer_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die('Booking not found or you do not have permission to view it.');
}

// Calculate number of days
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$days = $end_date->diff($start_date)->days + 1;

if (!isset($booking['total_cost']) || is_null($booking['total_cost'])) {
    $booking['total_cost'] = $days * $booking['price_per_day'];
}

$current_date = new DateTime();
if ($booking['status'] !== 'cancelled' && $end_date < $current_date) {
    $booking['status'] = 'completed';
    $update_stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
    $update_stmt->bind_param("i", $booking_id);
    $update_stmt->execute();
    $update_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - Booking Details</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="booking-details-container">
            <div class="booking-header">
                <div class="equipment-image-container">
                    <?php
                    $imageFileName = htmlspecialchars($booking['equipment_image']);
                    $displayImagePath = "../uploads/equipment/" . $imageFileName;

                    if (!file_exists($displayImagePath) || empty($imageFileName)) {
                        $displayImagePath = "../img/placeholder.jpg";
                    }
                    ?>
                    <img src="<?php echo $displayImagePath; ?>" alt="<?php echo htmlspecialchars($booking['equipment_name']); ?>" class="equipment-detail-image">
                </div>
                <div class="equipment-info">
                    <h4><?php echo $booking['equipment_name']; ?></h4>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?></p>
                </div>
            </div>

            <div class="booking-body">
                <div class="booking-row">
                    <div class="booking-col">
                        <strong>Booking ID:</strong>
                        <p>#<?php echo $booking['id']; ?></p>
                    </div>
                    <div class="booking-col">
                        <strong>Status:</strong>
                        <p><span class="status-badge <?php echo strtolower($booking['status']); ?>"><?php echo ucfirst($booking['status']); ?></span></p>
                    </div>
                </div>
                <div class="booking-row">
                    <div class="booking-col">
                        <strong>Start Date:</strong>
                        <p><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></p>
                    </div>
                    <div class="booking-col">
                        <strong>End Date:</strong>
                        <p><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></p>
                    </div>
                </div>
                <div class="booking-row">
                    <div class="booking-col">
                        <strong>Duration:</strong>
                        <p><?php echo $days; ?> day(s)</p>
                    </div>
                    <div class="booking-col">
                        <strong>Price per Day:</strong>
                        <p>₹<?php echo number_format($booking['price_per_day'], 2); ?></p>
                    </div>
                </div>
                <div class="booking-total">
                    <strong>Total Cost:</strong>
                    <h4>₹<?php echo number_format($booking['total_cost'], 2); ?></h4>
                </div>
            </div>

            <div class="booking-actions">
                <?php if ($booking['status'] != 'cancelled' && $booking['status'] != 'completed'): ?>
                    <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)">Cancel Booking</button>
                <?php endif; ?>
                <button class="btn" onclick="window.print();">Print Details</button>
                <a href="my_bookings.php" class="btn">Back to Bookings</a>
            </div>
        </div>
    </div>

    <script>
    function cancelBooking(bookingId) {
        if (confirm('Are you sure you want to cancel this booking?')) {
            fetch('cancel_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'booking_id=' + bookingId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking cancelled successfully.');
                    window.location.href = 'my_bookings.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cancelling the booking.');
            });
        }
    }
    </script>

</body>
</html>
