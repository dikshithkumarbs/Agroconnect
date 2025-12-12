<?php
require_once '../../config.php';

echo "<h2>Creating Missing Tables</h2>";

// Create message_rate_limits table
$sql = "CREATE TABLE IF NOT EXISTS message_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    action_type ENUM('send_message', 'edit_message', 'delete_message', 'search_messages') NOT NULL,
    action_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rate_limit (user_id, user_role, action_type, window_start),
    INDEX idx_window_start (window_start)
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ message_rate_limits table created successfully<br>";
} else {
    echo "✗ Error creating message_rate_limits table: " . $conn->error . "<br>";
}

// Create user_activity table
$sql = "CREATE TABLE IF NOT EXISTS user_activity (
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_online BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, user_role),
    INDEX idx_last_activity (last_activity),
    INDEX idx_is_online (is_online)
)";

if ($conn->query($sql) === TRUE) {
    echo "✓ user_activity table created successfully<br>";
} else {
    echo "✗ Error creating user_activity table: " . $conn->error . "<br>";
}

echo "<h3>Missing tables creation completed!</h3>";
?>