<?php
date_default_timezone_set('Asia/Kolkata');
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

header('Content-Type: application/json');

$response = ['success' => false, 'hourly' => [], 'error' => ''];

try {
    $user_id = $_SESSION['user_id'];
    $selected_date = $_GET['date'] ?? '';

    if (empty($selected_date)) {
        $response['error'] = 'Date parameter is required';
        echo json_encode($response);
        exit();
    }

    // Validate date format
    $date = DateTime::createFromFormat('Y-m-d', $selected_date);
    if (!$date) {
        $response['error'] = 'Invalid date format';
        echo json_encode($response);
        exit();
    }

    // Get farmer's location
    $stmt = $conn->prepare("SELECT location FROM farmers WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $farmer = $result->fetch_assoc();
    $location = $farmer['location'] ?? null;

    if (!$location) {
        $response['error'] = 'Location not set. Please update your profile.';
        echo json_encode($response);
        exit();
    }

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

    // Fetch 5-day forecast which includes hourly data
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
            // Filter hourly data for the selected date
            $filtered_hourly = [];
            
            foreach ($data['list'] as $hour) {
                $hour_date = date('Y-m-d', $hour['dt']);
                if ($hour_date === $selected_date) {
                    $filtered_hourly[] = $hour;
                }
            }
            
            $response['success'] = true;
            $response['hourly'] = $filtered_hourly;
        } else {
            $response['error'] = 'Unable to fetch hourly forecast data from API.';
        }
    } else {
        $response['error'] = 'Network error fetching hourly forecast. CURL Error: ' . $curl_error;
    }
} catch (Exception $e) {
    $response['error'] = 'An error occurred: ' . $e->getMessage();
}

echo json_encode($response);
?>