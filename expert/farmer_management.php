<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('expert');

$user_id = $_SESSION['user_id'];

// Get only assigned farmers
$stmt = $conn->prepare("
    SELECT f.*, COUNT(sr.id) as report_count 
    FROM farmers f 
    JOIN expert_farmer_assignments efa ON f.id = efa.farmer_id
    LEFT JOIN soil_reports sr ON f.id = sr.farmer_id 
    WHERE efa.expert_id = ? AND efa.status = 'active'
    GROUP BY f.id 
    ORDER BY f.name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmers = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container">
    <h2>Farmer Management</h2>

    <div class="card">
        <h3>Assigned Farmers</h3>
        <?php if ($farmers->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Soil Reports</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($farmer = $farmers->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $farmer['name']; ?></td>
                            <td><?php echo $farmer['email']; ?></td>
                            <td><?php echo $farmer['phone'] ?: 'N/A'; ?></td>
                            <td><?php echo $farmer['location'] ?: 'N/A'; ?></td>
                            <td><?php echo $farmer['report_count']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($farmer['created_at'])); ?></td>
                            <td>
                                <a href="advisory.php?farmer_id=<?php echo $farmer['id']; ?>" class="btn">View Reports</a>
                                <a href="chat.php?farmer_id=<?php echo $farmer['id']; ?>" class="btn">Chat</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>You don't have any farmers assigned yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>