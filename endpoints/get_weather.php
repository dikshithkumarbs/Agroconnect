<?php
date_default_timezone_set('Asia/Kolkata');
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Get farmer's location
$stmt = $conn->prepare("SELECT location FROM farmers WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmer = $result->fetch_assoc();
$location = $farmer['location'] ?? null;

$response = ['success' => false, 'data' => null, 'error' => ''];

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

    // Fetch current weather
    if ($lat && $lng) {
        $current_url = "http://api.openweathermap.org/data/2.5/weather?lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&appid=" . $api_key . "&units=metric";
    } else {
        $current_url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location . ",IN") . "&appid=" . $api_key . "&units=metric";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $current_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $api_response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($api_response) {
        $data = json_decode($api_response, true);
        if ($data && isset($data['main'])) {
            $response['success'] = true;
            $response['data'] = [
                'temperature' => round($data['main']['temp']),
                'description' => ucfirst($data['weather'][0]['description']),
                'humidity' => $data['main']['humidity'],
                'wind_speed' => $data['wind']['speed'],
                'icon' => $data['weather'][0]['icon'],
                'location' => $data['name'] . ', ' . $data['sys']['country']
            ];
        } else {
            $response['error'] = 'Unable to fetch weather data. API Response: ' . substr($api_response, 0, 100);
        }
    } else {
        $response['error'] = 'Network error fetching weather. CURL Error: ' . $curl_error;
    }
} else {
    $response['error'] = 'Location not set. Please update your profile.';
}

echo json_encode($response);
?>
