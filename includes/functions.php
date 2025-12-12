<?php
// Check if session is not already active before starting it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }

    return $conn;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get user role
function getUserRole() {
    return $_SESSION['role'] ?? '';
}

// Get user ID
function getUserId() {
    return $_SESSION['user_id'] ?? 0;
}

// Redirect if not logged in
function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect if not the correct role
function redirectIfNotRole($role) {
    if (!isLoggedIn() || getUserRole() !== $role) {
        header('Location: ../unauthorized.php');
        exit();
    }
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Format currency
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

// Format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Get user by email
function getUserByEmail($email, $role) {
    global $conn;
    
    $table = $role . 's'; // admins, experts, farmers
    $stmt = $conn->prepare("SELECT * FROM $table WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get user by phone
function getUserByPhone($phone, $role) {
    global $conn;
    
    $table = $role . 's'; // admins, experts, farmers
    $stmt = $conn->prepare("SELECT * FROM $table WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get unread notifications count
function getUnreadNotificationsCount($user_id, $user_role) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND user_role = ? AND is_read = 0
    ");
    $stmt->bind_param("is", $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Mark notification as read
function markNotificationAsRead($notification_id, $user_id, $user_role) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ? AND user_role = ?
    ");
    $stmt->bind_param("iis", $notification_id, $user_id, $user_role);
    return $stmt->execute();
}

// Send notification to a user
function sendNotification($user_id, $user_role, $title, $message, $type = 'info') {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, user_role, title, message, type) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $user_id, $user_role, $title, $message, $type);
    
    return $stmt->execute();
}

// Get unread messages count between farmer and expert
function getUnreadMessagesCount($farmer_id, $expert_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE sender_id = ? AND sender_role = 'expert' 
        AND receiver_id = ? AND receiver_role = 'farmer' 
        AND read_at IS NULL
    ");
    $stmt->bind_param("ii", $expert_id, $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get farmer location
function getFarmerLocation($farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT location FROM farmers WHERE id = ?");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['location'];
    }
    
    return null;
}

// Get weather data for a location
function getWeatherData($location) {
    // OpenWeatherMap API key - replace with your actual API key
    $api_key = 'YOUR_OPENWEATHERMAP_API_KEY';
    $url = "http://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location) . "&appid=" . $api_key . "&units=metric";
    
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Rate limiting function
function checkRateLimit($user_id, $user_role, $action, $limit = 30, $window = 60) {
    global $conn;
    
    // Clean up old rate limit entries (older than window seconds)
    $stmt = $conn->prepare("DELETE FROM message_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("i", $window);
    $stmt->execute();
    
    // Check current count for this user and action within the time window
    $stmt = $conn->prepare("SELECT SUM(action_count) as total_count FROM message_rate_limits WHERE user_id = ? AND user_role = ? AND action_type = ? AND window_start > DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stmt->bind_param("issi", $user_id, $user_role, $action, $window);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $current_count = $row['total_count'] ?? 0;
    
    if ($current_count >= $limit) {
        return false; // Rate limit exceeded
    }
    
    // Add new entry or update existing entry
    $stmt = $conn->prepare("INSERT INTO message_rate_limits (user_id, user_role, action_type, action_count) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE action_count = action_count + 1");
    $stmt->bind_param("iss", $user_id, $user_role, $action);
    $stmt->execute();
    
    return true; // Within rate limit
}

// Get dashboard statistics for farmers
function getFarmerDashboardStats($user_id) {
    global $conn;
    
    $stats = [];
    
    // Get assigned expert with unread message count
    $stmt = $conn->prepare("
        SELECT e.id, e.name, e.specialization, 
               COALESCE((SELECT AVG(rating) FROM expert_ratings WHERE expert_id = e.id), 0) as rating,
               (SELECT COUNT(*) FROM messages WHERE sender_id = e.id AND sender_role = 'expert' AND receiver_id = ? AND receiver_role = 'farmer' AND read_at IS NULL) as unread_messages,
               efa.assigned_at
        FROM experts e
        JOIN expert_farmer_assignments efa ON e.id = efa.expert_id
        WHERE efa.farmer_id = ? AND efa.status = 'active'
        GROUP BY e.id
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $stats['assigned_expert'] = $stmt->get_result()->fetch_assoc();
    
    // Get recent soil reports
    $stmt = $conn->prepare("SELECT * FROM soil_reports WHERE farmer_id = ? ORDER BY uploaded_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['soil_reports'] = $stmt->get_result();
    
    // Get recent bookings
    $stmt = $conn->prepare("SELECT b.*, e.name as equipment_name FROM bookings b JOIN equipment e ON b.equipment_id = e.id WHERE b.farmer_id = ? ORDER BY b.created_at DESC LIMIT 5");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['bookings'] = $stmt->get_result();
    
    return $stats;
}

// Get dashboard statistics for experts
function getExpertDashboardStats($user_id) {
    global $conn;
    
    $stats = [];
    
    // Get farmer count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM expert_farmer_assignments WHERE expert_id = ? AND status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['farmer_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get pending reports
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM soil_reports sr JOIN expert_farmer_assignments efa ON sr.farmer_id = efa.farmer_id WHERE efa.expert_id = ? AND efa.status = 'active' AND sr.status = 'pending'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['pending_reports'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get unread messages count
    $stmt = $conn->prepare("
        SELECT COUNT(m.id) as unread_count
        FROM messages m
        JOIN expert_farmer_assignments efa ON m.sender_id = efa.farmer_id AND m.sender_role = 'farmer'
        WHERE efa.expert_id = ? AND efa.status = 'active'
        AND m.receiver_id = ? AND m.receiver_role = 'expert'
        AND m.read_at IS NULL
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $stats['unread_messages'] = $stmt->get_result()->fetch_assoc()['unread_count'];
    
    // Get recommendations count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM crop_recommendations WHERE expert_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats['recommendations_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    return $stats;
}

// Get dashboard statistics for admins
function getAdminDashboardStats() {
    global $conn;
    
    $stats = [];
    
    // Get farmer count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM farmers");
    $stmt->execute();
    $stats['farmer_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get expert count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM experts WHERE status = 'active'");
    $stmt->execute();
    $stats['expert_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get equipment count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM equipment");
    $stmt->execute();
    $stats['equipment_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get booking count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bookings");
    $stmt->execute();
    $stats['booking_count'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // Get revenue
    $stmt = $conn->prepare("SELECT SUM(total_cost) as revenue FROM bookings WHERE status = 'completed'");
    $stmt->execute();
    $stats['revenue'] = $stmt->get_result()->fetch_assoc()['revenue'] ?: 0;
    
    return $stats;
}

// Calculate booking cost based on equipment ID and date range
function calculateBookingCost($equipment_id, $start_date, $end_date) {
    global $conn;

    // Validate dates
    $start = DateTime::createFromFormat('Y-m-d', $start_date);
    $end = DateTime::createFromFormat('Y-m-d', $end_date);

    if (!$start || !$end) {
        return 0;
    }

    if ($start > $end) {
        return 0;
    }

    // Get equipment price per day
    $stmt = $conn->prepare("SELECT price_per_day FROM equipment WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return 0;
    }

    $equipment = $result->fetch_assoc();
    $price_per_day = $equipment['price_per_day'];

    // Calculate number of days (inclusive)
    $interval = $start->diff($end);
    $days = $interval->days + 1;

    // Calculate total cost
    $total_cost = $days * $price_per_day;

    return $total_cost;
}

// Generate OTP function
function generateOTP() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Send OTP via SMS (placeholder function - implement with actual SMS service)
function sendOTP($phone, $otp) {
    // Log OTP for development/testing
    error_log("OTP for $phone: $otp");

    // TODO: Implement actual SMS sending using a service like Twilio, AWS SNS, etc.
    // For now, just log it
    $log_file = 'logs/sms.log';
    $log_entry = date('Y-m-d H:i:s') . " - OTP sent to $phone: $otp\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    return true;
}

?>