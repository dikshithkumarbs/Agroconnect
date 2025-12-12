<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

$user_id = $_SESSION['user_id'];
$report_id = isset($_GET['report_id']) ? intval($_GET['report_id']) : 0;
$recommendations = [];
$report = null;

if ($report_id) {
    // Get soil report data
    $stmt = $conn->prepare("SELECT * FROM soil_reports WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $report_id, $user_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();

    if ($report) {
        // Get existing recommendations or generate new ones
        $stmt = $conn->prepare("SELECT * FROM crop_recommendations WHERE soil_report_id = ?");
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $existing_recs = $stmt->get_result();

        if ($existing_recs->num_rows > 0) {
            while ($rec = $existing_recs->fetch_assoc()) {
                $recommendations[] = $rec;
            }
        } else {

        }
    }
}

include '../includes/header.php';
?>

<div class="container">
    <h2>Crop Recommendations</h2>

    <?php if ($report): ?>
        <div class="card">
            <h3>Soil Report Details</h3>
            <p><strong>Uploaded:</strong> <?php echo date('Y-m-d H:i', strtotime($report['uploaded_at'])); ?></p>
            <?php if ($report['report_file']): ?>
                <p><strong>Report File:</strong> <a href="../uploads/<?php echo $report['report_file']; ?>" target="_blank">View Report</a></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Recommended Crops</h3>
            <?php if (!empty($recommendations)): ?>
                <div class="equipment-grid">
                    <?php foreach ($recommendations as $rec): ?>
                        <div class="equipment-card">
                            <div class="content">
                                <h4><?php echo $rec['crop_name']; ?></h4>
                                <p><strong>Yield Estimate:</strong> <?php echo $rec['yield_estimate']; ?> tons/acre</p>
                                <p><strong>Water Required:</strong> <?php echo $rec['water_requirement']; ?> mm</p>
                                <p><strong>Market Value:</strong> ₹<?php echo number_format($rec['market_value'], 2); ?>/kg</p>
                                <p><em><?php echo $rec['ai_generated'] ? 'AI Generated' : 'Expert Recommended'; ?></em></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No recommendations available.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <h3>Select a Soil Report</h3>
            <p>Please select a soil report to view crop recommendations.</p>
            <a href="crop_advisory.php" class="btn">View Soil Reports</a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
