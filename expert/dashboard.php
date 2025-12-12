<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('expert');

$user_id = $_SESSION['user_id'];

// Get dashboard statistics using the new function
$dashboard_stats = getExpertDashboardStats($user_id);
$farmer_count = $dashboard_stats['farmer_count'];
$pending_reports = $dashboard_stats['pending_reports'];
$unread_messages = $dashboard_stats['unread_messages'];

// Get unread notifications
$unread_count = getUnreadNotificationsCount($user_id, 'expert');

include '../includes/header.php';
?>

<div class="container">
    <h2>Expert Dashboard</h2>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-users"></i> Total Farmers</h3>
            <div class="stat"><?php echo $farmer_count; ?></div>
            <p>Registered farmers</p>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-flask"></i> Pending Reports</h3>
            <div class="stat"><?php echo $pending_reports; ?></div>
            <p>Awaiting review</p>
            <a href="advisory.php" class="btn">Review Now</a>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-comments"></i> Messages</h3>
            <div class="stat"><?php echo $unread_messages; ?></div>
            <p>Unread messages</p>
            <a href="chat.php" class="btn">View Chat</a>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-clipboard-list"></i> Recommendations</h3>
            <div class="stat"><?php echo $dashboard_stats['recommendations_count']; ?></div>
            <p>Given by you</p>
        </div>
    </div>

    <div class="card">
        <h3>Recent Soil Reports</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT sr.*, f.name as farmer_name 
            FROM soil_reports sr 
            JOIN farmers f ON sr.farmer_id = f.id
            JOIN expert_farmer_assignments efa ON sr.farmer_id = efa.farmer_id
            WHERE efa.expert_id = ? AND efa.status = 'active'
            ORDER BY sr.uploaded_at DESC 
            LIMIT 5
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $reports = $stmt->get_result();
        ?>
        
        <?php if ($reports->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($report = $reports->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['farmer_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($report['uploaded_at'])); ?></td>
                            <td><?php echo ucfirst($report['status']); ?></td>
                            <td>
                                <a href="advisory.php?report_id=<?php echo $report['id']; ?>" class="btn">View</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No soil reports found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>