<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('admin');

// Get analytics data
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM farmers");
$stmt->execute();
$total_farmers = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM experts");
$stmt->execute();
$total_experts = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM soil_reports");
$stmt->execute();
$total_reports = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings");
$stmt->execute();
$total_bookings = $stmt->get_result()->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT SUM(total_cost) as revenue FROM bookings WHERE status = 'completed'");
$stmt->execute();
$total_revenue = $stmt->get_result()->fetch_assoc()['revenue'] ?: 0;

// Monthly data for charts (simplified)
$monthly_farmers = [];
$monthly_bookings = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM farmers WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $monthly_farmers[] = $stmt->get_result()->fetch_assoc()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $monthly_bookings[] = $stmt->get_result()->fetch_assoc()['count'];
}

include '../includes/header.php';
?>

<div class="container">
    <h2>Analytics Dashboard</h2>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-users"></i> Total Farmers</h3>
            <div class="stat"><?php echo $total_farmers; ?></div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-user-tie"></i> Total Experts</h3>
            <div class="stat"><?php echo $total_experts; ?></div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-flask"></i> Soil Reports</h3>
            <div class="stat"><?php echo $total_reports; ?></div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-shopping-cart"></i> Total Bookings</h3>
            <div class="stat"><?php echo $total_bookings; ?></div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-rupee-sign"></i> Total Revenue</h3>
            <div class="stat">₹<?php echo number_format($total_revenue, 2); ?></div>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> Avg Booking Value</h3>
            <div class="stat">₹<?php echo $total_bookings > 0 ? number_format($total_revenue / $total_bookings, 2) : '0.00'; ?></div>
        </div>
    </div>

    <div class="card">
        <h3>Monthly Trends</h3>
        <div style="display: flex; justify-content: space-around; margin-top: 20px;">
            <div>
                <h4>New Farmers (Last 6 Months)</h4>
                <canvas id="farmersChart" width="300" height="200"></canvas>
            </div>
            <div>
                <h4>Bookings (Last 6 Months)</h4>
                <canvas id="bookingsChart" width="300" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <h3>Top Equipment</h3>
        <?php
        $stmt = $conn->prepare("SELECT e.name, COUNT(b.id) as booking_count FROM equipment e LEFT JOIN bookings b ON e.id = b.equipment_id GROUP BY e.id ORDER BY booking_count DESC LIMIT 5");
        $stmt->execute();
        $top_equipment = $stmt->get_result();
        ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Bookings</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $top_equipment->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $item['name']; ?></td>
                        <td><?php echo $item['booking_count']; ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const farmersData = <?php echo json_encode($monthly_farmers); ?>;
const bookingsData = <?php echo json_encode($monthly_bookings); ?>;

const farmersCtx = document.getElementById('farmersChart').getContext('2d');
new Chart(farmersCtx, {
    type: 'line',
    data: {
        labels: ['6 months ago', '5 months ago', '4 months ago', '3 months ago', '2 months ago', 'Last month'],
        datasets: [{
            label: 'New Farmers',
            data: farmersData,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    }
});

const bookingsCtx = document.getElementById('bookingsChart').getContext('2d');
new Chart(bookingsCtx, {
    type: 'bar',
    data: {
        labels: ['6 months ago', '5 months ago', '4 months ago', '3 months ago', '2 months ago', 'Last month'],
        datasets: [{
            label: 'Bookings',
            data: bookingsData,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    }
});
</script>

<?php include '../includes/footer.php'; ?>
