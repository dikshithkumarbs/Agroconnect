<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('expert');

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
$farmer_id = isset($_GET['farmer_id']) ? intval($_GET['farmer_id']) : 0;

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recommendation'])) {
    $crop_name = sanitize($_POST['crop_name']);
    $yield_estimate = floatval($_POST['yield_estimate']);
    $water_requirement = floatval($_POST['water_requirement']);
    $market_value = floatval($_POST['market_value']);
    $advice = sanitize($_POST['advice']);

    $stmt = $conn->prepare("INSERT INTO crop_recommendations (soil_report_id, crop_name, yield_estimate, water_requirement, market_value, expert_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdddi", $report_id, $crop_name, $yield_estimate, $water_requirement, $market_value, $user_id);

    if ($stmt->execute()) {
        // Send notification to farmer
        $farmer_query = $conn->prepare("SELECT farmer_id FROM soil_reports WHERE id = ?");
        $farmer_query->bind_param("i", $report_id);
        $farmer_query->execute();
        $farmer = $farmer_query->get_result()->fetch_assoc();

        sendNotification($farmer['farmer_id'], 'New Crop Recommendation', 'An expert has provided crop recommendations for your soil report.', 'info');

        $message = '<div class="success">Recommendation added successfully!</div>';
    } else {
        $message = '<div class="error">Failed to add recommendation.</div>';
    }
    $stmt->close();
}

// Get soil reports
if ($farmer_id) {
    $stmt = $conn->prepare("SELECT sr.*, f.name as farmer_name FROM soil_reports sr JOIN farmers f ON sr.farmer_id = f.id WHERE sr.farmer_id = ? ORDER BY sr.uploaded_at DESC");
    $stmt->bind_param("i", $farmer_id);
} else {
    $stmt = $conn->prepare("SELECT sr.*, f.name as farmer_name FROM soil_reports sr JOIN farmers f ON sr.farmer_id = f.id ORDER BY sr.uploaded_at DESC");
}
$stmt->execute();
$reports = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container">
    <h2>Advisory & Recommendations</h2>

    <?php echo $message; ?>

    <?php if ($report_id): ?>
        <?php
        $stmt = $conn->prepare("SELECT sr.*, f.name as farmer_name FROM soil_reports sr JOIN farmers f ON sr.farmer_id = f.id WHERE sr.id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        ?>

        <div class="card">
            <h3>Provide Recommendation for <?php echo $report['farmer_name']; ?>'s Soil Report</h3>
            <p><strong>Uploaded:</strong> <?php echo date('Y-m-d H:i', strtotime($report['uploaded_at'])); ?></p>
            <?php if ($report['report_file']): ?>
                <p><strong>Report File:</strong> <a href="../uploads/<?php echo $report['report_file']; ?>" target="_blank">View Report</a></p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="crop_name">Recommended Crop:</label>
                    <input type="text" id="crop_name" name="crop_name" required>
                </div>
                <div class="form-group">
                    <label for="yield_estimate">Yield Estimate (tons/acre):</label>
                    <input type="number" step="0.1" id="yield_estimate" name="yield_estimate" required>
                </div>
                <div class="form-group">
                    <label for="water_requirement">Water Requirement (mm):</label>
                    <input type="number" step="0.1" id="water_requirement" name="water_requirement" required>
                </div>
                <div class="form-group">
                    <label for="market_value">Market Value (₹/kg):</label>
                    <input type="number" step="0.01" id="market_value" name="market_value" required>
                </div>
                <div class="form-group">
                    <label for="advice">Additional Advice:</label>
                    <textarea id="advice" name="advice" rows="4"></textarea>
                </div>
                <button type="submit" name="recommendation" class="btn">Submit Recommendation</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>All Soil Reports</h3>
        <?php if ($reports->num_rows > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Farmer</th>
                        <th>Date</th>
                        <th>File</th>
                        <th>Recommendations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($report = $reports->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $report['farmer_name']; ?></td>
                            <td><?php echo date('Y-m-d', strtotime($report['uploaded_at'])); ?></td>
                            <td>
                                <?php if ($report['report_file']): ?>
                                    <a href="../uploads/<?php echo $report['report_file']; ?>" target="_blank">View</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $rec_stmt = $conn->prepare("SELECT COUNT(*) as count FROM crop_recommendations WHERE soil_report_id = ?");
                                $rec_stmt->bind_param("i", $report['id']);
                                $rec_stmt->execute();
                                echo $rec_stmt->get_result()->fetch_assoc()['count'];
                                ?>
                            </td>
                            <td>
                                <a href="?report_id=<?php echo $report['id']; ?>" class="btn">Add Recommendation</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No soil reports available.</p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
