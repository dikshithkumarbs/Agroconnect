<?php
require_once '../config.php';
require_once '../includes/functions.php';
include '../includes/header.php';
redirectIfNotRole('admin');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_equipment'])) {
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);
        $price_per_day = floatval($_POST['price_per_day']);
        $location = sanitize($_POST['location']);
        $image_path = '';

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/equipment/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;

            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= 5000000) { // 5MB limit
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/equipment/' . $file_name;
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO equipment (name, description, type, price_per_day, location, image) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdss", $name, $description, $type, $price_per_day, $location, $image_path);

        if ($stmt->execute()) {
            $message = '<div class="success">Equipment added successfully!</div>';
        } else {
            $message = '<div class="error">Failed to add equipment.</div>';
        }
    } elseif (isset($_POST['edit_equipment'])) {
        $equipment_id = intval($_POST['equipment_id']);
        $name = sanitize($_POST['name']);
        $description = sanitize($_POST['description']);
        $type = sanitize($_POST['type']);
        $price_per_day = floatval($_POST['price_per_day']);
        $location = sanitize($_POST['location']);
        $image_path = sanitize($_POST['existing_image']);

        // Handle image upload for edit
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/equipment/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
            $target_file = $upload_dir . $file_name;

            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($imageFileType, $allowed_types) && $_FILES['image']['size'] <= 5000000) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_path = 'uploads/equipment/' . $file_name;
                    // Delete old image if exists
                    if (!empty($_POST['existing_image']) && file_exists('../' . $_POST['existing_image'])) {
                        unlink('../' . $_POST['existing_image']);
                    }
                }
            }
        }

        $stmt = $conn->prepare("UPDATE equipment SET name = ?, description = ?, type = ?, price_per_day = ?, location = ?, image = ? WHERE id = ?");
        $stmt->bind_param("sssdssi", $name, $description, $type, $price_per_day, $location, $image_path, $equipment_id);

        if ($stmt->execute()) {
            $message = '<div class="success">Equipment updated successfully!</div>';
        } else {
            $message = '<div class="error">Failed to update equipment.</div>';
        }
    } elseif (isset($_POST['delete_equipment'])) {
        $equipment_id = intval($_POST['equipment_id']);
        // Get image path before deleting
        $stmt = $conn->prepare("SELECT image FROM equipment WHERE id = ?");
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $image = $result->fetch_assoc()['image'];
            if (!empty($image) && file_exists('../' . $image)) {
                unlink('../' . $image);
            }
        }

        $stmt = $conn->prepare("DELETE FROM equipment WHERE id = ?");
        $stmt->bind_param("i", $equipment_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Equipment deleted successfully!</div>';
        } else {
            $message = '<div class="error">Failed to delete equipment.</div>';
        }
    } elseif (isset($_POST['toggle_availability'])) {
        $equipment_id = intval($_POST['equipment_id']);
        $stmt = $conn->prepare("UPDATE equipment SET availability = NOT availability WHERE id = ?");
        $stmt->bind_param("i", $equipment_id);
        if ($stmt->execute()) {
            $message = '<div class="success">Equipment availability updated!</div>';
        } else {
            $message = '<div class="error">Failed to update availability.</div>';
        }
    }
}

// Get all equipment
$stmt = $conn->prepare("SELECT * FROM equipment ORDER BY created_at DESC");
$stmt->execute();
$equipment = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agro Connect - Equipment Management</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../farmer/bookings/bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<div class="container">
    <h2>Equipment Management</h2>

    <?php echo $message; ?>

    <div class="card">
        <div class="header-actions">
            <button id="add-equipment-btn" class="btn">Add Equipment</button>
        </div>

    </div>

    <!-- Add Equipment Modal -->
    <div id="add-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" id="add-close">&times;</span>
            <h3>Add New Equipment</h3>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Equipment Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="type">Type:</label>
                    <select id="type" name="type" required>
                        <option value="machinery">Machinery</option>
                        <option value="tools">Tools</option>
                        <option value="vehicles">Vehicles</option>
                        <option value="irrigation">Irrigation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price_per_day">Price per Day (₹):</label>
                    <input type="number" step="0.01" id="price_per_day" name="price_per_day" required>
                </div>
                <div class="form-group">
                    <label for="location">Location:</label>
                    <input type="text" id="location" name="location" required>
                </div>
                <div class="form-group">
                    <label for="image">Equipment Image:</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <small>Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB</small>
                </div>
                <button type="submit" name="add_equipment" class="btn">Add Equipment</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>All Equipment</h3>

        <!-- View Toggle Buttons -->
        <div class="view-toggle" style="margin-bottom: 20px;">
            <button id="grid-view-btn" class="btn active">Grid View</button>
            <button id="card-view-btn" class="btn">Card View</button>
            <button id="list-view-btn" class="btn">List View</button>
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
                                <th>Price/Day</th>
                                <th>Location</th>
                                <th>Availability</th>
                                <th>Actions</th>
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
                                    <td>₹<?php echo number_format($item['price_per_day'], 2); ?></td>
                                    <td><?php echo $item['location']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $item['availability'] ? 'available' : 'unavailable'; ?>">
                                            <?php echo $item['availability'] ? '● Available' : '● Unavailable'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-small edit-btn" data-id="<?php echo $item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                            data-description="<?php echo htmlspecialchars($item['description']); ?>" 
                                            data-type="<?php echo $item['type']; ?>" 
                                            data-price="<?php echo $item['price_per_day']; ?>" 
                                            data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                                            data-image="<?php echo $item['image']; ?>">Edit</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="toggle_availability" class="btn btn-small">
                                                <?php echo $item['availability'] ? 'Make Unavailable' : 'Make Available'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this equipment?')">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_equipment" class="btn btn-danger btn-small">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Grid View -->
                    <div class="equipment-grid" id="equipment-grid" style="display: grid;">
                        <?php
                        $equipment->data_seek(0);
                        while ($item = $equipment->fetch_assoc()):
                        ?>
                            <div class="equipment-item equipment-card" 
                                 data-id="<?php echo $item['id']; ?>" 
                                 data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                 data-description="<?php echo htmlspecialchars($item['description']); ?>" 
                                 data-type="<?php echo $item['type']; ?>" 
                                 data-price="<?php echo $item['price_per_day']; ?>" 
                                 data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                                 data-image="<?php echo $item['image']; ?>"
                                 data-availability="<?php echo $item['availability']; ?>" 
                                 data-date="<?php echo strtotime($item['created_at']); ?>" 
                                 >
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
                                        <button class="btn btn-small edit-btn" data-id="<?php echo $item['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                            data-description="<?php echo htmlspecialchars($item['description']); ?>" 
                                            data-type="<?php echo $item['type']; ?>" 
                                            data-price="<?php echo $item['price_per_day']; ?>" 
                                            data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                                            data-image="<?php echo $item['image']; ?>">Edit</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="toggle_availability" class="btn btn-small">
                                                <?php echo $item['availability'] ? 'Make Unavailable' : 'Make Available'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this equipment?')">
                                            <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_equipment" class="btn btn-danger btn-small">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Card View -->
                    <div class="equipment-cards" id="equipment-cards" style="display: none;">
                    <?php
                    $equipment->data_seek(0); // Reset pointer
                    while ($item = $equipment->fetch_assoc()):
                    ?>
                        <div class="equipment-card-large" 
                             data-id="<?php echo $item['id']; ?>"
                             data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                             data-description="<?php echo htmlspecialchars($item['description']); ?>" 
                             data-type="<?php echo $item['type']; ?>" 
                             data-price="<?php echo $item['price_per_day']; ?>" 
                             data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                             data-image="<?php echo $item['image']; ?>"
                             data-availability="<?php echo $item['availability']; ?>" 
                             data-date="<?php echo strtotime($item['created_at']); ?>"
                             >
                            <div class="card-header">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="../<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                <?php else: ?>
                                    <div style="width: 280px; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                        <span style="color: #999;">No Image Available</span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-info">
                                    <h3><?php echo $item['name']; ?></h3>
                                    <p><?php echo $item['description']; ?></p>
                                    <div class="status-badge <?php echo $item['availability'] ? 'available' : 'unavailable'; ?>">
                                        <?php echo $item['availability'] ? '● Available' : '● Unavailable'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-details">
                                <div class="detail-item">
                                    <strong>Type:</strong> 
                                    <span><?php echo ucfirst($item['type']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong>Price:</strong> 
                                    <span>₹<?php echo number_format($item['price_per_day'], 2); ?>/day</span>
                                </div>
                                <div class="detail-item">
                                    <strong>Location:</strong> 
                                    <span><?php echo $item['location']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong>Added:</strong> 
                                    <span><?php echo date('M d, Y', strtotime($item['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="card-actions">
                                <button class="btn edit-btn" data-id="<?php echo $item['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($item['name']); ?>" 
                                    data-description="<?php echo htmlspecialchars($item['description']); ?>" 
                                    data-type="<?php echo $item['type']; ?>" 
                                    data-price="<?php echo $item['price_per_day']; ?>" 
                                    data-location="<?php echo htmlspecialchars($item['location']); ?>" 
                                    data-image="<?php echo $item['image']; ?>">Edit Equipment</button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="toggle_availability" class="btn">
                                        <?php echo $item['availability'] ? 'Mark Unavailable' : 'Mark Available'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete('Are you sure you want to delete this equipment?')">
                                    <input type="hidden" name="equipment_id" value="<?php echo $item['id']; ?>">
                                    <button type="submit" name="delete_equipment" class="btn btn-danger">Delete Equipment</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>No equipment found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Equipment</h3>
            <form method="POST" enctype="multipart/form-data" id="edit-form">
                <input type="hidden" name="equipment_id" id="edit-equipment-id">
                <input type="hidden" name="existing_image" id="edit-existing-image">
                <div class="form-group">
                    <label for="edit-name">Equipment Name:</label>
                    <input type="text" id="edit-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit-description">Description:</label>
                    <textarea id="edit-description" name="description" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit-type">Type:</label>
                    <select id="edit-type" name="type" required>
                        <option value="machinery">Machinery</option>
                        <option value="tools">Tools</option>
                        <option value="vehicles">Vehicles</option>
                        <option value="irrigation">Irrigation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-price">Price per Day (₹):</label>
                    <input type="number" step="0.01" id="edit-price" name="price_per_day" required>
                </div>
                <div class="form-group">
                    <label for="edit-location">Location:</label>
                    <input type="text" id="edit-location" name="location" required>
                </div>
                <div class="form-group">
                    <label for="edit-image">Equipment Image:</label>
                    <input type="file" id="edit-image" name="image" accept="image/*">
                    <small>Leave empty to keep current image. Allowed formats: JPG, JPEG, PNG, GIF. Max size: 5MB</small>
                    <div id="current-image-preview"></div>
                </div>
                <button type="submit" name="edit_equipment" class="btn">Update Equipment</button>
            </form>
        </div>
    </div>

</div>


<script>
// Add Equipment Modal
const addModal = document.getElementById('add-modal');
const addBtn = document.getElementById('add-equipment-btn');
const addClose = document.getElementById('add-close');

addBtn.onclick = function() {
    addModal.style.display = "block";
}

addClose.onclick = function() {
    addModal.style.display = "none";
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == addModal) {
        addModal.style.display = "none";
    }
    if (event.target == editModal) {
        editModal.style.display = "none";
    }
}

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

// Edit Equipment Modal
const editModal = document.getElementById('edit-modal');
const editClose = editModal.querySelector('.close');

editClose.onclick = function() {
    editModal.style.display = "none";
}

document.querySelectorAll('.edit-btn').forEach(button => {
    button.onclick = function(e) {
        e.stopPropagation();
        const id = this.dataset.id;
        const name = this.dataset.name;
        const description = this.dataset.description;
        const type = this.dataset.type;
        const price = this.dataset.price;
        const location = this.dataset.location;
        const image = this.dataset.image;

        document.getElementById('edit-equipment-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-description').value = description;
        document.getElementById('edit-type').value = type;
        document.getElementById('edit-price').value = price;
        document.getElementById('edit-location').value = location;
        document.getElementById('edit-existing-image').value = image;

        const imagePreview = document.getElementById('current-image-preview');
        if (image) {
            imagePreview.innerHTML = `<img src="../${image}" alt="Current Image" style="max-width: 100px; margin-top: 10px;">`;
        } else {
            imagePreview.innerHTML = '';
        }

        editModal.style.display = 'block';
    }
});

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
        card: {
            button: document.getElementById('card-view-btn'),
            container: document.getElementById('equipment-cards'),
            displayStyle: 'flex'
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