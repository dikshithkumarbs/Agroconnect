<?php
require_once '../config.php';
require_once '../includes/functions.php';
include '../includes/header.php';
redirectIfNotRole('farmer');

$farmer_id = $_SESSION['user_id'];

// Get farmer's bookings
$stmt = $conn->prepare("
    SELECT b.id, e.name AS equipment_name, b.start_date, b.end_date, b.total_cost, b.status
    FROM bookings b
    JOIN equipment e ON b.equipment_id = e.id
    WHERE b.farmer_id = ?
    ORDER BY b.created_at DESC
");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - My Bookings</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <h2>My Bookings</h2>

    <!-- View Toggle Buttons -->
    <div class="view-toggle">
        <button id="list-view-btn" class="btn btn-secondary active">
            <i class="fas fa-list"></i> List View
        </button>
        <button id="grid-view-btn" class="btn btn-secondary">
            <i class="fas fa-th"></i> Grid View
        </button>
    </div>

    <div class="card">
        <h3>Booking History</h3>
        <?php if ($bookings->num_rows > 0): ?>
            <!-- List View (Table) -->
            <div id="booking-list-view" class="view-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $bookings->data_seek(0); // Reset pointer ?>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $booking['equipment_name']; ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></td>
                                <td>₹<?php echo number_format($booking['total_cost'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-small" onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">View Details</button>
                                    <?php if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                        <button class="btn btn-danger btn-small" onclick="cancelBooking(<?php echo $booking['id']; ?>)">Cancel Booking</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grid View (Cards) -->
            <div id="booking-grid-view" class="view-container" style="display: none;">
                <div class="equipment-grid">
                    <?php $bookings->data_seek(0); // Reset pointer ?>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <div class="equipment-card booking-card">
                            <div class="content">
                                <h4><?php echo $booking['equipment_name']; ?></h4>
                                <p><strong>Start Date:</strong> <?php echo date('M d, Y', strtotime($booking['start_date'])); ?></p>
                                <p><strong>End Date:</strong> <?php echo date('M d, Y', strtotime($booking['end_date'])); ?></p>
                                <p><strong>Total Cost:</strong> ₹<?php echo number_format($booking['total_cost'], 2); ?></p>
                                <p><strong>Status:</strong>
                                    <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </p>
                                <div class="card-actions">
                                    <button class="btn btn-small" onclick="viewBookingDetails(<?php echo $booking['id']; ?>)">View Details</button>
                                    <?php if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed'): ?>
                                        <button class="btn btn-danger btn-small" onclick="cancelBooking(<?php echo $booking['id']; ?>)">Cancel Booking</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <p>You have no bookings.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="booking-details-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeBookingDetailsModal()">&times;</span>
        <h3>Booking Details</h3>
        <div id="booking-details-content"></div>
    </div>
</div>

<style>
.view-toggle {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.view-toggle .btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background-color: #f8f9fa;
    color: #333;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.view-toggle .btn:hover {
    background-color: #e9ecef;
}

.view-toggle .btn.active {
    background-color: #2c5530;
    color: white;
    border-color: #2c5530;
}

.view-container {
    transition: opacity 0.3s ease;
}

.equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.equipment-card, .booking-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.equipment-card:hover, .booking-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.equipment-card .content, .booking-card .content {
    padding: 20px;
}

.equipment-card h4, .booking-card h4 {
    margin: 0 0 10px 0;
    color: #2c5530;
    font-size: 1.2em;
}

.equipment-card p, .booking-card p {
    margin: 8px 0;
    color: #555;
}

.card-details {
    margin: 15px 0;
}

.detail-item {
    margin: 5px 0;
    font-size: 0.9em;
}

.detail-item strong {
    color: #333;
}

.card-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.9em;
    border-radius: 4px;
}
</style>

<script>
function viewBookingDetails(bookingId) {
    fetch('../farmer/bookings/get_booking_details.php?id=' + bookingId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('booking-details-content').innerHTML = data;
            document.getElementById('booking-details-modal').style.display = 'block';
        })
        .catch(error => alert('Error fetching booking details.'));
}

function closeBookingDetailsModal() {
    document.getElementById('booking-details-modal').style.display = 'none';
}

function cancelBooking(bookingId) {
    if (confirm('Cancel this booking?')) {
        fetch('../farmer/cancel_booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + bookingId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Booking cancelled!');
                location.reload();
            } else alert('Error: ' + data.message);
        })
        .catch(error => alert('Error cancelling booking.'));
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initViewToggle('bookings');
});
</script>

<?php include '../includes/footer.php'; ?>
