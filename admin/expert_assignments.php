<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('admin');

$message = '';

// Handle assignment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_farmer'])) {
        $expert_id = intval($_POST['expert_id']);
        $farmer_id = intval($_POST['farmer_id']);
        $notes = sanitize($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("INSERT INTO expert_farmer_assignments (expert_id, farmer_id, notes) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status='active', notes=VALUES(notes)");
        $stmt->bind_param("iis", $expert_id, $farmer_id, $notes);
        if ($stmt->execute()) {
            $message = '<div class="success">Farmer assigned to expert successfully!</div>';
        } else {
            $message = '<div class="error">Failed to assign farmer to expert.</div>';
        }
    } elseif (isset($_POST['unassign_farmer'])) {
        $expert_id = intval($_POST['expert_id']);
        $farmer_id = intval($_POST['farmer_id']);
        
        $stmt = $conn->prepare("UPDATE expert_farmer_assignments SET status='inactive' WHERE expert_id=? AND farmer_id=?");
        $stmt->bind_param("ii", $expert_id, $farmer_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Assignment removed successfully!</div>';
        } else {
            $message = '<div class="error">Failed to remove assignment.</div>';
        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h2><i class="fas fa-user-tie"></i> Expert-Farmer Assignments</h2>
    
    <?php
    // Get all active experts with their assigned farmers
    $stmt = $conn->prepare("
        SELECT e.id as expert_id, e.name as expert_name, e.location as expert_location,
               e.specialization, e.experience_years,
               COUNT(DISTINCT efa.farmer_id) as assigned_farmers
        FROM experts e
        LEFT JOIN expert_farmer_assignments efa ON e.id = efa.expert_id AND efa.status = 'active'
        WHERE e.status = 'active'
        GROUP BY e.id
        ORDER BY e.name
    ");
    $stmt->execute();
    $experts = $stmt->get_result();
    ?>

    <div class="expert-assignments">
        <?php echo $message; ?>
        
        <?php if ($experts->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Expert Name</th>
                        <th>Location</th>
                        <th>Specialization</th>
                        <th>Experience</th>
                        <th>Assigned Farmers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($expert = $experts->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expert['expert_name']); ?></td>
                            <td><?php echo htmlspecialchars($expert['expert_location']); ?></td>
                            <td><?php echo htmlspecialchars($expert['specialization']); ?></td>
                            <td><?php echo $expert['experience_years']; ?> years</td>
                            <td>
                                <?php
                                // Get list of farmers assigned to this expert
                                $stmt2 = $conn->prepare("
                                    SELECT f.id, f.name, f.location, efa.assigned_at, efa.notes
                                    FROM farmers f
                                    JOIN expert_farmer_assignments efa ON f.id = efa.farmer_id
                                    WHERE efa.expert_id = ? AND efa.status = 'active'
                                    ORDER BY f.name
                                ");
                                $stmt2->bind_param("i", $expert['expert_id']);
                                $stmt2->execute();
                                $assigned_farmers = $stmt2->get_result();
                                ?>
                                <div class="assigned-farmers">
                                    <strong><?php echo $expert['assigned_farmers']; ?> farmers</strong>
                                    <?php if ($assigned_farmers->num_rows > 0): ?>
                                        <ul class="farmer-list">
                                            <?php while ($farmer = $assigned_farmers->fetch_assoc()): ?>
                                                <li>
                                                    <?php echo htmlspecialchars($farmer['name']); ?> 
                                                    (<?php echo htmlspecialchars($farmer['location']); ?>)
                                                    <?php if ($farmer['notes']): ?>
                                                        <span class="notes-icon" title="<?php echo htmlspecialchars($farmer['notes']); ?>">
                                                            <i class="fas fa-sticky-note"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="expert_id" value="<?php echo $expert['expert_id']; ?>">
                                                        <input type="hidden" name="farmer_id" value="<?php echo $farmer['id']; ?>">
                                                        <button type="submit" name="unassign_farmer" class="btn btn-danger btn-small">Unassign</button>
                                                    </form>
                                                </li>
                                            <?php endwhile; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <button class="btn btn-small" onclick="showAssignFarmerModal(<?php echo $expert['expert_id']; ?>, '<?php echo htmlspecialchars($expert['expert_name']); ?>')">
                                    Assign Farmer
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No active experts found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Farmer Modal -->
<div id="assignFarmerModal" class="modal">
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
.expert-assignments {
    margin-top: 20px;
}

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

.notes-icon {
    margin-left: 8px;
    color: #666;
    cursor: help;
}

.success {
    color: green;
    background: #e8f5e9;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
}

.error {
    color: #d32f2f;
    background: #ffebee;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 15px;
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

<?php include '../includes/footer.php'; ?>