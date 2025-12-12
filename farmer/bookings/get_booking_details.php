<?php
require_once '../../config.php';
require_once '../../includes/functions.php';

if (!isset($_GET['id'])) {
    die('Invalid request.');
}

$booking_id = $_GET['id'];

$stmt = $conn->prepare("
    SELECT b.*, e.name AS equipment_name, e.image AS equipment_image, e.location, e.price_per_day
    FROM bookings b
    JOIN equipment e ON b.equipment_id = e.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die('Booking not found.');
}

// Calculate number of days
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$days = $end_date->diff($start_date)->days + 1;

// Ensure total_cost is available
if (!isset($booking['total_cost']) || is_null($booking['total_cost'])) {
    $booking['total_cost'] = $days * $booking['price_per_day'];
}

// Check if booking should be marked as completed
$current_date = new DateTime();
if ($booking['status'] !== 'cancelled' && $end_date < $current_date) {
    $booking['status'] = 'completed';
    // Update status in the database
    $update_stmt = $conn->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
    $update_stmt->bind_param("i", $booking_id);
    $update_stmt->execute();
    $update_stmt->close();
}

?>

<div class="booking-details-container" style="font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 8px; max-width: 600px; margin: 20px auto; background-color: #fff;">
    <div class="booking-header" style="display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
        <div class="equipment-image-container" style="flex-shrink: 0; margin-right: 20px;">
            <?php
            $imageFileName = htmlspecialchars($booking['equipment_image']);
            $displayImagePath = "/T(OG)/uploads/equipment/" . $imageFileName;

            // Fallback to a placeholder if the specific image doesn't exist
            if (!file_exists($_SERVER['DOCUMENT_ROOT'] . $displayImagePath) || empty($imageFileName)) {
                $displayImagePath = "/T(OG)/img/placeholder.jpg"; // Ensure you have a placeholder image
            }
            ?>
            <img src="<?php echo $displayImagePath; ?>" alt="<?php echo htmlspecialchars($booking['equipment_name']); ?>" class="equipment-detail-image" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd;">
        </div>
        <div class="equipment-info" style="flex-grow: 1;">
            <h4 style="margin: 0 0 5px 0; color: #333; font-size: 1.5em;"><?php echo $booking['equipment_name']; ?></h4>
            <p style="margin: 0; color: #666; font-size: 0.9em;"><strong>Location:</strong> <?php echo htmlspecialchars($booking['location']); ?></p>
        </div>
    </div>

    <div class="booking-body" style="margin-bottom: 20px;">
        <div class="booking-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">Booking ID:</strong>
                <p style="margin: 5px 0 0 0; font-size: 1.1em; color: #333;">#<?php echo $booking['id']; ?></p>
            </div>
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">Status:</strong>
                <p style="margin: 5px 0 0 0;"><span class="status-badge <?php echo strtolower($booking['status']); ?>" style="padding: 5px 10px; border-radius: 5px; font-weight: bold; color: #fff; background-color: #007bff; /* Default */ <?php
                    if ($booking['status'] === 'pending') echo 'background-color: #ffc107;';
                    else if ($booking['status'] === 'confirmed') echo 'background-color: #28a745;';
                    else if ($booking['status'] === 'cancelled') echo 'background-color: #dc3545;';
                    else if ($booking['status'] === 'completed') echo 'background-color: #6c757d;';
                ?>"><?php echo ucfirst($booking['status']); ?></span></p>
            </div>
        </div>
        <div class="booking-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">Start Date:</strong>
                <p style="margin: 5px 0 0 0; color: #333;"><?php echo date('M d, Y', strtotime($booking['start_date'])); ?></p>
            </div>
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">End Date:</strong>
                <p style="margin: 5px 0 0 0; color: #333;"><?php echo date('M d, Y', strtotime($booking['end_date'])); ?></p>
            </div>
        </div>
        <div class="booking-row" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">Duration:</strong>
                <p style="margin: 5px 0 0 0; color: #333;"><?php echo $days; ?> day(s)</p>
            </div>
            <div class="booking-col" style="flex: 1; min-width: 48%;">
                <strong style="color: #555;">Price per Day:</strong>
                <p style="margin: 5px 0 0 0; color: #333;">₹<?php echo number_format($booking['price_per_day'], 2); ?></p>
            </div>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <div class="booking-total" style="text-align: right; margin-top: 20px;">
            <strong style="color: #333; font-size: 1.2em;">Total Cost:</strong>
            <h4 style="margin: 5px 0 0 0; color: #007bff; font-size: 1.8em;">₹<?php echo number_format($booking['total_cost'], 2); ?></h4>
        </div>
    </div>

    <div class="booking-actions" style="text-align: center; padding-top: 20px; border-top: 1px solid #eee;">
        <?php if ($booking['status'] != 'cancelled' && $booking['status'] != 'completed'): ?>
            <button class="btn btn-danger" onclick="cancelBooking(<?php echo $booking['id']; ?>)" style="background-color: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em; margin-right: 10px;">Cancel Booking</button>
        <?php endif; ?>
        <button class="btn" onclick="window.print();" style="background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; font-size: 1em;">Print Details</button>
    </div>
</div>
