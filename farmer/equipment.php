<?php
require_once '../config.php';
require_once '../includes/functions.php';
include '../includes/header.php';
redirectIfNotRole('farmer');

$farmer_id = $_SESSION['user_id'];

// Get all equipment
$stmt = $conn->prepare("SELECT * FROM equipment ORDER BY created_at DESC");
$stmt->execute();
$equipment = $stmt->get_result();

// Get user's wishlist
$wishlist = [];
$stmt = $conn->prepare("SELECT equipment_id FROM wishlist WHERE farmer_id = ?");
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$wishlist_result = $stmt->get_result();
while ($row = $wishlist_result->fetch_assoc()) {
    $wishlist[] = $row['equipment_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - Equipment Marketplace</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <h2>Equipment Marketplace</h2>

        <div class="card">
            <h3>All Equipment</h3>

            <!-- View Toggle Buttons -->
            <div class="view-actions-container">
                <div class="view-toggle">
                    <button id="grid-view-btn" class="btn active"><i class="fas fa-th-large"></i> Grid View</button>
                    <button id="list-view-btn" class="btn"><i class="fas fa-list"></i> List View</button>
                </div>
                <div class="header-actions">
                    <a href="wishlist.php" class="btn"><i class="fas fa-heart"></i> My Wishlist</a>
                    <a href="my_bookings.php" class="btn"><i class="fas fa-shopping-cart"></i> My Bookings</a>
                </div>
            </div>

            <!-- Sorting and Filtering -->
            <div class="filters-section">
                <div class="filters">
                    <div class="filter-group">
                        <label for="filter-type">Equipment Type</label>
                        <select id="filter-type">
                            <option value="">All Types</option>
                            <option value="machinery">Machinery</option>
                            <option value="tools">Tools</option>
                            <option value="vehicles">Vehicles</option>
                            <option value="irrigation">Irrigation</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-availability">Availability</label>
                        <select id="filter-availability">
                            <option value="">All Status</option>
                            <option value="1">Available</option>
                            <option value="0">Unavailable</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="search-equipment">Search Equipment</label>
                        <input type="text" id="search-equipment" placeholder="Enter name...">
                    </div>
                </div>
                <div class="active-filters" id="active-filters">
                    <!-- Active filters will be added here dynamically -->
                </div>
            </div>

            <div id="equipment-container">
                <?php if ($equipment->num_rows > 0): ?>
                    <div id="equipment-views-container">
                        <!-- List View -->
                        <table class="table equipment-list" id="equipment-table" style="display: none;">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Price/Day</th>
                                    <th>Description</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $equipment->data_seek(0);
                                while ($item = $equipment->fetch_assoc()): 
                                ?>
                                    <tr class="equipment-item" data-type="<?php echo $item['type']; ?>" data-availability="<?php echo $item['availability']; ?>" data-price="<?php echo $item['price_per_day']; ?>" data-name="<?php echo strtolower($item['name']); ?>" data-date="<?php echo strtotime($item['created_at']); ?>">
                                        <td>
                                            <?php if (!empty($item['image'])): ?>
                                                <img src="../<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <div style="width: 50px; height: 50px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">No Image</div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['name']; ?></td>
                                        <td><?php echo ucfirst($item['type']); ?></td>
                                        <td><?php echo $item['location']; ?></td>
                                        <td>₹<?php echo number_format($item['price_per_day'], 2); ?></td>
                                        <td><?php echo $item['description']; ?></td>
                                        <td>
                                            <?php if ($item['availability']): ?>
                                                <button class="btn btn-small" onclick="bookEquipment(<?php echo $item['id']; ?>)">Book Now</button>
                                            <?php else: ?>
                                                <button class="btn btn-small" disabled>Unavailable</button>
                                            <?php endif; ?>
                                            <button onclick="toggleWishlist(<?php echo $item['id']; ?>, this)" class="btn btn-small wishlist-btn <?php echo in_array($item['id'], $wishlist) ? 'wishlisted' : ''; ?>" style="margin-left: 5px;">
                                                <i class="fas fa-heart" style="color: <?php echo in_array($item['id'], $wishlist) ? 'red' : 'inherit'; ?>;"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>

                        <!-- Grid View -->
                        <div class="equipment-grid" id="equipment-grid" style="display: none;">
                            <?php
                            $equipment->data_seek(0);
                            while ($item = $equipment->fetch_assoc()):
                            ?>
                                <div class="equipment-item equipment-card" data-type="<?php echo $item['type']; ?>" data-availability="<?php echo $item['availability']; ?>" data-price="<?php echo $item['price_per_day']; ?>" data-name="<?php echo strtolower($item['name']); ?>" data-date="<?php echo strtotime($item['created_at']); ?>">
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="../<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                    <?php else: ?>
                                        <div class="no-image-placeholder">
                                            <span>No Image Available</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-content">
                                        <h4><?php echo $item['name']; ?></h4>
                                        <div class="equipment-details">
                                            <p><strong>Type:</strong> <?php echo ucfirst($item['type']); ?></p>
                                            <p><strong>Price:</strong> ₹<?php echo number_format($item['price_per_day'], 2); ?>/day</p>
                                            <p><strong>Location:</strong> <?php echo $item['location']; ?></p>
                                            <div class="status-badge <?php echo $item['availability'] ? 'available' : 'unavailable'; ?>">
                                                <?php echo $item['availability'] ? '● Available' : '● Unavailable'; ?>
                                            </div>
                                        </div>
                                        <div class="card-actions">
                                            <?php if ($item['availability']): ?>
                                                <button class="btn btn-small" onclick="bookEquipment(<?php echo $item['id']; ?>)">Book Now</button>
                                            <?php else: ?>
                                                <button class="btn btn-small" disabled>Unavailable</button>
                                            <?php endif; ?>
                                            <button onclick="toggleWishlist(<?php echo $item['id']; ?>, this)" class="btn btn-small wishlist-btn <?php echo in_array($item['id'], $wishlist) ? 'wishlisted' : ''; ?>" style="margin-left: 5px;">
                                                <i class="fas fa-heart" style="color: <?php echo in_array($item['id'], $wishlist) ? 'red' : 'inherit'; ?>;"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                </div>
            <?php else: ?>
                <p>No equipment found.</p>
            <?php endif; ?>
        </div>
    </div>
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

<script>
// Booking Modal
const bookingModal = document.getElementById('booking-modal');

function bookEquipment(equipmentId) {
    document.getElementById('equipment-id').value = equipmentId;
    bookingModal.style.display = 'block';
}

function closeBookingModal() {
    bookingModal.style.display = 'none';
}

document.getElementById('booking-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch('../farmer/bookings/book_equipment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        alert(data);
        closeBookingModal();
        window.location.href = 'my_bookings.php';
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while booking.');
    });
});

// Wishlist
function toggleWishlist(equipmentId, button) {
    fetch('../farmer/bookings/toggle_wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'equipment_id=' + equipmentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const heartIcon = button.querySelector('.fa-heart');
            if (data.wishlisted) {
                button.classList.add('wishlisted');
                heartIcon.style.color = 'red';
            } else {
                button.classList.remove('wishlisted');
                heartIcon.style.color = 'inherit';
            }
        }
    });
}

// Filtering and view toggle
// Filtering functionality
function initializeFiltersAndSort() {
    const filterType = document.getElementById('filter-type');
    const filterAvailability = document.getElementById('filter-availability');
    const searchInput = document.getElementById('search-equipment');
    const activeFilters = document.getElementById('active-filters');
    
    const allEquipmentItems = document.querySelectorAll('[data-type]');
    
    // Function to update active filters display
    function updateActiveFilters() {
        activeFilters.innerHTML = '';
        
        if (filterType.value) {
            const tag = createFilterTag('Type: ' + filterType.options[filterType.selectedIndex].text, () => {
                filterType.value = '';
                applyFilters();
            });
            activeFilters.appendChild(tag);
        }
        
        if (filterAvailability.value !== '') {
            const text = filterAvailability.options[filterAvailability.selectedIndex].text;
            const tag = createFilterTag('Status: ' + text, () => {
                filterAvailability.value = '';
                applyFilters();
            });
            activeFilters.appendChild(tag);
        }

        if (searchInput.value) {
            const tag = createFilterTag('Search: "' + searchInput.value + '"', () => {
                searchInput.value = '';
                applyFilters();
            });
            activeFilters.appendChild(tag);
        }
        
        // Show/hide the active filters section
        activeFilters.style.display = activeFilters.hasChildNodes() ? 'flex' : 'none';
    }
    
    // Create a filter tag element
    function createFilterTag(text, onRemove) {
        const tag = document.createElement('div');
        tag.className = 'filter-tag';
        tag.innerHTML = text + '<button type="button" aria-label="Remove filter">×</button>';
        tag.querySelector('button').addEventListener('click', onRemove);
        return tag;
    }
    
    // Function to apply filters
    function applyFilters() {
        const typeFilter = filterType.value.toLowerCase();
        const availabilityFilter = filterAvailability.value;
        const searchTerm = searchInput.value.toLowerCase();
        
        allEquipmentItems.forEach(item => {
            const matchesType = !typeFilter || item.dataset.type === typeFilter;
            const matchesAvailability = availabilityFilter === '' || item.dataset.availability === availabilityFilter;
            const matchesSearch = !searchTerm || item.dataset.name.includes(searchTerm);

            if (matchesType && matchesAvailability && matchesSearch) {
                item.style.display = '';
                item.classList.remove('filtered-out');
            } else {
                item.style.display = 'none';
                item.classList.add('filtered-out');
            }
        });
        
        updateActiveFilters();
    }
    
    // Event listeners
    filterType.addEventListener('change', applyFilters);
    filterAvailability.addEventListener('change', applyFilters);
    searchInput.addEventListener('input', applyFilters);
    
    // Initial application
    updateActiveFilters();
}

// View toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize filters and sorting
    initializeFiltersAndSort();
    
    // View switching functionality
    const viewsConfig = {
        grid: {
            button: document.getElementById('grid-view-btn'),
            container: document.getElementById('equipment-grid'),
            displayStyle: 'grid'
        },
        list: {
            button: document.getElementById('list-view-btn'),
            container: document.getElementById('equipment-table'),
            displayStyle: 'table'
        }
    };

    // Function to switch views
    function switchView(activeViewKey) {
        // Hide all views and remove active class from all buttons
        Object.keys(viewsConfig).forEach(viewKey => {
            const view = viewsConfig[viewKey];
            if (view.container) {
                view.container.style.display = 'none';
            }
            if (view.button) {
                view.button.classList.remove('active');
            }
        });

        // Show active view and set active class on button
        const activeView = viewsConfig[activeViewKey];
        if (activeView.container) {
            activeView.container.style.display = activeView.displayStyle;
        }
        if (activeView.button) {
            activeView.button.classList.add('active');
        }

        // Store the current view preference
        localStorage.setItem('preferredEquipmentView', activeViewKey);
    }

    // Add click event listeners to view buttons
    Object.keys(viewsConfig).forEach(viewKey => {
        const view = viewsConfig[viewKey];
        if (view.button) {
            view.button.addEventListener('click', () => switchView(viewKey));
        }
    });

    // Set initial view (either from localStorage or default to grid)
    const savedView = localStorage.getItem('preferredEquipmentView');
    switchView(savedView || 'grid');
});
</script>

<?php include '../includes/footer.php'; ?>