<?php
require_once '../../config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Please log in to book equipment.']);
    exit();
}

$equipment_id = isset($_REQUEST['equipment_id']) ? intval($_REQUEST['equipment_id']) : 0;
$start_date = isset($_REQUEST['start_date']) ? sanitize($_REQUEST['start_date']) : '';
$end_date = isset($_REQUEST['end_date']) ? sanitize($_REQUEST['end_date']) : '';

if ($equipment_id && $start_date && $end_date) {
    $cost = calculateBookingCost($equipment_id, $start_date, $end_date);
    
    $stmt = $conn->prepare("SELECT price_per_day FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $equipment = $result->fetch_assoc();
    $price_per_day = $equipment['price_per_day'];

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    if ($cost > 0) {
        echo json_encode([
            'success' => true,
            'days' => $days,
            'price_per_day' => number_format($price_per_day, 2),
            'total_cost' => number_format($cost, 2)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid booking dates!']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
}
?>