<?php
require_once '../../config.php';

echo "<h2>Checking typing_status table structure</h2>";

$result = $conn->query("DESCRIBE typing_status");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . $value . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . $conn->error;
}

// Check if expert_farmer_assignments has the right data
echo "<h3>Checking expert_farmer_assignments</h3>";
$result = $conn->query("SELECT * FROM expert_farmer_assignments");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Expert ID: " . $row['expert_id'] . " | Farmer ID: " . $row['farmer_id'] . " | Status: " . $row['status'] . "<br>";
    }
} else {
    echo "Error: " . $conn->error;
}
?>