<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('admin');

$user_id = $_SESSION['user_id'];

// Get dashboard statistics using the new function
$dashboard_stats = getAdminDashboardStats();
$farmer_count = $dashboard_stats['farmer_count'];
$expert_count = $dashboard_stats['expert_count'];
$equipment_count = $dashboard_stats['equipment_count'];
$booking_count = $dashboard_stats['booking_count'];
$revenue = $dashboard_stats['revenue'];

// Handle user actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_user'])) {
        $user_id = intval($_POST['user_id']);
        $role = sanitize($_POST['role']); // Assume role is passed
        $table = $role === 'farmer' ? 'farmers' : 'experts';
        $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = '<div class="success">User deleted successfully!</div>';
        } else {
            $message = '<div class="error">Failed to delete user.</div>';
        }
    } elseif (isset($_POST['approve_expert'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("UPDATE experts SET status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Expert approved successfully!</div>';
        } else {
            $message = '<div class="error">Failed to approve expert.</div>';
        }
    } elseif (isset($_POST['reject_expert'])) {
        $user_id = intval($_POST['user_id']);
        $stmt = $conn->prepare("DELETE FROM experts WHERE id = ? AND status = 'inactive'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Expert application rejected and user removed.</div>';
        } else {
            $message = '<div class="error">Failed to reject expert.</div>';
        }
    }
}

// Get recent activities
$stmt = $conn->prepare("SELECT 'New farmer registered' as activity, name as details, created_at FROM farmers ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$activities = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container">
    <h2>Admin Dashboard</h2>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-users"></i> Total Farmers</h3>
            <div class="stat"><?php echo $farmer_count; ?></div>
            <p>Registered farmers</p>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-user-tie"></i> Total Experts</h3>
            <div class="stat"><?php echo $expert_count; ?></div>
            <p>Agricultural experts</p>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-tractor"></i> Equipment</h3>
            <div class="stat"><?php echo $equipment_count; ?></div>
            <p>Available items</p>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-shopping-cart"></i> Bookings</h3>
            <div class="stat"><?php echo $booking_count; ?></div>
            <p>Total bookings</p>
        </div>

        <div class="dashboard-card">
            <h3><i class="fas fa-rupee-sign"></i> Revenue</h3>
            <div class="stat">₹<?php echo number_format($revenue, 2); ?></div>
            <p>From completed bookings</p>
        </div>



        <div class="dashboard-card">
            <h3><i class="fas fa-tractor"></i> Top Equipment</h3>
            <div class="stat">
                <?php
                $stmt = $conn->prepare("
                    SELECT e.name, COUNT(b.id) as booking_count
                    FROM equipment e
                    LEFT JOIN bookings b ON e.id = b.equipment_id
                    GROUP BY e.id, e.name
                    ORDER BY booking_count DESC
                    LIMIT 1
                ");
                $stmt->execute();
                $top_equipment = $stmt->get_result()->fetch_assoc();
                echo $top_equipment ? htmlspecialchars($top_equipment['name']) : 'N/A';
                ?>
            </div>
            <p>Most Booked Equipment</p>
            <div class="trend-chart">
                <small><?php echo $top_equipment ? $top_equipment['booking_count'] . ' bookings' : 'No bookings'; ?></small>
            </div>
        </div>
    </div>

    

    <div class="card">
        <h3></h3>Recent Activities</h3>
        <?php if ($activities->num_rows > 0): ?>
            <ul>
                <?php while ($activity = $activities->fetch_assoc()): ?>
                    <li><?php echo $activity['activity']; ?>: <?php echo $activity['details']; ?> (<?php echo date('M d, Y', strtotime($activity['created_at'])); ?>)</li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p>No recent activities.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Farmer Modal -->
<div id="assignFarmerModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close" onclick="closeAssignFarmerModal()">&times;</span>
        <h3>Assign Farmer to <span id="expertName"></span></h3>
        <form method="POST" id="assignFarmerForm">
            <input type="hidden" name="expert_id" id="expertIdInput">
            <div class="form-group">
                <label for="farmer_id">Select Farmer:</label>
                <select name="farmer_id" id="farmer_id" required>
                    <option value="">Choose a farmer...</option>
                    <?php
                    $stmt = $conn->prepare("
                        SELECT f.id, f.name, f.location 
                        FROM farmers f
                        WHERE NOT EXISTS (
                            SELECT 1 FROM expert_farmer_assignments efa 
                            WHERE efa.farmer_id = f.id 
                            AND efa.status = 'active'
                        )
                        ORDER BY f.location, f.name
                    ");
                    $stmt->execute();
                    $available_farmers = $stmt->get_result();
                    while ($farmer = $available_farmers->fetch_assoc()): ?>
                        <option value="<?php echo $farmer['id']; ?>">
                            <?php echo htmlspecialchars($farmer['name']); ?> (<?php echo htmlspecialchars($farmer['location']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="notes">Notes:</label>
                <textarea name="notes" id="notes" rows="3"></textarea>
            </div>
            <button type="submit" name="assign_farmer" class="btn">Assign Farmer</button>
        </form>
    </div>
</div>

<style>
.farmer-list {
    list-style: none;
    padding: 0;
    margin: 10px 0;
    max-height: 150px;
    overflow-y: auto;
}

.farmer-list li {
    padding: 5px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.assigned-farmers {
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 50%;
    border-radius: 5px;
    position: relative;
}

.close {
    position: absolute;
    right: 10px;
    top: 5px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
</style>

<script>
function showAssignFarmerModal(expertId, expertName) {
    document.getElementById('expertIdInput').value = expertId;
    document.getElementById('expertName').textContent = expertName;
    document.getElementById('assignFarmerModal').style.display = 'block';
}

function closeAssignFarmerModal() {
    document.getElementById('assignFarmerModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('assignFarmerModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>

<script src="../js/admin-dashboard.js"></script>
<?php include '../includes/footer.php'; ?>