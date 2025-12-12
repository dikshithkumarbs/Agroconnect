<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file upload only
    $report_file = '';
    if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        $file_name = uniqid() . '_' . basename($_FILES['report_file']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['report_file']['tmp_name'], $target_file)) {
            $report_file = $file_name;
        }
    }

    // Insert soil report with file only (no manual NPK values)
    $stmt = $conn->prepare("INSERT INTO soil_reports (farmer_id, report_file) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $report_file);

    if ($stmt->execute()) {
        $report_id = $conn->insert_id;
        $message = '<div class="success">Soil report uploaded successfully! Our experts will analyze it and provide crop recommendations.</div>';

        // Send notification to experts
        $stmt = $conn->prepare("SELECT id FROM experts");
        $stmt->execute();
        $experts = $stmt->get_result();

        while ($expert = $experts->fetch_assoc()) {
            sendNotification($expert['id'], 'expert', 'New Soil Report', 'A new soil report has been uploaded by a farmer for analysis.', 'info');
        }
    } else {
        $message = '<div class="error">Failed to upload soil report.</div>';
    }
    $stmt->close();
}

// Get existing soil reports
$stmt = $conn->prepare("SELECT sr.*, cr.crop_name, cr.yield_estimate, cr.water_requirement, cr.market_value, cr.ai_generated
                       FROM soil_reports sr
                       LEFT JOIN crop_recommendations cr ON sr.id = cr.soil_report_id
                       WHERE sr.farmer_id = ?
                       ORDER BY sr.uploaded_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$reports = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container">
    <h2>Crop Advisory</h2>

    <?php echo $message; ?>

    <div class="card">
        <h3>Upload Soil Report for Analysis</h3>
        <p>Upload your soil test report and our agricultural experts will analyze it to provide personalized crop recommendations.</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="report_file">Upload Soil Test Report:</label>
                <input type="file" id="report_file" name="report_file" accept=".pdf,.jpg,.jpeg,.png" required onchange="validateFileUpload(this)">
                <small>Accepted formats: PDF, JPG, JPEG, PNG. Max size: 5MB</small>
            </div>
            <button type="submit" class="btn">Upload & Get Recommendations</button>
        </form>
    </div>

    <div class="card">
        <h3>Your Crop Recommendations</h3>
        <?php if ($reports->num_rows > 0): ?>
            <?php
            $current_report_id = null;
            while ($report = $reports->fetch_assoc()):
                if ($current_report_id !== $report['id']):
                    if ($current_report_id !== null) echo '</div></div>';
                    $current_report_id = $report['id'];
            ?>
                <div class="history-section">
                    <div class="history-header">
                        <h4>Report from <?php echo date('M d, Y', strtotime($report['uploaded_at'])); ?></h4>
                        <div class="history-status">
                            <?php if ($report['crop_name']): ?>
                                <span class="status-approved">Recommendations Available</span>
                            <?php else: ?>
                                <span class="status-pending">Under Expert Review</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($report['report_file']): ?>
                        <p><a href="../uploads/<?php echo $report['report_file']; ?>" target="_blank" class="btn btn-small">View Original Report</a></p>
                    <?php endif; ?>

                    <?php if ($report['crop_name']): ?>
                        <div class="recommendations-grid">
                            <div class="recommendation-card">
                                <h5><?php echo $report['crop_name']; ?></h5>
                                <div class="rec-details">
                                    <p><strong>Expected Yield:</strong> <?php echo $report['yield_estimate']; ?> tons/acre</p>
                                    <p><strong>Water Required:</strong> <?php echo $report['water_requirement']; ?> mm</p>
                                    <p><strong>Market Value:</strong> ₹<?php echo number_format($report['market_value'], 2); ?>/kg</p>
                                </div>
                                <div class="rec-source">
                                    <em>Expert Recommendation</em>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <p><em>Our experts are analyzing your soil report. Recommendations will be available soon.</em></p>
                    <?php endif; ?>
                </div>
            <?php
                endif;
            endwhile;
            if ($current_report_id !== null) echo '</div></div>';
            ?>
        <?php else: ?>
            <p>No soil reports uploaded yet. Upload your first report to get personalized crop recommendations.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.history-section {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.history-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #2c5530;
}

.history-header h4 {
    margin: 0;
    color: #2c5530;
    font-size: 1.2em;
    font-weight: 600;
}

.history-status {
    font-size: 0.9em;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 500;
}

.status-approved {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-pending {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.recommendations-grid {
    margin-top: 15px;
}

.recommendation-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.recommendation-card:hover {
    box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    border-color: #2c5530;
}

.recommendation-card h5 {
    margin: 0 0 15px 0;
    color: #2c5530;
    font-size: 1.1em;
    font-weight: 600;
    text-align: center;
    border-bottom: 1px solid #e9ecef;
    padding-bottom: 10px;
}

.rec-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.rec-details p {
    margin: 0;
    font-size: 0.95em;
    color: #495057;
    display: flex;
    align-items: center;
}

.rec-details p strong {
    color: #2c5530;
    margin-right: 8px;
    font-weight: 600;
}

.rec-source {
    text-align: center;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    font-size: 0.85em;
    color: #6c757d;
    font-style: italic;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.85em;
    border-radius: 6px;
    text-decoration: none;
    display: inline-block;
    margin-top: 10px;
    transition: all 0.2s ease;
}

.btn-small:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
</style>

<?php include '../includes/footer.php'; ?>
