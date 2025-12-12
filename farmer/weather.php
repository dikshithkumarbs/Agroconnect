<?php
date_default_timezone_set('Asia/Kolkata');
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');

$user_id = $_SESSION['user_id'];

// Get farmer's location
$stmt = $conn->prepare("SELECT location FROM farmers WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmer = $result->fetch_assoc();
$location = $farmer['location'] ?? null;

$weather_data = null;
$forecast_data = null;
$alerts_data = null;
$error_message = null;

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
        $error_message = "CURL extension is not loaded. Please contact administrator.";
    } else {
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
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['main'])) {
                $weather_data = $data;
            } else {
                $error_message = "Unable to fetch current weather data. API Response: " . substr($response, 0, 100);
            }
        } else {
            $error_message = "Network error fetching current weather. CURL Error: " . $curl_error;
        }

        // Fetch 5-day forecast
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
        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['list'])) {
                $forecast_data = $data;
            }
        }

        // Fetch weather alerts using One Call API 3.0 (requires coordinates)
        if ($lat && $lng) {
            $alerts_url = "http://api.openweathermap.org/data/3.0/onecall?lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&exclude=minutely,hourly,daily&appid=" . $api_key;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $alerts_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $data = json_decode($response, true);
                if ($data && isset($data['alerts'])) {
                    $alerts_data = $data['alerts'];
                }
            }
        }
    }
} else {
    $error_message = "Your location is not set. Please update your profile to get weather reports.";
}

include '../includes/header.php';
?>

<div class="container">
    <h2><i class="fas fa-cloud-sun"></i> Real-Time Weather Report</h2>

    <?php if ($error_message): ?>
        <div class="card">
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php if (!$location): ?>
                <a href="#" class="btn">Update Profile</a> <!-- Link to profile update if exists -->
            <?php endif; ?>
        </div>
    <?php elseif ($weather_data): ?>
        <!-- Weather Alerts Section -->
        <?php if ($alerts_data && count($alerts_data) > 0): ?>
            <div class="alerts-section">
                <h3><i class="fas fa-exclamation-triangle"></i> Weather Alerts</h3>
                <?php foreach ($alerts_data as $alert): ?>
                    <div class="alert-card warning">
                        <h4><?php echo htmlspecialchars($alert['event']); ?></h4>
                        <p><?php echo htmlspecialchars($alert['description']); ?></p>
                        <small>Valid until: <?php echo date('M j, Y H:i', $alert['end']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Current Weather -->
        <div class="weather-card">
            <div class="weather-header">
                <h3><?php echo htmlspecialchars($weather_data['name']); ?>, <?php echo htmlspecialchars($weather_data['sys']['country']); ?></h3>
                <p id="last-updated">Last updated: <?php echo date('l, F j, Y \a\t H:i:s'); ?></p>
            </div>
            <div class="weather-main">
                <div class="temperature">
                    <span class="temp"><?php echo round($weather_data['main']['temp']); ?>°C</span>
                    <img src="http://openweathermap.org/img/w/<?php echo $weather_data['weather'][0]['icon']; ?>.png" alt="Weather Icon">
                </div>
                <div class="description">
                    <p><?php echo ucfirst($weather_data['weather'][0]['description']); ?></p>
                </div>
            </div>
            <div class="weather-details">
                <div class="detail">
                    <span>Humidity:</span> <?php echo $weather_data['main']['humidity']; ?>%
                </div>
                <div class="detail">
                    <span>Wind Speed:</span> <?php echo $weather_data['wind']['speed']; ?> m/s
                </div>
                <div class="detail">
                    <span>Pressure:</span> <?php echo $weather_data['main']['pressure']; ?> hPa
                </div>
                <div class="detail">
                    <span>Visibility:</span> <?php echo ($weather_data['visibility'] / 1000); ?> km
                </div>
            </div>
        </div>

        <!-- 5-Day Forecast -->
        <?php if ($forecast_data && isset($forecast_data['list'])): ?>
            <div class="forecast-section">
                <h3><i class="fas fa-calendar-alt"></i> 5-Day Weather Forecast</h3>
                <div class="forecast-grid">
                    <?php
                    $daily_forecasts = [];
                    foreach ($forecast_data['list'] as $forecast) {
                        $date = date('Y-m-d', $forecast['dt']);
                        if (!isset($daily_forecasts[$date])) {
                            $daily_forecasts[$date] = $forecast;
                        }
                    }
                    $count = 0;
                    foreach ($daily_forecasts as $date => $forecast):
                        if ($count >= 5) break;
                        $count++;
                    ?>
                        <div class="forecast-card" onclick="showHourlyForecast('<?php echo $date; ?>')">
                            <div class="forecast-date">
                                <strong><?php echo date('D, M j', strtotime($date)); ?></strong>
                            </div>
                            <div class="forecast-icon">
                                <img src="http://openweathermap.org/img/w/<?php echo $forecast['weather'][0]['icon']; ?>.png" alt="Weather">
                            </div>
                            <div class="forecast-temp">
                                <span class="max"><?php echo round($forecast['main']['temp_max']); ?>°</span>
                                <span class="min"><?php echo round($forecast['main']['temp_min']); ?>°</span>
                            </div>
                            <div class="forecast-desc">
                                <?php echo ucfirst($forecast['weather'][0]['description']); ?>
                            </div>
                            <div class="forecast-details">
                                <small>Rain: <?php echo ($forecast['pop'] * 100); ?>%</small><br>
                                <small>Wind: <?php echo $forecast['wind']['speed']; ?> m/s</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.weather-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.weather-header h3 {
    color: #2c5530;
    margin-bottom: 5px;
}

.weather-header p {
    color: #666;
    font-size: 0.9em;
}

.weather-main {
    display: flex;
    align-items: center;
    margin: 20px 0;
}

.temperature {
    display: flex;
    align-items: center;
    font-size: 3em;
    font-weight: bold;
    color: #2c5530;
}

.temperature img {
    margin-left: 10px;
    width: 80px;
    height: 80px;
}

.description {
    margin-left: 20px;
    font-size: 1.2em;
    color: #555;
}

.weather-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.detail {
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
}

.detail span {
    font-weight: bold;
    color: #2c5530;
}

/* Weather Alerts Styles */
.alerts-section {
    margin-bottom: 20px;
}

.alerts-section h3 {
    color: #dc3545;
    margin-bottom: 15px;
}

.alert-card {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
}

.alert-card.warning {
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-card h4 {
    color: #856404;
    margin: 0 0 10px 0;
    font-size: 1.1em;
}

.alert-card.warning h4 {
    color: #721c24;
}

.alert-card p {
    margin: 0 0 10px 0;
    color: #856404;
}

.alert-card.warning p {
    color: #721c24;
}

.alert-card small {
    color: #6c757d;
    font-size: 0.9em;
}

/* Forecast Styles */
.forecast-section {
    margin-top: 30px;
}

.forecast-section h3 {
    color: #2c5530;
    margin-bottom: 20px;
}

.forecast-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
}

.forecast-card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 15px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.forecast-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    border: 1px solid #2c5530;
}

.forecast-date {
    margin-bottom: 10px;
    font-size: 0.9em;
    color: #2c5530;
}

.forecast-icon {
    margin: 10px 0;
}

.forecast-icon img {
    width: 50px;
    height: 50px;
}

.forecast-temp {
    margin: 10px 0;
}

.forecast-temp .max {
    font-size: 1.4em;
    font-weight: bold;
    color: #dc3545;
    margin-right: 5px;
}

.forecast-temp .min {
    font-size: 1.2em;
    color: #6c757d;
}

.forecast-desc {
    font-size: 0.9em;
    color: #555;
    margin: 8px 0;
    text-transform: capitalize;
}

.forecast-details {
    font-size: 0.8em;
    color: #666;
    margin-top: 8px;
}

.forecast-details small {
    display: block;
    line-height: 1.3;
}
</style>

<script>
function updateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Kolkata'
    };
    const timeString = now.toLocaleDateString('en-US', options) + ' at ' + now.toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Kolkata'});
    document.getElementById('last-updated').textContent = 'Last updated: ' + timeString;
}

// Update time immediately and then every second
updateTime();
setInterval(updateTime, 1000);

function showHourlyForecast(date) {
    // Create modal for hourly forecast
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background-color: white;
        padding: 20px;
        border-radius: 8px;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;

    modalContent.innerHTML = `
        <h3 style="color: #2c5530; margin-bottom: 20px;">Hourly Forecast for ${new Date(date).toLocaleDateString()}</h3>
        <div id="hourly-content" style="display: flex; flex-wrap: wrap; gap: 10px;">
            <p>Loading hourly forecast...</p>
        </div>
        <button onclick="this.closest('.modal').remove()" style="
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #2c5530;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        ">Close</button>
    `;

    modal.appendChild(modalContent);
    modal.classList.add('modal');
    document.body.appendChild(modal);

    // Fetch actual hourly data from the server
    fetch(`get_hourly_forecast.php?date=${date}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let hourlyHTML = '<div style="width: 100%; text-align: center; padding: 20px;">';
                hourlyHTML += '<h4>Hourly Forecast</h4>';
                hourlyHTML += '<div style="display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; margin-top: 20px;">';
                
                data.hourly.forEach(hour => {
                    const time = new Date(hour.dt * 1000).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    hourlyHTML += `
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; min-width: 120px;">
                            <div>${time}</div>
                            <div><img src="http://openweathermap.org/img/w/${hour.weather[0].icon}.png" style="width: 40px; height: 40px;"></div>
                            <div>${Math.round(hour.main.temp)}°C</div>
                            <div style="font-size: 0.9em; color: #666;">${hour.weather[0].main}</div>
                        </div>
                    `;
                });
                
                hourlyHTML += '</div></div>';
                document.getElementById('hourly-content').innerHTML = hourlyHTML;
            } else {
                document.getElementById('hourly-content').innerHTML = '<p>Error loading hourly forecast data.</p>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('hourly-content').innerHTML = '<p>Error loading hourly forecast data.</p>';
        });
}
</script>

<?php include '../includes/footer.php'; ?>
