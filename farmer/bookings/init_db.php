<?php
require_once '../../config.php';

echo "<h2>Initializing Database Tables</h2>";

// Read the SQL file
$sql = file_get_contents('../../database.sql');

// Split the SQL into individual statements
$statements = explode(';', $sql);

$success = 0;
$errors = 0;

foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement)) {
        // Check if this is a CREATE TABLE statement
        if (preg_match('/^CREATE TABLE\s+(\w+)/i', $statement, $matches)) {
            $tableName = $matches[1];
            // Check if table already exists
            $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
            if ($checkTable && $checkTable->num_rows > 0) {
                // Table already exists, skip this statement
                echo "Table '$tableName' already exists, skipping...<br>";
                $success++;
                continue;
            }
        }
        
        // Execute the statement
        try {
            if ($conn->query($statement) === TRUE) {
                $success++;
            } else {
                // Check if it's an "already exists" error
                if (strpos($conn->error, 'already exists') !== false) {
                    $success++;
                } else {
                    echo "Error executing statement: " . $conn->error . "<br>";
                    echo "Statement: " . substr($statement, 0, 100) . "...<br><br>";
                    $errors++;
                }
            }
        } catch (mysqli_sql_exception $e) {
            // Check if it's an "already exists" error
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "Table already exists, skipping...<br>";
                $success++;
            } else {
                echo "Error executing statement: " . $e->getMessage() . "<br>";
                echo "Statement: " . substr($statement, 0, 100) . "...<br><br>";
                $errors++;
            }
        }
    }
}

echo "Database initialization completed!<br>";
echo "Successful statements: " . $success . "<br>";
echo "Errors: " . $errors . "<br>";

// Insert sample data if tables are empty
echo "<h3>Inserting sample data...</h3>";

// Check if admins table is empty
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM admins");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO admins (name, email, password, phone, location) VALUES ('Admin User', 'admin@agroconnect.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1234567890', 'Bengaluru')");
        echo "Inserted sample admin data<br>";
    }
} catch (Exception $e) {
    echo "Warning: Could not check admins table: " . $e->getMessage() . "<br>";
}

// Check if experts table is empty
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM experts");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO experts (name, email, password, phone, location, specialization, experience_years) VALUES ('Expert One', 'expert1@agroconnect.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'Mysore', 'Crop Science', 5)");
        echo "Inserted sample expert data<br>";
    }
} catch (Exception $e) {
    echo "Warning: Could not check experts table: " . $e->getMessage() . "<br>";
}

// Check if farmers table is empty
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM farmers");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO farmers (name, email, password, phone, location, land_size, soil_type) VALUES ('Farmer One', 'farmer1@agroconnect.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '5556667777', 'Mandya', 5.5, 'Alluvial')");
        echo "Inserted sample farmer data<br>";
    }
} catch (Exception $e) {
    echo "Warning: Could not check farmers table: " . $e->getMessage() . "<br>";
}

// Check if expert_farmer_assignments table is empty
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM expert_farmer_assignments");
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $conn->query("INSERT INTO expert_farmer_assignments (expert_id, farmer_id, status) VALUES (1, 1, 'active')");
        echo "Inserted sample expert-farmer assignment<br>";
    }
} catch (Exception $e) {
    echo "Warning: Could not check expert_farmer_assignments table: " . $e->getMessage() . "<br>";
}

echo "<h3>Database initialization completed!</h3>";
?>