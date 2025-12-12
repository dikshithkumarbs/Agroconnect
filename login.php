<?php
require_once 'config.php';
require_once 'includes/functions.php';



$error = '';

// Check for expired session
if (isset($_GET['expired']) && $_GET['expired'] == '1') {
    $error = 'Your session has expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_or_phone = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (empty($email_or_phone) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Try to authenticate against each role table
        $roles = ['admin', 'expert', 'farmer'];
        $authenticated = false;

        foreach ($roles as $role) {
            // Try email first
            $user = getUserByEmail($email_or_phone, $role);
            // If not found by email, try phone
            if (!$user) {
                $user = getUserByPhone($email_or_phone, $role);
            }

            if ($user && password_verify($password, $user['password'])) {
                // Check if expert is approved
                if ($role === 'expert' && $user['status'] !== 'active') {
                    $error = 'Your expert account is pending approval. Please wait for admin approval.';
                    break;
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $role;
                $_SESSION['last_activity'] = time();
                $authenticated = true;

                // Redirect based on role
                switch ($role) {
                    case 'farmer':
                        header('Location: farmer/dashboard.php');
                        break;
                    case 'expert':
                        header('Location: expert/dashboard.php');
                        break;
                    case 'admin':
                        header('Location: admin/dashboard.php');
                        break;
                }
                exit();
            }
        }

        if (!$authenticated) {
            $error = 'Invalid email/phone or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Agro Connect</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h2>Login to Agro Connect</h2>
            <?php if (isLoggedIn()): ?>
                <div class="info">You are currently logged in as <?php echo ucfirst(getUserRole()); ?>. Logging in with different credentials will switch your session. <a href="logout.php">Logout first</a> if you prefer.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email or Phone Number</label>
                    <input type="text" id="email" name="email" placeholder="Enter Registered Email or Phone Number" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>
