<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('admin');
include '../includes/header.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $booking_id = intval($_POST['booking_id']);
        $status = sanitize($_POST['status']);

        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $booking_id);

        if ($stmt->execute()) {
            $message = '<div class="success">Booking status updated successfully!</div>';
        } else {
            $message = '<div class="error">Failed to update booking status.</div>';
        }
    }
}

// Get all bookings with user and equipment information
$stmt = $conn->prepare("
    SELECT
        b.id,
        f.name AS farmer_name,
        e.name AS equipment_name,
        b.start_date,
        b.end_date,
        b.total_cost,
        b.status
    FROM bookings b
    JOIN farmers f ON b.farmer_id = f.id
    JOIN equipment e ON b.equipment_id = e.id
    ORDER BY b.created_at DESC
");
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - Booking Management</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../farmer/bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <h2>Booking Management</h2>

    <?php echo $message; ?>

    <div class="card">
        <h3>All Bookings</h3>
        <div id="equipment-container">
            <?php if ($bookings->num_rows > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Farmer Name</th>
                            <th>Equipment</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $booking['id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['farmer_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['equipment_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></td>
                                <td>₹<?php echo number_format($booking['total_cost'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(htmlspecialchars($booking['status'])); ?>">
                                        <?php echo ucfirst(htmlspecialchars($booking['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="pending" <?php if ($booking['status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="confirmed" <?php if ($booking['status'] === 'confirmed') echo 'selected'; ?>>Confirmed</option>
                                            <option value="cancelled" <?php if ($booking['status'] === 'cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No bookings found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

</body>
</html>
