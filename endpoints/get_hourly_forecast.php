<?php
date_default_timezone_set('Asia/Kolkata');
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$date = $_GET['date'] ?? null;

if (!$date) {
    echo json_encode(['success' => false, 'error' => 'Date parameter is required']);
    exit();
}

// Get farmer's location
$stmt = $conn->prepare("SELECT location FROM farmers WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmer = $result->fetch_assoc();
$location = $farmer['location'] ?? null;

$response = ['success' => false, 'hourly' => [], 'error' => ''];

if ($location) {
    // OpenWeatherMap API key
    $api_key = 'e674bf050650a7cc81ed63d1f7d03a38';

    // Parse location for coordinates
    $lat = null;
    $lng = null;
    if (strpos($location, ',') !== false) {
        list($lat, $lng) = array_map('trim', explode(',', $location));
    }

    // Check if curl extension is loaded
    if (!extension_loaded('curl')) {
        $response['error'] = 'CURL extension is not loaded. Please contact administrator.';
        echo json_encode($response);
        exit();
    }

    // Fetch 5-day forecast with 3-hour intervals
    if ($lat && $lng) {
        $forecast_url = "http://api.openweathermap.org/data/2.5/forecast?lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&appid=" . $api_key . "&units=metric";
    } else {
        $forecast_url = "http://api.openweathermap.org/data/2.5/forecast?q=" . urlencode($location . ",IN") . "&appid=" . $api_key . "&units=metric";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $forecast_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $api_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($api_response) {
        $data = json_decode($api_response, true);
        if ($data && isset($data['list'])) {
            $hourly_data = [];
            foreach ($data['list'] as $forecast) {
                $forecast_date = date('Y-m-d', $forecast['dt']);
                if ($forecast_date === $date) {
                    $hourly_data[] = [
                        'dt' => $forecast['dt'],
                        'temp' => $forecast['main']['temp'],
                        'weather' => $forecast['weather']
                    ];
                }
            }

            $response['success'] = true;
            $response['hourly'] = $hourly_data;
        } else {
            $response['error'] = 'Unable to fetch forecast data. API Response: ' . substr($api_response, 0, 100);
        }
    } else {
        $response['error'] = 'Network error fetching forecast. CURL Error: ' . $curl_error;
    }
} else {
    $response['error'] = 'Location not set. Please update your profile.';
}

echo json_encode($response);
?>
