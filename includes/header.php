<?php
require_once '../config.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <h1><i class="fas fa-seedling"></i> Agro Connect</h1>
                </div>
                <ul class="nav-menu">
                    <li><a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <?php if (getUserRole() === 'farmer'): ?>
                        <li><a href="weather.php"><i class="fas fa-cloud-sun"></i> Weather</a></li>
                        <li><a href="crop_advisory.php"><i class="fas fa-leaf"></i> Crop Advisory</a></li>
                        <li><a href="chat.php"><i class="fas fa-comments"></i> Chat with Expert</a></li>
                        <li><a href="equipment.php"><i class="fas fa-tractor"></i> Equipment</a></li>

                        <li><a href="my_bookings.php"><i class="fas fa-calendar-alt"></i> My Bookings</a></li>
                    <?php elseif (getUserRole() === 'expert'): ?>
                        <li><a href="farmer_management.php"><i class="fas fa-users"></i> Farmers</a></li>
                        <li><a href="advisory.php"><i class="fas fa-clipboard-list"></i> Advisory</a></li>
                        <li><a href="chat.php"><i class="fas fa-comments"></i> Chat</a></li>
                    <?php elseif (getUserRole() === 'admin'): ?>
                        <li><a href="user_management.php"><i class="fas fa-user-cog"></i> Users</a></li>
                        <li><a href="expert_assignments.php"><i class="fas fa-user-tie"></i> Expert-Farmer Assignments</a></li>
                        <li><a href="equipment_management.php"><i class="fas fa-tools"></i> Equipment</a></li>
                        <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a></li>
                        <li><a href="bookings.php"><i class="fas fa-calendar-alt"></i> Bookings</a></li>
                    <?php endif; ?>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
                <div class="nav-user">
                    <span>Welcome, <?php echo $_SESSION['user_name'] ?? 'User'; ?> (<?php echo ucfirst(getUserRole()); ?>)</span>
                </div>
            </div>
        </nav>
    </header>
    <main>