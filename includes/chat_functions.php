<?php
function isValidExpertFarmerPair($expert_id, $farmer_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 1 
        FROM expert_farmer_assignments 
        WHERE expert_id = ? 
        AND farmer_id = ? 
        AND status = 'active'
    ");
    $stmt->bind_param("ii", $expert_id, $farmer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

function sendChatMessage($sender_id, $sender_role, $receiver_id, $receiver_role, $message, $attachment = null) {
    global $conn;

    // Validate roles
    if (!in_array($sender_role, ['expert', 'farmer']) || !in_array($receiver_role, ['expert', 'farmer'])) {
        return [
            'success' => false,
            'error' => 'Invalid role specified'
        ];
    }

    // Check if it's a valid expert-farmer pair
    $expert_id = $sender_role === 'expert' ? $sender_id : $receiver_id;
    $farmer_id = $sender_role === 'farmer' ? $sender_id : $receiver_id;

    if (!isValidExpertFarmerPair($expert_id, $farmer_id)) {
        // Check if they were previously assigned (allow sending to previously assigned experts)
        $stmt = $conn->prepare("SELECT 1 FROM expert_farmer_assignments WHERE expert_id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $expert_id, $farmer_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            return [
                'success' => false,
                'error' => 'Not authorized to send messages to this user'
            ];
        }
    }

    // Rate limiting check for sending messages
    if (!checkRateLimit($sender_id, $sender_role, 'send_message', 30, 60)) { // 30 messages per minute
        return [
            'success' => false,
            'error' => 'Rate limit exceeded. Please wait before sending another message.'
        ];
    }

    // Input validation and sanitization
    $message = trim($message);
    if (empty($message) && !$attachment) {
        return [
            'success' => false,
            'error' => 'Message cannot be empty'
        ];
    }

    if (strlen($message) > 1000) {
        return [
            'success' => false,
            'error' => 'Message too long. Maximum 1000 characters allowed.'
        ];
    }

    // XSS prevention
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    // Handle file attachment if provided
    $attachment_path = null;
    if ($attachment && isset($attachment['tmp_name']) && is_uploaded_file($attachment['tmp_name'])) {
        // Validate file
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv'
        ];
        $max_size = 10 * 1024 * 1024; // 10MB

        if (!in_array($attachment['type'], $allowed_types)) {
            return [
                'success' => false,
                'error' => 'Invalid file type. Allowed: images, PDF, Word docs, text files, and videos (MP4, MOV, AVI, WMV).'
            ];
        }

        if ($attachment['size'] > $max_size) {
            return [
                'success' => false,
                'error' => 'File size too large. Maximum 10MB allowed.'
            ];
        }

        // Additional security: check for malicious file content
        $file_content = file_get_contents($attachment['tmp_name']);
        if (preg_match('/<\?php|<\?|\b(eval|exec|system|shell_exec|passthru)\b/i', $file_content)) {
            return [
                'success' => false,
                'error' => 'File contains potentially malicious content.'
            ];
        }

        // Generate unique filename
        $extension = pathinfo($attachment['name'], PATHINFO_EXTENSION);
        $filename = uniqid('chat_', true) . '.' . $extension;
        $upload_dir = __DIR__ . '/../uploads/chat_attachments/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $attachment_path = 'uploads/chat_attachments/' . $filename;
        $full_path = $upload_dir . $filename;
        
        if (!move_uploaded_file($attachment['tmp_name'], $full_path)) {
            return [
                'success' => false,
                'error' => 'Failed to upload file'
            ];
        }
    }

    // Insert the message with sent_at as current timestamp and read_at as NULL for new messages
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, sender_role, receiver_id, receiver_role, message, attachment, sent_at, read_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NULL)
    ");
    $stmt->bind_param("isssss", $sender_id, $sender_role, $receiver_id, $receiver_role, $message, $attachment_path);

    if ($stmt->execute()) {
        return [
            'success' => true,
            'message_id' => $stmt->insert_id
        ];
    }

    return [
        'success' => false,
        'error' => 'Failed to send message'
    ];
}

function getChatMessages($user_id, $user_role, $other_id, $last_message_id = 0) {
    global $conn;
    
    // Validate the chat participants
    if ($user_role === 'farmer') {
        if (!isValidExpertFarmerPair($other_id, $user_id)) {
            return [
                'success' => false,
                'error' => 'Not authorized to view this chat'
            ];
        }
    } else if ($user_role === 'expert') {
        // Check if this is a valid expert-farmer pair (either currently or previously assigned)
        if (!isValidExpertFarmerPair($user_id, $other_id)) {
            // Check if they were previously assigned
            $stmt = $conn->prepare("SELECT 1 FROM expert_farmer_assignments WHERE expert_id = ? AND farmer_id = ?");
            $stmt->bind_param("ii", $user_id, $other_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                return [
                    'success' => false,
                    'error' => 'Not authorized to view this chat'
                ];
            }
        }
    }
    
    // Get messages - Fixed the query to properly handle both directions
    $stmt = $conn->prepare("
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_role = 'farmer' THEN f.name 
                WHEN m.sender_role = 'expert' THEN e.name 
                ELSE 'Unknown'
            END as sender_name
        FROM messages m
        LEFT JOIN farmers f ON m.sender_id = f.id AND m.sender_role = 'farmer'
        LEFT JOIN experts e ON m.sender_id = e.id AND m.sender_role = 'expert'
        WHERE 
            ((m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?) OR
            (m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?))
        ORDER BY m.sent_at ASC
    ");
    
    // Fixed the parameter binding - we need to match the correct roles
    if ($user_role === 'farmer') {
        // Farmer is viewing chat with expert
        $sender_id_1 = $user_id;
        $sender_role_1 = $user_role;
        $receiver_id_1 = $other_id;
        $receiver_role_1 = 'expert';
        $sender_id_2 = $other_id;
        $sender_role_2 = 'expert';
        $receiver_id_2 = $user_id;
        $receiver_role_2 = $user_role;
        
        $stmt->bind_param("isisisis", 
            $sender_id_1, $sender_role_1, $receiver_id_1, $receiver_role_1,
            $sender_id_2, $sender_role_2, $receiver_id_2, $receiver_role_2);
    } else {
        // Expert is viewing chat with farmer
        $sender_id_1 = $user_id;
        $sender_role_1 = $user_role;
        $receiver_id_1 = $other_id;
        $receiver_role_1 = 'farmer';
        $sender_id_2 = $other_id;
        $sender_role_2 = 'farmer';
        $receiver_id_2 = $user_id;
        $receiver_role_2 = $user_role;
        
        $stmt->bind_param("isisisis", 
            $sender_id_1, $sender_role_1, $receiver_id_1, $receiver_role_1,
            $sender_id_2, $sender_role_2, $receiver_id_2, $receiver_role_2);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($msg = $result->fetch_assoc()) {
        // Add delivery status information
        $msg['delivery_status'] = 'sent'; // Default status
        
        // Check if message was delivered (sent_at exists)
        if (!empty($msg['sent_at'])) {
            $msg['delivery_status'] = 'delivered';
        }
        
        // Check if message was read (read_at exists)
        if (!empty($msg['read_at'])) {
            $msg['delivery_status'] = 'read';
        }
        
        $messages[] = $msg;
    }
    
    // Mark messages as read
    if (count($messages) > 0) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET read_at = NOW() 
            WHERE receiver_id = ? 
            AND receiver_role = ? 
            AND read_at IS NULL
        ");
        $stmt->bind_param("is", $user_id, $user_role);
        $stmt->execute();
    }

    return [
        'success' => true,
        'messages' => $messages
    ];
}

function getChatMessagesPaginated($user_id, $user_role, $other_id, $last_message_id = 0, $limit = 50, $before_message_id = 0) {
    global $conn;
    
    // Validate the chat participants
    if ($user_role === 'farmer') {
        if (!isValidExpertFarmerPair($other_id, $user_id)) {
            return [
                'success' => false,
                'error' => 'Not authorized to view this chat'
            ];
        }
    } else if ($user_role === 'expert') {
        // Check if this is a valid expert-farmer pair (either currently or previously assigned)
        if (!isValidExpertFarmerPair($user_id, $other_id)) {
            // Check if they were previously assigned
            $stmt = $conn->prepare("SELECT 1 FROM expert_farmer_assignments WHERE expert_id = ? AND farmer_id = ?");
            $stmt->bind_param("ii", $user_id, $other_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                return [
                    'success' => false,
                    'error' => 'Not authorized to view this chat'
                ];
            }
        }
    }
    
    // Get messages - Fixed the query to properly handle both directions
    $sql = "
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_role = 'farmer' THEN f.name 
                WHEN m.sender_role = 'expert' THEN e.name 
                ELSE 'Unknown'
            END as sender_name
        FROM messages m
        LEFT JOIN farmers f ON m.sender_id = f.id AND m.sender_role = 'farmer'
        LEFT JOIN experts e ON m.sender_id = e.id AND m.sender_role = 'expert'
        WHERE 
            ((m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?) OR
            (m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?))";
    
    $params = [];
    $types = "";
    
    // Add conditions based on pagination type
    if ($last_message_id > 0) {
        // Get newer messages (for real-time updates)
        $sql .= " AND m.id > ?";
        $params[] = $last_message_id;
        $types .= "i";
    } else if ($before_message_id > 0) {
        // Get older messages (for loading history)
        $sql .= " AND m.id < ?";
        $params[] = $before_message_id;
        $types .= "i";
    }
    
    // Order by sent_at depending on pagination type
    if ($before_message_id > 0) {
        $sql .= " ORDER BY m.sent_at DESC LIMIT ?";
    } else {
        $sql .= " ORDER BY m.sent_at ASC LIMIT ?";
    }
    
    // Prepare parameters
    if ($user_role === 'farmer') {
        // Farmer is viewing chat with expert
        $params = array_merge([
            $user_id, $user_role, $other_id, 'expert',
            $other_id, 'expert', $user_id, $user_role
        ], $params);
        $types = "isisisis" . $types . "i";
    } else {
        // Expert is viewing chat with farmer
        $params = array_merge([
            $user_id, $user_role, $other_id, 'farmer',
            $other_id, 'farmer', $user_id, $user_role
        ], $params);
        $types = "isisisis" . $types . "i";
    }
    
    $params[] = $limit;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($msg = $result->fetch_assoc()) {
        // Add delivery status information
        $msg['delivery_status'] = 'sent'; // Default status
        
        // Check if message was delivered (sent_at exists)
        if (!empty($msg['sent_at'])) {
            $msg['delivery_status'] = 'delivered';
        }
        
        // Check if message was read (read_at exists)
        if (!empty($msg['read_at'])) {
            $msg['delivery_status'] = 'read';
        }
        
        $messages[] = $msg;
    }
    
    // Reverse messages if loading history (to show newest first)
    if ($before_message_id > 0) {
        $messages = array_reverse($messages);
    }
    
    // Mark messages as read (only for newer messages)
    if ($last_message_id > 0 && count($messages) > 0) {
        $stmt = $conn->prepare("
            UPDATE messages 
            SET read_at = NOW() 
            WHERE receiver_id = ? 
            AND receiver_role = ? 
            AND read_at IS NULL
        ");
        $stmt->bind_param("is", $user_id, $user_role);
        $stmt->execute();
    }

    return [
        'success' => true,
        'messages' => $messages,
        'has_more' => count($messages) >= $limit
    ];
}

// Update user activity (last seen and online status)
function updateUserActivity($user_id, $user_role, $is_online = true) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO user_activity (user_id, user_role, is_online)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        last_activity = NOW(), 
        is_online = ?
    ");
    $stmt->bind_param("isis", $user_id, $user_role, $is_online, $is_online);
    $stmt->execute();
}

// Mark specific messages as read
function markMessagesAsRead($user_id, $user_role, $sender_id, $sender_role) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE messages 
        SET read_at = NOW() 
        WHERE receiver_id = ? 
        AND receiver_role = ? 
        AND sender_id = ? 
        AND sender_role = ? 
        AND read_at IS NULL
    ");
    $stmt->bind_param("isis", $user_id, $user_role, $sender_id, $sender_role);
    return $stmt->execute();
}

// Mark a specific message as read
function markMessageAsRead($message_id, $user_id, $user_role) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE messages 
        SET read_at = NOW() 
        WHERE id = ? 
        AND receiver_id = ? 
        AND receiver_role = ? 
        AND read_at IS NULL
    ");
    $stmt->bind_param("iis", $message_id, $user_id, $user_role);
    return $stmt->execute();
}

// Delete a message (only if user is the sender)
function deleteMessage($message_id, $user_id, $user_role) {
    global $conn;
    
    // First check if the message exists and belongs to the user
    $stmt = $conn->prepare("
        SELECT id, attachment 
        FROM messages 
        WHERE id = ? AND sender_id = ? AND sender_role = ?
    ");
    $stmt->bind_param("iis", $message_id, $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'success' => false,
            'error' => 'Message not found or you do not have permission to delete it'
        ];
    }
    
    $message = $result->fetch_assoc();
    
    // Delete the message
    $stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->bind_param("i", $message_id);
    
    if ($stmt->execute()) {
        // If there was an attachment, delete the file
        if (!empty($message['attachment']) && file_exists('../' . $message['attachment'])) {
            unlink('../' . $message['attachment']);
        }
        
        return [
            'success' => true,
            'message' => 'Message deleted successfully'
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Failed to delete message'
    ];
}

// Edit a message (only if user is the sender)
function editMessage($message_id, $user_id, $user_role, $new_message) {
    global $conn;
    
    // Validate input
    $new_message = trim($new_message);
    if (empty($new_message)) {
        return [
            'success' => false,
            'error' => 'Message cannot be empty'
        ];
    }
    
    if (strlen($new_message) > 1000) {
        return [
            'success' => false,
            'error' => 'Message too long. Maximum 1000 characters allowed.'
        ];
    }
    
    // XSS prevention
    $new_message = htmlspecialchars($new_message, ENT_QUOTES, 'UTF-8');
    
    // First check if the message exists and belongs to the user
    $stmt = $conn->prepare("
        SELECT message 
        FROM messages 
        WHERE id = ? AND sender_id = ? AND sender_role = ?
    ");
    $stmt->bind_param("iis", $message_id, $user_id, $user_role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return [
            'success' => false,
            'error' => 'Message not found or you do not have permission to edit it'
        ];
    }
    
    $old_message = $result->fetch_assoc()['message'];
    
    // Insert into edit history
    $stmt = $conn->prepare("
        INSERT INTO message_edit_history (message_id, old_message, new_message, edited_by, edited_by_role)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issss", $message_id, $old_message, $new_message, $user_id, $user_role);
    $stmt->execute();
    
    // Update the message
    $stmt = $conn->prepare("UPDATE messages SET message = ? WHERE id = ? AND sender_id = ? AND sender_role = ?");
    $stmt->bind_param("siis", $new_message, $message_id, $user_id, $user_role);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return [
            'success' => true,
            'message' => 'Message updated successfully'
        ];
    }
    
    return [
        'success' => false,
        'error' => 'Failed to update message'
    ];
}

// Set typing status
function setTypingStatus($user_id, $user_role, $other_id, $is_typing = true) {
    global $conn;
    
    // Clean up old typing statuses (older than 10 seconds)
    $stmt = $conn->prepare("DELETE FROM typing_status WHERE last_updated < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
    $stmt->execute();
    
    // Insert or update typing status
    $stmt = $conn->prepare("
        INSERT INTO typing_status (user_id, user_role, other_id, is_typing)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE is_typing = ?, last_updated = NOW()
    ");
    $stmt->bind_param("iisii", $user_id, $user_role, $other_id, $is_typing, $is_typing);
    
    return $stmt->execute();
}

// Get typing status
function getTypingStatus($user_id, $user_role, $other_id) {
    global $conn;
    
    // Clean up old typing statuses (older than 10 seconds)
    $stmt = $conn->prepare("DELETE FROM typing_status WHERE last_updated < DATE_SUB(NOW(), INTERVAL 10 SECOND)");
    $stmt->execute();
    
    // Get typing status
    $stmt = $conn->prepare("
        SELECT is_typing 
        FROM typing_status 
        WHERE user_id = ? AND user_role = ? AND other_id = ?
    ");
    $stmt->bind_param("iis", $other_id, $user_role === 'farmer' ? 'expert' : 'farmer', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Check if the status is recent (within 5 seconds)
        $stmt = $conn->prepare("
            SELECT last_updated 
            FROM typing_status 
            WHERE user_id = ? AND user_role = ? AND other_id = ?
        ");
        $stmt->bind_param("iis", $other_id, $user_role === 'farmer' ? 'expert' : 'farmer', $user_id);
        $stmt->execute();
        $time_result = $stmt->get_result();
        $time_row = $time_result->fetch_assoc();
        
        if ($time_row) {
            $last_updated = strtotime($time_row['last_updated']);
            if ((time() - $last_updated) < 5) { // 5 seconds
                return (bool)$row['is_typing'];
            }
        }
    }
    
    return false;
}

// Search messages between two users
function searchMessages($user_id, $user_role, $other_id, $search_term, $limit = 50) {
    global $conn;
    
    // Validate the chat participants
    if ($user_role === 'farmer') {
        if (!isValidExpertFarmerPair($other_id, $user_id)) {
            return [
                'success' => false,
                'error' => 'Not authorized to view this chat'
            ];
        }
    } else if ($user_role === 'expert') {
        // Check if this is a valid expert-farmer pair (either currently or previously assigned)
        if (!isValidExpertFarmerPair($user_id, $other_id)) {
            // Check if they were previously assigned
            $stmt = $conn->prepare("SELECT 1 FROM expert_farmer_assignments WHERE expert_id = ? AND farmer_id = ?");
            $stmt->bind_param("ii", $user_id, $other_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                return [
                    'success' => false,
                    'error' => 'Not authorized to view this chat'
                ];
            }
        }
    }
    
    // Search messages
    $stmt = $conn->prepare("
        SELECT 
            m.*,
            CASE 
                WHEN m.sender_role = 'farmer' THEN f.name 
                WHEN m.sender_role = 'expert' THEN e.name 
                ELSE 'Unknown'
            END as sender_name
        FROM messages m
        LEFT JOIN farmers f ON m.sender_id = f.id AND m.sender_role = 'farmer'
        LEFT JOIN experts e ON m.sender_id = e.id AND m.sender_role = 'expert'
        WHERE 
            ((m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?) OR
            (m.sender_id = ? AND m.sender_role = ? AND m.receiver_id = ? AND m.receiver_role = ?))
            AND (m.message LIKE ? OR m.message LIKE ?)
        ORDER BY m.sent_at DESC
        LIMIT ?
    ");
    
    $search_term1 = '%' . $search_term . '%';
    $search_term2 = '%' . strtolower($search_term) . '%';
    
    if ($user_role === 'farmer') {
        // Farmer is viewing chat with expert
        $stmt->bind_param("isisisisssi", 
            $user_id, $user_role, $other_id, 'expert',
            $other_id, 'expert', $user_id, $user_role,
            $search_term1, $search_term2, $limit);
    } else {
        // Expert is viewing chat with farmer
        $stmt->bind_param("isisisisssi", 
            $user_id, $user_role, $other_id, 'farmer',
            $other_id, 'farmer', $user_id, $user_role,
            $search_term1, $search_term2, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($msg = $result->fetch_assoc()) {
        $messages[] = $msg;
    }
    
    return [
        'success' => true,
        'messages' => $messages
    ];
}

// Add function to get user name by ID and role
function getUserNameById($user_id, $user_role) {
    global $conn;
    
    if ($user_role === 'farmer') {
        $stmt = $conn->prepare("SELECT name FROM farmers WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    } else if ($user_role === 'expert') {
        $stmt = $conn->prepare("SELECT name FROM experts WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        return 'Unknown';
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['name'];
    }
    
    return 'Unknown';
}

// Get the most recently messaged farmer for an expert
function getMostRecentMessagedFarmer($expert_id) {
    global $conn;
    
    // Get the most recent message sent or received by the expert
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN m.sender_id = ? AND m.sender_role = 'expert' THEN m.receiver_id
                WHEN m.receiver_id = ? AND m.receiver_role = 'expert' THEN m.sender_id
            END as farmer_id
        FROM messages m
        WHERE (m.sender_id = ? AND m.sender_role = 'expert') 
           OR (m.receiver_id = ? AND m.receiver_role = 'expert')
        ORDER BY m.sent_at DESC
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $expert_id, $expert_id, $expert_id, $expert_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['farmer_id'];
    }
    
    return null;
}

// Get unread message count for a conversation
function getUnreadMessageCount($user_id, $user_role, $other_id, $other_role) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM messages
        WHERE sender_id = ? 
        AND sender_role = ? 
        AND receiver_id = ? 
        AND receiver_role = ? 
        AND read_at IS NULL
    ");
    
    if ($user_role === 'expert') {
        $stmt->bind_param("isis", $other_id, $other_role, $user_id, $user_role);
    } else {
        $stmt->bind_param("isis", $other_id, $other_role, $user_id, $user_role);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['unread_count'];
}
