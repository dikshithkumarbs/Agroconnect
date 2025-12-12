<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

$user_id = $_SESSION['user_id'];

// Get dashboard statistics using the new function
$dashboard_stats = getFarmerDashboardStats($user_id);
$assigned_expert = $dashboard_stats['assigned_expert'];
$soil_reports = $dashboard_stats['soil_reports'];
$bookings = $dashboard_stats['bookings'];

// Get unread notifications
$unread_count = getUnreadNotificationsCount($user_id, 'farmer');

include '../includes/header.php';
?>

<div class="container">
    <h2>Farmer Dashboard</h2>

    <div class="dashboard-grid">
        <!-- Weather Widget -->
        <div class="dashboard-card">
            <h3><i class="fas fa-cloud-sun"></i> Weather</h3>
            <div id="weather-widget">
                <p>Loading weather data...</p>
            </div>
        </div>

        <!-- Expert Chat Card -->
        <div class="dashboard-card">
            <h3><i class="fas fa-user-tie"></i> Agricultural Expert</h3>
            <?php if ($assigned_expert): ?>
                <div class="expert-info">
                    <h4><?php echo htmlspecialchars($assigned_expert['name']); ?></h4>
                    <p><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($assigned_expert['specialization']); ?></p>
                    <?php if ($assigned_expert['unread_messages'] > 0): ?>
                        <div class="unread-badge"><?php echo $assigned_expert['unread_messages']; ?> new messages</div>
                    <?php endif; ?>
                    <a href="chat.php" class="btn btn-primary">
                        <i class="fas fa-comments"></i> Open Chat
                    </a>
                </div>
            <?php else: ?>
                <div class="no-expert">
                    <p>No expert assigned yet.</p>
                    <p class="text-muted">Please contact administration to get assigned to an agricultural expert.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-leaf"></i> Crop Advisory</h3>
            <div class="stat"><?php echo $soil_reports->num_rows; ?></div>
            <p>Soil reports uploaded</p>
            <a href="crop_advisory.php" class="btn">Get Recommendations</a>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-tractor"></i> Equipment</h3>
            <div class="stat"><?php echo $bookings->num_rows; ?></div>
            <p>Active bookings</p>
            <a href="equipment.php" class="btn">Browse Equipment</a>
        </div>
    </div>

    <div class="card">
        <h3>Recent Soil Reports</h3>
        <?php if ($soil_reports->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>File</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($report = $soil_reports->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($report['uploaded_at'])); ?></td>
                            <td>
                                <?php if ($report['report_file']): ?>
                                    <a href="../uploads/<?php echo $report['report_file']; ?>" target="_blank">View</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="crop_recommendation.php?report_id=<?php echo $report['id']; ?>" class="btn">View Recommendations</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No soil reports found. <a href="crop_advisory.php">Upload your first report</a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Bookings</h3>
        <?php if ($bookings->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Equipment</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Status</th>
                        <th>Cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $booking['equipment_name']; ?></td>
                            <td><?php echo $booking['start_date']; ?></td>
                            <td><?php echo $booking['end_date']; ?></td>
                            <td><?php echo ucfirst($booking['status']); ?></td>
                            <td>₹<?php echo number_format($booking['total_cost'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No bookings found. <a href="equipment.php">Browse equipment</a></p>
        <?php endif; ?>
    </div>
</div>

<script>
// Update weather widget when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadWeather();
});
</script>

<?php include '../includes/footer.php'; ?>