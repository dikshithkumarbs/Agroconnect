<?php
require_once '../config.php';
require_once '../includes/functions.php';
redirectIfNotRole('farmer');
include '../includes/header.php';

$user_id = $_SESSION['user_id'];

// Get user's wishlist items
$stmt = $conn->prepare("SELECT w.*, e.name, e.type, e.location, e.price_per_day, e.image, e.description FROM wishlist w JOIN equipment e ON w.equipment_id = e.id WHERE w.farmer_id = ? ORDER BY e.name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - My Wishlist</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <h2>My Wishlist</h2>

    <!-- View Toggle Buttons -->
    <div class="view-toggle">
        <button id="list-view-btn" class="btn btn-secondary active">
            <i class="fas fa-list"></i> List View
        </button>
        <button id="grid-view-btn" class="btn btn-secondary">
            <i class="fas fa-th"></i> Grid View
        </button>
    </div>

    <?php if ($wishlist->num_rows > 0): ?>
        <!-- List View (Table) -->
        <div id="wishlist-list-view" class="view-container">
            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Equipment</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Price/Day</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $wishlist->data_seek(0); // Reset pointer ?>
                        <?php while ($item = $wishlist->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $item['name']; ?></td>
                                <td><?php echo ucfirst($item['type']); ?></td>
                                <td><?php echo $item['location']; ?></td>
                                <td>₹<?php echo number_format($item['price_per_day'], 2); ?></td>
                                <td>
                                    <button onclick="bookEquipment(<?php echo $item['equipment_id']; ?>)" class="btn btn-small">Book Now</button>
                                    <button onclick="removeFromWishlist(<?php echo $item['equipment_id']; ?>, this)" class="btn btn-danger btn-small">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grid View (Cards) -->
        <div id="wishlist-grid-view" class="view-container" style="display: none;">
            <div class="equipment-grid">
                <?php $wishlist->data_seek(0); // Reset pointer ?>
                <?php while ($item = $wishlist->fetch_assoc()): ?>
                    <div class="equipment-card" data-equipment-id="<?php echo $item['equipment_id']; ?>">
                        <div class="card-header">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" style="width: 100%; height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">No Image</div>
                            <?php endif; ?>
                        </div>
                        <div class="content">
                            <h4><?php echo $item['name']; ?></h4>
                            <p><?php echo substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                            <div class="card-details">
                                <div class="detail-item"><strong>Type:</strong> <?php echo ucfirst($item['type']); ?></div>
                                <div class="detail-item"><strong>Location:</strong> <?php echo $item['location']; ?></div>
                                <div class="detail-item"><strong>Price:</strong> ₹<?php echo number_format($item['price_per_day'], 2); ?>/day</div>
                            </div>
                            <div class="card-actions">
                                <button onclick="bookEquipment(<?php echo $item['equipment_id']; ?>)" class="btn">Book Now</button>
                                <button onclick="removeFromWishlist(<?php echo $item['equipment_id']; ?>, this)" class="btn btn-danger btn-small">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <p style="text-align: center; padding: 40px;">Your wishlist is empty. <a href="equipment.php">Browse equipment</a> to add items to your wishlist.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Booking Modal -->
<div id="booking-modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeBookingModal()">&times;</span>
        <h3>Book Equipment</h3>
        <form id="booking-form">
            <input type="hidden" id="equipment-id" name="equipment_id">
            <div class="form-group">
                <label for="start-date">Start Date:</label>
                <input type="date" id="start-date" name="start_date" required>
            </div>
            <div class="form-group">
                <label for="end-date">End Date:</label>
                <input type="date" id="end-date" name="end_date" required>
            </div>
            <div id="booking-details" style="margin: 15px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;"></div>
            <button type="submit" class="btn">Confirm Booking</button>
        </form>
    </div>
</div>

<script src="../js/scripts.js"></script>
<script>
function bookEquipment(equipmentId) {
    document.getElementById('equipment-id').value = equipmentId;
    document.getElementById('booking-modal').style.display = 'block';
    calculateBookingCost();
}

function closeBookingModal() {
    document.getElementById('booking-modal').style.display = 'none';
}

function calculateBookingCost() {
    const equipmentId = document.getElementById('equipment-id').value;
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const detailsDiv = document.getElementById('booking-details');
    
    if (startDate && endDate) {
        fetch('../farmer/bookings/get_booking_cost.php?equipment_id=' + equipmentId + '&start_date=' + startDate + '&end_date=' + endDate)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    detailsDiv.innerHTML = `<strong>Duration:</strong> ${data.days} days<br><strong>Total Cost:</strong> ₹${data.total_cost}`;
                } else {
                    detailsDiv.innerHTML = `<span style="color: red;">Error: ${data.error}</span>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                detailsDiv.innerHTML = '<span style="color: red;">An error occurred while calculating cost.</span>';
            });
    } else {
        detailsDiv.innerHTML = '';
    }
}

function removeFromWishlist(equipmentId, buttonElement) {
    if (confirm('Remove this item from your wishlist?')) {
        fetch('../farmer/bookings/toggle_wishlist.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'equipment_id=' + equipmentId + '&remove=true' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the item from DOM without reloading
                    const card = buttonElement.closest('.equipment-card, tr');
                    if (card) card.remove();
                    // Check if no items left and reload if empty
                    const remaining = document.querySelectorAll('.equipment-card, tbody tr');
                    if (remaining.length === 0) location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while removing the item from your wishlist.');
            });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initViewToggle('wishlist');
});

document.getElementById('booking-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('../farmer/bookings/book_equipment.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closeBookingModal();
                window.location.href = 'my_bookings.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while booking.');
        });
});

document.getElementById('start-date').addEventListener('change', calculateBookingCost);
document.getElementById('end-date').addEventListener('change', calculateBookingCost);
</script>

<style>
.view-toggle {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
}

.view-toggle .btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background-color: #f8f9fa;
    color: #333;
    cursor: pointer;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.view-toggle .btn:hover {
    background-color: #e9ecef;
}

.view-toggle .btn.active {
    background-color: #2c5530;
    color: white;
    border-color: #2c5530;
}

.view-container {
    transition: opacity 0.3s ease;
}

.equipment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.equipment-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.equipment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.equipment-card .card-header img {
    width: 100%;
    height: 150px;
    object-fit: cover;
}

.equipment-card .content {
    padding: 20px;
}

.equipment-card h4 {
    margin: 0 0 10px 0;
    color: #2c5530;
    font-size: 1.2em;
}

.equipment-card p {
    margin: 8px 0;
    color: #555;
}

.card-details {
    margin: 15px 0;
}

.detail-item {
    margin: 5px 0;
    font-size: 0.9em;
}

.detail-item strong {
    color: #333;
}

.card-actions {
    margin-top: 15px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-small {
    padding: 6px 12px;
    font-size: 0.9em;
    border-radius: 4px;
}
</style>

<?php include '../includes/footer.php'; ?>
