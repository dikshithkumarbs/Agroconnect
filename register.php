<?php
require_once 'config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$step = $_GET['step'] ?? 'register';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'register') {
        // Step 1: Handle registration form submission
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = sanitize($_POST['phone']);
        $location = sanitize($_POST['location']);
        $role = sanitize($_POST['role']);

        // Basic validation
        if (empty($name) || empty($email) || empty($password) || empty($phone) || empty($role)) {
            $error = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif (strlen($phone) !== 10 || !is_numeric($phone)) {
            $error = 'Phone number must be 10 digits.';
        } else {
            // Check if email or phone already exists
            $email_exists = getUserByEmail($email, 'farmer') || getUserByEmail($email, 'expert');
            $phone_exists = getUserByPhone($phone, 'farmer') || getUserByPhone($phone, 'expert');

            if ($email_exists) {
                $error = 'Email already registered.';
            } elseif ($phone_exists) {
                $error = 'Phone number already registered.';
            } else {
                // All good, proceed to OTP verification
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $otp = generateOTP();

                // Store registration data and OTP in session
                $_SESSION['registration_data'] = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $hashed_password,
                    'phone' => $phone,
                    'location' => $location,
                    'role' => $role,
                    'specialization' => sanitize($_POST['specialization'] ?? ''),
                    'experience_years' => (int)($_POST['experience_years'] ?? 0),
                    'land_size' => sanitize($_POST['land_size'] ?? ''),
                    'soil_type' => sanitize($_POST['soil_type'] ?? ''),
                ];
                
                // Handle file upload for experts
                if ($role === 'pending_expert' && isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/resumes/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_extension = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
                    $resume_filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.' . $file_extension;
                    $resume_path = $upload_dir . $resume_filename;
                    if (move_uploaded_file($_FILES['resume']['tmp_name'], $resume_path)) {
                        $_SESSION['registration_data']['resume_path'] = $resume_path;
                    } else {
                        $error = 'Failed to upload resume.';
                    }
                } elseif ($role === 'pending_expert' && !isset($_SESSION['registration_data']['resume_path'])) {
                    $error = 'Resume is required for expert registration.';
                }


                if (empty($error)) {
                    $_SESSION['registration_otp'] = $otp;
                    $_SESSION['registration_otp_expiry'] = time() + (5 * 60); // 5 minutes expiry

                    // Send OTP
                    sendOTP($phone, $otp);
                    
                    error_log("Registration OTP sent to $phone: $otp");

                    // Redirect to verification step
                    header('Location: register.php?step=verify');
                    exit();
                }
            }
        }
    } elseif ($step === 'verify') {
        // Step 2: Handle OTP verification
        $otp_entered = sanitize($_POST['otp']);

        if (empty($otp_entered)) {
            $error = 'Please enter the OTP.';
        } elseif (!isset($_SESSION['registration_otp']) || !isset($_SESSION['registration_data'])) {
            $error = 'Registration session expired. Please start over.';
        } elseif (time() > $_SESSION['registration_otp_expiry']) {
            $error = 'OTP has expired. Please try again.';
            unset($_SESSION['registration_otp'], $_SESSION['registration_data'], $_SESSION['registration_otp_expiry']);
        } elseif ($otp_entered !== $_SESSION['registration_otp']) {
            $error = 'Invalid OTP. Please try again.';
        } else {
            // OTP is correct, create user
            $data = $_SESSION['registration_data'];
            $conn = getDBConnection();
            
            if ($data['role'] === 'farmer') {
                $stmt = $conn->prepare("INSERT INTO farmers (name, email, password, phone, location, land_size, soil_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $data['name'], $data['email'], $data['password'], $data['phone'], $data['location'], $data['land_size'], $data['soil_type']);
            } elseif ($data['role'] === 'pending_expert') {
                $stmt = $conn->prepare("INSERT INTO experts (name, email, password, phone, location, specialization, experience_years, resume_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'inactive')");
                $stmt->bind_param("ssssssis", $data['name'], $data['email'], $data['password'], $data['phone'], $data['location'], $data['specialization'], $data['experience_years'], $data['resume_path']);
            }

            if (isset($stmt) && $stmt->execute()) {
                $success = 'Registration successful! You can now login.';
                // Clean up session
                unset($_SESSION['registration_otp'], $_SESSION['registration_data'], $_SESSION['registration_otp_expiry']);
                $step = 'success'; // To show success message instead of form
            } else {
                $error = 'An error occurred during registration. Please try again.';
            }
        }
    }
}

// Function to get user by phone (you should have this in functions.php)
if (!function_exists('getUserByPhone')) {
    function getUserByPhone($phone, $role) {
        $conn = getDBConnection();
        $table = $role === 'admin' ? 'admins' : ($role === 'expert' ? 'experts' : 'farmers');
        $stmt = $conn->prepare("SELECT * FROM $table WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Agro Connect</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="register-page">
    <div class="container">
        <div class="register-form">
            <h2>Register for Agro Connect</h2>

            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>

            <?php if ($step === 'register'): ?>
            <form method="POST" action="register.php?step=register" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number (10 digits):</label>
                    <input type="tel" id="phone" name="phone" required pattern="[0-9]{10}">
                </div>
                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" readonly>
                    <button type="button" id="getLocationBtn" class="btn btn-small">Get My Location</button>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="farmer">Farmer</option>
                        <option value="pending_expert">Agricultural Expert</option>
                    </select>
                </div>
                
                <!-- Additional fields for farmer -->
                <div id="farmer_fields" style="display:none;">
                    <div class="form-group">
                        <label for="land_size">Land Size (in acres):</label>
                        <input type="number" id="land_size" name="land_size">
                    </div>
                    <div class="form-group">
                        <label for="soil_type">Soil Type:</label>
                        <input type="text" id="soil_type" name="soil_type">
                    </div>
                </div>

                <!-- Additional fields for expert -->
                <div id="expert_fields" style="display:none;">
                     <div class="form-group">
                        <label for="specialization">Specialization:</label>
                        <input type="text" id="specialization" name="specialization">
                    </div>
                    <div class="form-group">
                        <label for="experience_years">Years of Experience:</label>
                        <input type="number" id="experience_years" name="experience_years" min="0">
                    </div>
                    <div class="form-group">
                        <label for="resume">Resume/Portfolio (PDF, DOC, DOCX):</label>
                        <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx">
                    </div>
                </div>

                <button type="submit" class="btn">Register</button>
            </form>
            <p>Already have an account? <a href="login.php">Login here</a></p>

            <?php elseif ($step === 'verify'): ?>
            <form method="POST" action="register.php?step=verify">
                <p>An OTP has been sent to your phone number. Please enter it below.</p>
                <div class="form-group">
                    <label for="otp">OTP:</label>
                    <input type="text" id="otp" name="otp" required>
                </div>
                <button type="submit" class="btn">Verify OTP</button>
            </form>
            <p><a href="register.php">Start Over</a></p>
            
            <?php elseif ($step === 'success'): ?>
            <p>You can now proceed to the login page.</p>
            <a href="login.php" class="btn">Go to Login</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('role').addEventListener('change', function() {
            document.getElementById('farmer_fields').style.display = this.value === 'farmer' ? 'block' : 'none';
            document.getElementById('expert_fields').style.display = this.value === 'pending_expert' ? 'block' : 'none';
        });

        // Geolocation functionality
        const getLocationBtn = document.getElementById('getLocationBtn');
        if (getLocationBtn) {
            getLocationBtn.addEventListener('click', function() {
                if (navigator.geolocation) {
                    this.textContent = 'Getting...';
                    this.disabled = true;
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        document.getElementById('location').value = lat + ',' + lng;
                        getLocationBtn.textContent = 'Location Set';
                        getLocationBtn.disabled = false;
                    }, function(error) {
                        alert('Error: ' + error.message);
                        getLocationBtn.textContent = 'Get My Location';
                        getLocationBtn.disabled = false;
                    });
                } else {
                    alert('Geolocation is not supported by this browser.');
                }
            });
        }
    </script>
</body>
</html>