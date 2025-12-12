-- Users table for administrators
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Experts table
CREATE TABLE experts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(100),
    specialization VARCHAR(100),
    experience_years INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    rating DECIMAL(3,2) DEFAULT 0.00,
    num_ratings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Farmers table
CREATE TABLE farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(100),
    land_size DECIMAL(10,2),
    soil_type VARCHAR(50),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Equipment table
CREATE TABLE equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type VARCHAR(50),
    price_per_day DECIMAL(10,2) NOT NULL,
    location VARCHAR(100),
    image VARCHAR(255),
    status ENUM('available', 'unavailable') DEFAULT 'available',
    rating DECIMAL(3,2) DEFAULT 0.00,
    num_ratings INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    equipment_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
);

-- Soil reports table
CREATE TABLE soil_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    report_file VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'reviewed') DEFAULT 'pending',
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE
);

-- Crop recommendations table
CREATE TABLE crop_recommendations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    soil_report_id INT NOT NULL,
    expert_id INT NOT NULL,
    farmer_id INT NOT NULL,
    recommendation TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (soil_report_id) REFERENCES soil_reports(id) ON DELETE CASCADE,
    FOREIGN KEY (expert_id) REFERENCES experts(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE
);

-- Messages table
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    receiver_id INT NOT NULL,
    receiver_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    message TEXT NOT NULL,
    attachment VARCHAR(255),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_sender (sender_id, sender_role, sent_at),
    INDEX idx_receiver (receiver_id, receiver_role, sent_at),
    INDEX idx_conversation ((CASE WHEN sender_role = 'farmer' THEN CONCAT(sender_id, '_', receiver_id) ELSE CONCAT(receiver_id, '_', sender_id) END), sent_at),
    INDEX idx_read_status (read_at),
    INDEX idx_sent_at (sent_at)
);

-- Message edit history table for tracking changes
CREATE TABLE message_edit_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    old_message TEXT,
    new_message TEXT,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edited_by INT NOT NULL,
    edited_by_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    INDEX idx_message_id (message_id),
    INDEX idx_edited_at (edited_at)
);

-- Rate limiting table for message sending
CREATE TABLE message_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    action_type ENUM('send_message', 'edit_message', 'delete_message', 'search_messages') NOT NULL,
    action_count INT DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rate_limit (user_id, user_role, action_type, window_start),
    INDEX idx_window_start (window_start)
);

-- User activity table for connection status
CREATE TABLE user_activity (
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_online BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (user_id, user_role),
    INDEX idx_last_activity (last_activity),
    INDEX idx_is_online (is_online)
);

-- Typing status table (created dynamically in chat_functions.php but adding here for completeness)
CREATE TABLE IF NOT EXISTS typing_status (
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    other_id INT NOT NULL,
    is_typing BOOLEAN DEFAULT FALSE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, user_role, other_id),
    INDEX idx_last_updated (last_updated)
);

-- Notifications table
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_role ENUM('farmer', 'expert', 'admin') NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Expert ratings table
CREATE TABLE expert_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    expert_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE,
    FOREIGN KEY (expert_id) REFERENCES experts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_expert_rating (farmer_id, expert_id)
);

-- Equipment ratings table
CREATE TABLE equipment_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    equipment_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    UNIQUE KEY unique_equipment_rating (farmer_id, equipment_id)
);

-- Knowledge center articles
CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category VARCHAR(100),
    author_id INT,
    author_role ENUM('expert', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES experts(id) ON DELETE SET NULL
);

-- Expert-Farmer assignments table
CREATE TABLE expert_farmer_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expert_id INT NOT NULL,
    farmer_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') DEFAULT 'active',
    notes TEXT,
    FOREIGN KEY (expert_id) REFERENCES experts(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (expert_id, farmer_id)
);

-- Wishlist table
CREATE TABLE wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT NOT NULL,
    equipment_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE CASCADE,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    UNIQUE KEY unique_wishlist_item (farmer_id, equipment_id)
);

-- Insert sample data
INSERT INTO admins (name, email, password, phone, location) VALUES
('Admin User', 'admin@agroconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1234567890', 'Bengaluru');

INSERT INTO experts (name, email, password, phone, location, specialization, experience_years) VALUES
('Expert One', 'expert1@agroconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876543210', 'Mysore', 'Crop Science', 5);

INSERT INTO farmers (name, email, password, phone, location, land_size, soil_type) VALUES
('Farmer One', 'farmer1@agroconnect.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '5556667777', 'Mandya', 5.5, 'Alluvial');

INSERT INTO equipment (name, description, type, price_per_day, location, image, rating, num_ratings) VALUES
('Tractor', 'Powerful tractor for farming operations', 'machinery', 500.00, 'Bengaluru', 'tractor.jpg', 4.5, 12),
('Combine Harvester', 'Modern combine harvester for efficient harvesting', 'machinery', 800.00, 'Mysore', 'harvester.jpg', 4.2, 8),
('Pesticide Sprayer', 'High-pressure pesticide sprayer', 'tools', 100.00, 'Mandya', 'sprayer.jpg', 4.0, 15),
('Rotavator', 'Soil preparation and tilling equipment', 'machinery', 300.00, 'Bengaluru', 'rotavator.jpg', 4.3, 10),
('Seed Drill', 'Precision seed planting machine', 'machinery', 250.00, 'Mysore', 'seed_drill.jpg', 4.1, 6),
('Power Tiller', 'Compact tilling machine for small farms', 'machinery', 200.00, 'Mandya', 'power_tiller.jpg', 4.4, 9),
('Rice Transplanter', 'Automated rice seedling transplanter', 'machinery', 400.00, 'Bengaluru', 'rice_transplanter.jpg', 4.6, 7),
('Thresher', 'Grain threshing machine', 'machinery', 350.00, 'Mysore', 'thresher.jpg', 4.0, 11),
('Drip Irrigation System', 'Water-efficient irrigation setup', 'irrigation', 150.00, 'Mandya', 'drip_irrigation.jpg', 4.7, 13),
('Cultivator', 'Soil cultivation and weed control', 'machinery', 180.00, 'Bengaluru', 'cultivator.jpg', 3.9, 8),
('Plough', 'Traditional and modern ploughing equipment', 'machinery', 120.00, 'Mysore', 'plough.jpg', 4.2, 14),
('Hay Baler', 'Hay and fodder baling machine', 'machinery', 280.00, 'Mandya', 'hay_baler.jpg', 4.3, 5),
('Fertilizer Spreader', 'Uniform fertilizer distribution', 'tools', 90.00, 'Bengaluru', 'fertilizer_spreader.jpg', 4.1, 10),
('Weedicide Sprayer', 'Selective weed control sprayer', 'tools', 110.00, 'Mysore', 'weedicide_sprayer.jpg', 4.5, 12),
('Groundnut Digger', 'Groundnut harvesting machine', 'machinery', 220.00, 'Mandya', 'groundnut_digger.jpg', 4.0, 6),
('Sugarcane Harvester', 'Sugarcane cutting and loading machine', 'machinery', 600.00, 'Bengaluru', 'sugarcane_harvester.jpg', 4.4, 8),
('Cotton Picker', 'Cotton harvesting machine', 'machinery', 450.00, 'Mysore', 'cotton_picker.jpg', 4.2, 9),
('Potato Digger', 'Potato harvesting equipment', 'machinery', 190.00, 'Mandya', 'potato_digger.jpg', 4.3, 7);

INSERT INTO articles (title, content, category, author_id, author_role) VALUES
('Modern Farming Techniques', 'Learn about the latest farming techniques...', 'farming', 1, 'expert'),
('Soil Health Management', 'Understanding soil health is crucial...', 'soil', 1, 'expert');

-- Assign expert to farmer
INSERT INTO expert_farmer_assignments (expert_id, farmer_id, status) VALUES (1, 1, 'active');