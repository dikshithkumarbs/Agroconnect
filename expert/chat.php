<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';
redirectIfNotRole('expert');

$user_id = $_SESSION['user_id'];
$farmer_id = isset($_GET['farmer_id']) ? intval($_GET['farmer_id']) : 0;

// Get assigned farmers for selection with phone numbers
// Debug: Log the user ID
error_log("Fetching farmers for expert ID: " . $user_id);

$stmt = $conn->prepare("
    SELECT f.id, f.name, f.location, f.phone, efa.assigned_at, efa.notes
    FROM farmers f
    JOIN expert_farmer_assignments efa ON f.id = efa.farmer_id
    WHERE efa.expert_id = ? AND efa.status = 'active'
    ORDER BY f.name
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmers = $stmt->get_result();

// Debug: Log the number of farmers found
error_log("Number of farmers found: " . $farmers->num_rows);

// Store the last accessed farmer in session for better user experience
if ($farmer_id > 0) {
    $_SESSION['last_farmer_id'] = $farmer_id;
} else if (isset($_SESSION['last_farmer_id']) && $farmer_id == 0) {
    // If no farmer is selected but we have a last accessed farmer, use it
    $farmer_id = intval($_SESSION['last_farmer_id']);
    // Only redirect if we're not already redirecting (prevent infinite loop)
    // This will only happen when accessing the page without a farmer_id parameter
    if (!isset($_GET['redirected'])) {
        header('Location: chat.php?farmer_id=' . $farmer_id . '&redirected=1');
        exit();
    }
} else if ($farmer_id == 0 && $farmers && $farmers->num_rows > 0) {
    // If no farmer is selected and no last accessed farmer,
    // automatically select the most recently messaged farmer or the first one
    $recent_farmer_id = getMostRecentMessagedFarmer($user_id);
    if ($recent_farmer_id) {
        $farmer_id = $recent_farmer_id;
        $_SESSION['last_farmer_id'] = $farmer_id;
        // Redirect to prevent form resubmission on refresh
        if (!isset($_GET['redirected'])) {
            header('Location: chat.php?farmer_id=' . $farmer_id . '&redirected=1');
            exit();
        }
    } else {
        // Select the first farmer in the list
        $first_farmer = $farmers->fetch_assoc();
        $farmer_id = $first_farmer['id'];
        $_SESSION['last_farmer_id'] = $farmer_id;
        // Redirect to prevent form resubmission on refresh
        if (!isset($_GET['redirected'])) {
            header('Location: chat.php?farmer_id=' . $farmer_id . '&redirected=1');
            exit();
        }
        // Reset the result set for later use
        $stmt->execute();
        $farmers = $stmt->get_result();
    }
}

$message = '';

// Handle success message after redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'success') {
    $message = '<div class="success">Message sent successfully!</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_text = sanitize($_POST['message'] ?? '');

    // Handle file upload
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $attachment = $_FILES['attachment'];
    }

    if ((!empty($message_text) || $attachment) && $farmer_id) {
        // Validate that this farmer is assigned to this expert for sending messages
        if (!isValidExpertFarmerPair($user_id, $farmer_id)) {
            $message = '<div class="error">You can only send messages to farmers currently assigned to you.</div>';
        } else {
            $result = sendChatMessage($user_id, 'expert', $farmer_id, 'farmer', $message_text, $attachment);
            if ($result['success']) {
                // Redirect to prevent form resubmission on refresh
                header('Location: chat.php?farmer_id=' . $farmer_id . '&msg=success');
                exit();
            } else {
                $message = '<div class="error">' . htmlspecialchars($result['error']) . '</div>';
            }
        }
    }
}

// Get chat messages if farmer selected
$chat_messages = [];
$selected_farmer = null;
if ($farmer_id) {
    // First verify this is an assigned farmer
    // Check if the farmer is assigned to this expert
    if (!isValidExpertFarmerPair($user_id, $farmer_id)) {
        // If not assigned, check if the expert has access to this farmer
        $stmt = $conn->prepare("SELECT 1 FROM expert_farmer_assignments WHERE expert_id = ? AND farmer_id = ?");
        $stmt->bind_param("ii", $user_id, $farmer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $message = '<div class="error">You are not authorized to view this chat. This farmer is not assigned to you.</div>';
        } else {
            // Farmer was previously assigned, show a different message
            $message = '<div class="warning">This farmer is not currently assigned to you but you can still view the chat history.</div>';
        }
    }
    
    // Always allow viewing chat with assigned farmers or previously assigned farmers
    if (empty($message) || isValidExpertFarmerPair($user_id, $farmer_id)) {
        // Get farmer details with phone number
        // Debug: Log the farmer ID being requested
        error_log("Fetching details for farmer ID: " . $farmer_id . " for expert ID: " . $user_id);
            
        $stmt = $conn->prepare("
            SELECT f.*, efa.assigned_at, efa.notes, efa.status as assignment_status
            FROM farmers f
            JOIN expert_farmer_assignments efa ON f.id = efa.farmer_id
            WHERE f.id = ? AND efa.expert_id = ?
        ");
        $stmt->bind_param("ii", $farmer_id, $user_id);
        $stmt->execute();
        $selected_farmer = $stmt->get_result()->fetch_assoc();
            
        // Debug: Log if farmer was found
        if ($selected_farmer) {
            error_log("Farmer found: " . $selected_farmer['name']);
        } else {
            error_log("Farmer not found for ID: " . $farmer_id);
        }

        // Get chat messages
        $result = getChatMessages($user_id, 'expert', $farmer_id);
        if ($result['success']) {
            $chat_messages = $result['messages'];
        } else {
            $message = '<div class="error">' . htmlspecialchars($result['error']) . '</div>';
        }
    }
}

include '../includes/header.php';
?>

<div class="chat-app">
    <!-- Sidebar with farmer list -->
    <div class="chat-sidebar">
        <div class="chat-header">
            <h2>Assigned Farmers</h2>
            <!-- Add search input for farmers -->
            <div class="search-container">
                <input type="text" id="farmer-search" placeholder="Search farmers..." class="search-input">
                <button id="clear-farmer-search" class="btn-icon" title="Clear Search" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="farmer-list">
            <?php if ($farmers->num_rows > 0): ?>
                <?php while ($farmer = $farmers->fetch_assoc()): 
                    // Get unread message count for this farmer
                    $unread_count = getUnreadMessageCount($user_id, 'expert', $farmer['id'], 'farmer');
                ?>
                    <div class="farmer-item <?php echo $farmer_id == $farmer['id'] ? 'active' : ''; ?>" 
                         data-farmer-id="<?php echo $farmer['id']; ?>">
                        <div class="farmer-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="farmer-info">
                            <div class="farmer-name"><?php echo htmlspecialchars($farmer['name']); ?></div>
                            <div class="farmer-location"><?php echo htmlspecialchars($farmer['location']); ?></div>
                            <?php if (!empty($farmer['phone'])): ?>
                                <div class="phone-number">📞 <?php echo htmlspecialchars($farmer['phone']); ?></div>
                            <?php endif; ?>
                            <?php if ($unread_count > 0): ?>
                                <div class="unread-badge"><?php echo $unread_count; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-farmers">
                    <p>You don't have any farmers assigned yet.</p>
                </div>
            <?php endif; ?>
        </div>
        <script>
            // Add click event listeners to all farmer items
            document.addEventListener('DOMContentLoaded', function() {
                const farmerItems = document.querySelectorAll('.farmer-item');
                console.log('Number of farmers found:', farmerItems.length);
                
                if (farmerItems.length === 0) {
                    console.log('No farmers found in the list');
                    return;
                }
                
                farmerItems.forEach(function(item) {
                    const farmerId = item.getAttribute('data-farmer-id');
                    console.log('Farmer item with ID:', farmerId);
                    
                    // Add click event listener
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Clicked farmer item with ID:', farmerId);
                        selectFarmer(farmerId);
                        
                        // Add active class to selected item
                        farmerItems.forEach(i => i.classList.remove('active'));
                        item.classList.add('active');
                    });
                });
            });
        </script>
    </div>

    <!-- Main chat area -->
    <div class="chat-main">
        <?php if ($farmer_id && $selected_farmer): ?>
            <div class="chat-container">
                <!-- Chat header -->
                <div class="chat-header">
                    <div class="chat-partner">
                        <div class="partner-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="partner-info">
                            <div class="partner-name"><?php echo htmlspecialchars($selected_farmer['name']); ?></div>
                            <div class="partner-status">
                                Location: <?php echo htmlspecialchars($selected_farmer['location']); ?>
                                <?php if (!empty($selected_farmer['phone'])): ?>
                                    <span class="phone-number">📞 <?php echo htmlspecialchars($selected_farmer['phone']); ?></span>
                                <?php endif; ?>
                                <?php if (isset($selected_farmer['assignment_status']) && $selected_farmer['assignment_status'] !== 'active'): ?>
                                    <span class="assignment-status">(Previously Assigned)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <div class="search-container">
                            <input type="text" id="search-messages" placeholder="Search messages..." class="search-input">
                            <button id="search-btn" class="btn-icon" title="Search">
                                <i class="fas fa-search"></i>
                            </button>
                            <button id="clear-search" class="btn-icon" title="Clear Search" style="display: none;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <button id="refresh-chat" class="btn-icon" title="Refresh Chat">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Message container -->
                <div class="message-container" id="chat-messages" data-other-id="<?php echo $farmer_id; ?>">
                    <div class="load-more-container" id="load-more-container" style="display: none;">
                        <button id="load-more-btn" class="btn-load-more">Load More Messages</button>
                    </div>
                    <?php if (empty($chat_messages)): ?>
                        <div class="no-messages">
                            <i class="fas fa-comments"></i>
                            <p>No messages yet. Start a conversation with the farmer!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chat_messages as $msg): ?>
                            <div class="message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['id']; ?>">
                                <div class="message-content">
                                    <?php if (!empty($msg['message'])): ?>
                                        <div class="message-text"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($msg['attachment'])): ?>
                                        <div class="attachment">
                                            <?php
                                            $file_name = basename($msg['attachment']);
                                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <div class="image-attachment">
                                                    <img src="<?php echo $msg['attachment']; ?>" alt="Attachment" class="attachment-image" onclick="openImageModal('<?php echo $msg['attachment']; ?>')">
                                                </div>
                                            <?php elseif (in_array($file_ext, ['mp4', 'mov', 'avi', 'wmv'])): ?>
                                                <div class="video-attachment">
                                                    <video controls class="attachment-video">
                                                        <source src="<?php echo $msg['attachment']; ?>" type="video/<?php echo $file_ext; ?>">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                    <div class="file-info">
                                                        <i class="fas fa-file-video"></i>
                                                        <span><?php echo $file_name; ?></span>
                                                    </div>
                                                </div>
                                            <?php elseif ($file_ext === 'pdf'): ?>
                                                <div class="file-attachment">
                                                    <a href="<?php echo $msg['attachment']; ?>" target="_blank">
                                                        <i class="fas fa-file-pdf"></i>
                                                        <span><?php echo $file_name; ?></span>
                                                    </a>
                                                </div>
                                            <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                                <div class="file-attachment">
                                                    <a href="<?php echo $msg['attachment']; ?>" target="_blank">
                                                        <i class="fas fa-file-word"></i>
                                                        <span><?php echo $file_name; ?></span>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="file-attachment">
                                                    <a href="<?php echo $msg['attachment']; ?>" target="_blank">
                                                        <i class="fas fa-file"></i>
                                                        <span><?php echo $file_name; ?></span>
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="message-meta">
                                    <span class="message-time"><?php echo date('M d, H:i', strtotime($msg['sent_at'])); ?></span>
                                    <?php if ($msg['sender_id'] == $user_id): ?>
                                        <div class="message-status">
                                            <?php if (isset($msg['delivery_status']) && $msg['delivery_status'] === 'read'): ?>
                                                <span class="status-read" title="Read">&#10003;&#10003;&#10003;</span>
                                            <?php elseif (isset($msg['delivery_status']) && $msg['delivery_status'] === 'delivered'): ?>
                                                <span class="status-delivered" title="Delivered">&#10003;&#10003;</span>
                                            <?php else: ?>
                                                <span class="status-sent" title="Sent">&#10003;</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="message-actions">
                                            <button onclick="editMessage(<?php echo $msg['id']; ?>, this)" class="btn-edit" title="Edit message">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteMessage(<?php echo $msg['id']; ?>, this)" class="btn-delete" title="Delete message">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="message-actions">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Typing indicator -->
                <div id="typing-indicator" class="typing-indicator" style="display: none;">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <span id="typing-text" class="typing-text">is typing...</span>
                </div>

                <!-- Message input -->
                <form method="POST" enctype="multipart/form-data" id="chat-form">
                    <div class="message-input-area">
                        <div class="input-container">
                            <input type="text" name="message" id="message-input" placeholder="Type a message..." autocomplete="off">
                            <div class="input-actions">
                                <label for="attachment" class="file-upload-label" title="Attach file">
                                    <i class="fas fa-paperclip"></i>
                                </label>
                                <input type="file" id="attachment" name="attachment" style="display: none;" accept="image/*,video/*,.pdf,.doc,.docx,.txt">
                                <button type="submit" class="send-button" id="send-button">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                        <div class="file-info" id="file-info" style="display: none;">
                            <span id="file-name" class="file-name-display"></span>
                            <button type="button" id="clear-file" class="clear-file-btn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="chat-welcome">
                <div class="welcome-content">
                    <i class="fas fa-comments fa-3x"></i>
                    <h3>Welcome to Agro Connect Chat</h3>
                    <?php if ($farmers && $farmers->num_rows > 0): ?>
                        <p>Select a farmer from the list to start chatting</p>
                    <?php else: ?>
                        <p>You don't have any farmers assigned yet. Please contact your administrator.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Global variables
let isRefreshing = false;
let lastMessageId = 0;

// Function to select a farmer
function selectFarmer(farmerId) {
    console.log('Selecting farmer with ID:', farmerId);
    // Validate farmerId
    if (!farmerId || farmerId <= 0) {
        console.error('Invalid farmer ID:', farmerId);
        alert('Invalid farmer selection. Please try again.');
        return;
    }
    
    // Convert to integer to ensure it's a valid number
    const id = parseInt(farmerId, 10);
    if (isNaN(id) || id <= 0) {
        console.error('Invalid farmer ID format:', farmerId);
        alert('Invalid farmer selection. Please try again.');
        return;
    }
    
    // Prevent default to ensure no other events interfere
    try {
        // Check if we're already on the same farmer's chat
        const urlParams = new URLSearchParams(window.location.search);
        const currentFarmerId = urlParams.get('farmer_id');
        
        if (currentFarmerId && parseInt(currentFarmerId) === id) {
            console.log('Already on the same farmer chat');
            return;
        }
        
        window.location.href = 'chat.php?farmer_id=' + id;
    } catch (error) {
        console.error('Error navigating to farmer chat:', error);
        alert('Error opening chat. Please try again.');
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Auto scroll to bottom of messages
    const messageContainer = document.getElementById('chat-messages');
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
    
    // File input handling
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('file-name');
    const clearFileBtn = document.getElementById('clear-file');
    const fileInfo = document.getElementById('file-info');
    
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileNameDisplay.textContent = file.name;
                clearFileBtn.style.display = 'flex';
                fileInfo.style.display = 'flex';
                
                // Add image preview if it's an image file
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Create or update preview element
                        let preview = document.getElementById('image-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.id = 'image-preview';
                            preview.className = 'image-preview';
                            fileInfo.parentNode.insertBefore(preview, fileInfo.nextSibling);
                        }
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width: 100px; max-height: 100px; margin-top: 5px; border-radius: 5px;">';
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Remove preview if it exists
                    const preview = document.getElementById('image-preview');
                    if (preview) {
                        preview.remove();
                    }
                }
            } else {
                fileNameDisplay.textContent = '';
                clearFileBtn.style.display = 'none';
                fileInfo.style.display = 'none';
                
                // Remove preview if it exists
                const preview = document.getElementById('image-preview');
                if (preview) {
                    preview.remove();
                }
            }
        });
    }
    
    if (clearFileBtn) {
        clearFileBtn.addEventListener('click', function() {
            if (fileInput) {
                fileInput.value = '';
                fileNameDisplay.textContent = '';
                clearFileBtn.style.display = 'none';
                fileInfo.style.display = 'none';
                
                // Remove preview if it exists
                const preview = document.getElementById('image-preview');
                if (preview) {
                    preview.remove();
                }
            }
        });
    }
    
    // Refresh chat button
    const refreshBtn = document.getElementById('refresh-chat');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            loadChatMessages(true);
        });
    }
    
    // Add typing indicator element
    const typingIndicator = document.createElement('div');
    typingIndicator.id = 'typing-indicator';
    typingIndicator.className = 'typing-indicator';
    typingIndicator.style.display = 'none';
    typingIndicator.innerHTML = '<span id="typing-text">Farmer is typing...</span>';
    if (messageContainer) {
        messageContainer.parentNode.insertBefore(typingIndicator, messageContainer.nextSibling);
    }
    
    // Set up typing indicator for message input
    const messageInput = document.getElementById('message-input');
    let typingTimer;
    let isTyping = false;
    
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            // Clear previous typing timer
            clearTimeout(typingTimer);
            
            // Set typing status to true
            if (!isTyping) {
                isTyping = true;
                setTypingStatus(true);
            }
            
            // Set timer to stop typing indicator after user stops typing
            typingTimer = setTimeout(function() {
                isTyping = false;
                setTypingStatus(false);
            }, 1000); // Stop typing indicator after 1 second of inactivity
        });
    }
    
    // Set up "Load More" functionality
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (loadMoreBtn && loadMoreContainer) {
        loadMoreBtn.addEventListener('click', function() {
            loadMoreMessages();
        });
        
        // Show load more button if there are messages
        if (messageContainer && messageContainer.querySelectorAll('.message').length > 0) {
            loadMoreContainer.style.display = 'block';
        }
    }
    
    // Set up search functionality
    const searchInput = document.getElementById('search-messages');
    const searchBtn = document.getElementById('search-btn');
    const clearSearchBtn = document.getElementById('clear-search');
    
    if (searchInput && searchBtn) {
        // Search on button click
        searchBtn.addEventListener('click', function() {
            searchMessages();
        });
        
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchMessages();
            }
        });
        
        // Clear search
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearSearchBtn.style.display = 'none';
                // Refresh chat to show all messages
                loadChatMessages(true);
            });
        }
    }
    
    // Set up farmer search functionality
    const farmerSearchInput = document.getElementById('farmer-search');
    const clearFarmerSearchBtn = document.getElementById('clear-farmer-search');
    
    if (farmerSearchInput) {
        // Search as user types
        farmerSearchInput.addEventListener('input', function() {
            searchFarmers();
        });
        
        // Clear farmer search
        if (clearFarmerSearchBtn) {
            clearFarmerSearchBtn.addEventListener('click', function() {
                farmerSearchInput.value = '';
                clearFarmerSearchBtn.style.display = 'none';
                searchFarmers(); // Show all farmers
            });
        }
    }
});

// Image modal functions
function openImageModal(src) {
    const modal = document.getElementById('image-modal');
    const modalImg = document.getElementById('modal-image');
    modal.style.display = 'block';
    modalImg.src = src;
}

function closeImageModal() {
    document.getElementById('image-modal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('image-modal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Periodically refresh message statuses
setInterval(function() {
    // Only refresh if we're on a chat page and not already refreshing
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer && !isRefreshing) {
        // Check if there are any sent messages that might have updated status
        const sentMessages = document.querySelectorAll('.message.sent');
        if (sentMessages.length > 0) {
            // Only do a silent refresh (without loading indicator)
            loadChatMessages(false);
        }
    }
}, 30000); // Refresh every 30 seconds

// Load chat messages function
function loadChatMessages(showLoading = false) {
    if (isRefreshing) return;
    
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        // Show loading indicator
        if (showLoading) {
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'chat-loading';
            loadingDiv.innerHTML = '<div class="loading-indicator"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>';
            chatContainer.appendChild(loadingDiv);
        }
        
        isRefreshing = true;
        
        // Get other_id from URL parameter or from a data attribute
        const urlParams = new URLSearchParams(window.location.search);
        let otherId = urlParams.get('farmer_id'); // For expert chat

        if (!otherId) {
            // For farmer chat, get other_id from a data attribute
            otherId = chatContainer.getAttribute('data-other-id');
        }

        if (otherId) {
            // For real-time updates, include the last message ID
            let fetchUrl = '../endpoints/get_messages.php?other_id=' + otherId;
            if (lastMessageId > 0) {
                fetchUrl += '&last_message_id=' + lastMessageId;
            }
            
            fetch(fetchUrl)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (lastMessageId > 0 && data.messages.length > 0) {
                        // Append new messages to existing ones
                        let html = '';
                        data.messages.forEach(function(msg) {
                            const isSent = msg.sender_id == window.userId;
                            const messageClass = isSent ? 'sent' : 'received';
                            html += '<div class="message ' + messageClass + '" data-message-id="' + msg.id + '">';
                            html += '<div class="message-content">';
                            if (msg.message) {
                                html += '<div class="message-text">' + formatMessage(escapeHtml(msg.message)) + '</div>';
                            }
                            
                            // Handle attachments
                            if (msg.attachment) {
                                const fileName = msg.attachment.split('/').pop();
                                const fileExt = fileName.split('.').pop().toLowerCase();
                                
                                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                                    html += '<div class="image-attachment">';
                                    html += '<img src="' + msg.attachment + '" alt="Attachment" class="attachment-image" onclick="openImageModal(\'' + msg.attachment + '\')">';
                                    html += '</div>';
                                } else if (['mp4', 'mov', 'avi', 'wmv'].includes(fileExt)) {
                                    html += '<div class="video-attachment">';
                                    html += '<video controls class="attachment-video">';
                                    html += '<source src="' + msg.attachment + '" type="video/' + fileExt + '">';
                                    html += 'Your browser does not support the video tag.';
                                    html += '</video>';
                                    html += '<div class="file-info">';
                                    html += '<i class="fas fa-file-video"></i>';
                                    html += '<span>' + fileName + '</span>';
                                    html += '</div>';
                                    html += '</div>';
                                } else if (fileExt === 'pdf') {
                                    html += '<div class="file-attachment">';
                                    html += '<a href="' + msg.attachment + '" target="_blank">';
                                    html += '<i class="fas fa-file-pdf"></i>';
                                    html += '<span>' + fileName + '</span>';
                                    html += '</a>';
                                    html += '</div>';
                                } else if (['doc', 'docx'].includes(fileExt)) {
                                    html += '<div class="file-attachment">';
                                    html += '<a href="' + msg.attachment + '" target="_blank">';
                                    html += '<i class="fas fa-file-word"></i>';
                                    html += '<span>' + fileName + '</span>';
                                    html += '</a>';
                                    html += '</div>';
                                } else {
                                    html += '<div class="file-attachment">';
                                    html += '<a href="' + msg.attachment + '" target="_blank">';
                                    html += '<i class="fas fa-file"></i>';
                                    html += '<span>' + fileName + '</span>';
                                    html += '</a>';
                                    html += '</div>';
                                }
                            }
                            
                            html += '</div>';
                            html += '<div class="message-meta">';
                            html += '<span class="message-time">' + formatDate(msg.sent_at) + '</span>';
                            
                            // Add delivery status for sent messages
                            if (isSent) {
                                html += '<div class="message-status">';
                                if (msg.delivery_status && msg.delivery_status === 'read') {
                                    html += '<span class="status-read" title="Read">&#10003;&#10003;&#10003;</span>';
                                } else if (msg.delivery_status && msg.delivery_status === 'delivered') {
                                    html += '<span class="status-delivered" title="Delivered">&#10003;&#10003;</span>';
                                } else {
                                    html += '<span class="status-sent" title="Sent">&#10003;</span>';
                                }
                                html += '</div>';
                                
                                // Add edit/delete buttons for own messages
                                html += '<div class="message-actions">';
                                html += '<button class="btn-edit" onclick="editMessage(' + msg.id + ', this)" title="Edit message"><i class="fas fa-edit"></i></button>';
                                html += '<button class="btn-delete" onclick="deleteMessage(' + msg.id + ', this)" title="Delete message"><i class="fas fa-trash"></i></button>';
                                html += '</div>';
                            }
                            
                            html += '</div>';
                            
                            
                            html += '</div>';
                            
                            // Update last message ID
                            if (parseInt(msg.id) > lastMessageId) {
                                lastMessageId = parseInt(msg.id);
                            }
                        });
                        
                        // Append new messages to the container
                        chatContainer.insertAdjacentHTML('beforeend', html);
                        
                        // Auto scroll to bottom to show latest messages if user is at bottom
                        const isScrolledToBottom = chatContainer.scrollHeight - chatContainer.clientHeight <= chatContainer.scrollTop + 1;
                        if (isScrolledToBottom) {
                            chatContainer.scrollTop = chatContainer.scrollHeight;
                        }
                        
                        // Update typing indicator
                        updateTypingIndicator();
                    } else {
                        // Build HTML from all messages (initial load)
                        let html = '';
                        if (data.messages.length === 0) {
                            html = `
                                <div class="no-messages">
                                    <i class="fas fa-comments"></i>
                                    <p>No messages yet. Start a conversation!</p>
                                </div>
                            `;
                        } else {
                            data.messages.forEach(function(msg) {
                                const isSent = msg.sender_id == window.userId;
                                const messageClass = isSent ? 'sent' : 'received';
                                html += '<div class="message ' + messageClass + '" data-message-id="' + msg.id + '">';
                                html += '<div class="message-content">';
                                if (msg.message) {
                                    html += '<div class="message-text">' + formatMessage(escapeHtml(msg.message)) + '</div>';
                                }
                                
                                // Handle attachments
                                if (msg.attachment) {
                                    const fileName = msg.attachment.split('/').pop();
                                    const fileExt = fileName.split('.').pop().toLowerCase();
                                    
                                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                                        html += '<div class="image-attachment">';
                                        html += '<img src="' + msg.attachment + '" alt="Attachment" class="attachment-image" onclick="openImageModal(\'' + msg.attachment + '\')">';
                                        html += '</div>';
                                    } else if (['mp4', 'mov', 'avi', 'wmv'].includes(fileExt)) {
                                        html += '<div class="video-attachment">';
                                        html += '<video controls class="attachment-video">';
                                        html += '<source src="' + msg.attachment + '" type="video/' + fileExt + '">';
                                        html += 'Your browser does not support the video tag.';
                                        html += '</video>';
                                        html += '<div class="file-info">';
                                        html += '<i class="fas fa-file-video"></i>';
                                        html += '<span>' + fileName + '</span>';
                                        html += '</div>';
                                        html += '</div>';
                                    } else if (fileExt === 'pdf') {
                                        html += '<div class="file-attachment">';
                                        html += '<a href="' + msg.attachment + '" target="_blank">';
                                        html += '<i class="fas fa-file-pdf"></i>';
                                        html += '<span>' + fileName + '</span>';
                                        html += '</a>';
                                        html += '</div>';
                                    } else if (['doc', 'docx'].includes(fileExt)) {
                                        html += '<div class="file-attachment">';
                                        html += '<a href="' + msg.attachment + '" target="_blank">';
                                        html += '<i class="fas fa-file-word"></i>';
                                        html += '<span>' + fileName + '</span>';
                                        html += '</a>';
                                        html += '</div>';
                                    } else {
                                        html += '<div class="file-attachment">';
                                        html += '<a href="' + msg.attachment + '" target="_blank">';
                                        html += '<i class="fas fa-file"></i>';
                                        html += '<span>' + fileName + '</span>';
                                        html += '</a>';
                                        html += '</div>';
                                    }
                                }
                                
                                html += '</div>';
                                html += '<div class="message-meta">';
                                html += '<span class="message-time">' + formatDate(msg.sent_at) + '</span>';
                                
                                // Add delivery status for sent messages
                                if (isSent) {
                                    html += '<div class="message-status">';
                                    if (msg.delivery_status && msg.delivery_status === 'read') {
                                        html += '<span class="status-read" title="Read">&#10003;&#10003;&#10003;</span>';
                                    } else if (msg.delivery_status && msg.delivery_status === 'delivered') {
                                        html += '<span class="status-delivered" title="Delivered">&#10003;&#10003;</span>';
                                    } else {
                                        html += '<span class="status-sent" title="Sent">&#10003;</span>';
                                    }
                                    html += '</div>';
                                    
                                    // Add edit/delete buttons for own messages
                                    html += '<div class="message-actions">';
                                    html += '<button class="btn-edit" onclick="editMessage(' + msg.id + ', this)" title="Edit message"><i class="fas fa-edit"></i></button>';
                                    html += '<button class="btn-delete" onclick="deleteMessage(' + msg.id + ', this)" title="Delete message"><i class="fas fa-trash"></i></button>';
                                    html += '</div>';
                                } else {
                                    html += '<div class="message-actions">';
                                    html += '</div>';
                                    
                                    // Show new message notification for received messages
                                    if (!document.hidden) {
                                        showNewMessageNotification(msg);
                                    }
                                }
                                
                                html += '</div>';
                                
                                
                                html += '</div>';
                                
                                // Update last message ID
                                if (parseInt(msg.id) > lastMessageId) {
                                    lastMessageId = parseInt(msg.id);
                                }
                            });
                        }
                        chatContainer.innerHTML = html;

                        // Auto scroll to bottom to show latest messages
                        chatContainer.scrollTop = chatContainer.scrollHeight;

                        // Update typing indicator
                        updateTypingIndicator();
                    }
                } else {
                    console.error('Error loading messages:', data.error);
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
            })
            .finally(() => {
                isRefreshing = false;
                // Remove loading indicator
                const loadingDiv = document.getElementById('chat-loading');
                if (loadingDiv) {
                    loadingDiv.remove();
                }
            });
        } else {
            isRefreshing = false;
        }
    }
}

// Show new message notification
function showNewMessageNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'new-message-notification';
    notification.innerHTML = '<i class="fas fa-bell"></i> New message received';
    
    // Add to chat container
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        chatContainer.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
    
    // Mark the message as read
    if (message.id) {
        markMessageAsRead(message.id);
    }
}

// Mark message as read
function markMessageAsRead(messageId) {
    fetch('../endpoints/mark_message_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ message_id: messageId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Message marked as read');
        } else {
            console.error('Failed to mark message as read:', data.error);
        }
    })
    .catch(error => {
        console.error('Error marking message as read:', error);
    });
}

// Edit message functionality
function editMessage(messageId, buttonElement) {
    const messageDiv = buttonElement.closest('.message');
    const messageContent = messageDiv.querySelector('.message-content');
    
    if (messageContent) {
        const currentText = messageContent.textContent.trim();
        const textarea = document.createElement('textarea');
        textarea.value = currentText;
        textarea.className = 'edit-textarea';
        textarea.rows = 3;
        
        messageContent.innerHTML = '';
        messageContent.appendChild(textarea);
        textarea.focus();
        
        textarea.addEventListener('blur', function() {
            saveEdit(messageId, textarea.value, messageContent);
        });
        
        textarea.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && e.ctrlKey) {
                saveEdit(messageId, textarea.value, messageContent);
            } else if (e.key === 'Escape') {
                cancelEdit(messageContent, currentText);
            }
        });
    }
}

function saveEdit(messageId, newText, messageContent) {
    if (newText.trim() === '') {
        alert('Message cannot be empty.');
        return;
    }
    
    fetch('../endpoints/edit_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message_id=${messageId}&message=${encodeURIComponent(newText)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageContent.innerHTML = formatMessage(escapeHtml(newText));
        } else {
            alert('Failed to edit message: ' + data.error);
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to edit message.');
        location.reload();
    });
}

function cancelEdit(messageContent, originalText) {
    messageContent.innerHTML = formatMessage(escapeHtml(originalText));
}

function formatMessage(text) {
    // Convert URLs to links
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffDays = Math.floor(diffMs / 86400000);
    const diffHours = Math.floor((diffMs % 86400000) / 3600000);
    const diffMinutes = Math.floor(((diffMs % 86400000) % 3600000) / 60000);
    
    if (diffDays > 0) {
        return date.toLocaleDateString();
    } else if (diffHours > 0) {
        return diffHours + 'h ago';
    } else if (diffMinutes > 0) {
        return diffMinutes + 'm ago';
    } else {
        return 'Just now';
    }
}

// Delete message functionality
function deleteMessage(messageId, buttonElement) {
    if (!confirm('Are you sure you want to delete this message?')) {
        return;
    }
    
    fetch('../endpoints/delete_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `message_id=${messageId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageDiv = buttonElement.closest('.message');
            messageDiv.remove();
        } else {
            alert('Failed to delete message: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete message.');
    });
}

// Add hover functionality for message actions with delay
document.addEventListener('mouseover', function(e) {
    if (e.target.closest('.message')) {
        const message = e.target.closest('.message');
        const actions = message.querySelector('.message-actions');
        if (actions) {
            actions.style.display = 'flex';
            setTimeout(() => {
                actions.style.opacity = '1';
            }, 10);
        }
    }
});

document.addEventListener('mouseout', function(e) {
    if (e.target.closest('.message')) {
        const message = e.target.closest('.message');
        const actions = message.querySelector('.message-actions');
        if (actions) {
            // Add a small delay before hiding actions
            setTimeout(() => {
                if (!actions.matches(':hover') && !message.matches(':hover')) {
                    actions.style.opacity = '0';
                    setTimeout(() => {
                        actions.style.display = 'none';
                    }, 200);
                }
            }, 300);
        }
    }
});

// Typing indicator functions
function setTypingStatus(isTyping) {
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        const otherId = chatContainer.getAttribute('data-other-id');
        if (otherId) {
            fetch('../endpoints/typing_indicator.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `other_id=${otherId}&is_typing=${isTyping ? 1 : 0}&action=set_typing`
            })
            .catch(error => {
                console.error('Error setting typing status:', error);
            });
        }
    }
}

function updateTypingIndicator() {
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        const otherId = chatContainer.getAttribute('data-other-id');
        if (otherId) {
            fetch('../endpoints/typing_indicator.php?action=get_typing&other_id=' + otherId)
            .then(response => response.json())
            .then(data => {
                const typingIndicator = document.getElementById('typing-indicator');
                const typingText = document.getElementById('typing-text');
                if (typingIndicator && typingText) {
                    if (data.is_typing && data.typing_user) {
                        typingText.textContent = data.typing_user + ' is typing...';
                        typingIndicator.style.display = 'block';
                    } else {
                        typingIndicator.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error checking typing status:', error);
            });
        }
    }
}


// Periodically update typing indicator
setInterval(updateTypingIndicator, 2000);

// Search messages function
function searchMessages() {
    const searchTerm = document.getElementById('search-messages').value.trim();
    const clearSearchBtn = document.getElementById('clear-search');
    
    if (searchTerm === '') {
        clearSearchBtn.style.display = 'none';
        return;
    }
    
    clearSearchBtn.style.display = 'inline-block';
    
    const chatContainer = document.getElementById('chat-messages');
    if (!chatContainer) return;
    
    const otherId = chatContainer.getAttribute('data-other-id');
    if (!otherId) return;
    
    // Show loading state
    const originalContent = chatContainer.innerHTML;
    chatContainer.innerHTML = '<div class="searching-messages"><i class="fas fa-spinner fa-spin"></i> Searching messages...</div>';
    
    // Fetch search results
    fetch(`../endpoints/search_messages.php?other_id=${otherId}&search_term=${encodeURIComponent(searchTerm)}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages.length > 0) {
            // Build HTML for search results
            let html = '<div class="search-results-header">Search Results for: "' + escapeHtml(searchTerm) + '"</div>';
            data.messages.forEach(function(msg) {
                const isSent = msg.sender_id == window.userId;
                const messageClass = isSent ? 'sent' : 'received';
                html += '<div class="message search-result ' + messageClass + '" data-message-id="' + msg.id + '">';
                html += '<div class="message-content">';
                if (msg.message) {
                    // Highlight search terms
                    const highlightedMessage = escapeHtml(msg.message).replace(new RegExp('(' + escapeHtml(searchTerm) + ')', 'gi'), '<mark>$1</mark>');
                    html += '<div class="message-text">' + formatMessage(highlightedMessage) + '</div>';
                }
                
                // Handle attachments
                if (msg.attachment) {
                    const fileName = msg.attachment.split('/').pop();
                    const fileExt = fileName.split('.').pop().toLowerCase();
                    
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                        html += '<div class="image-attachment">';
                        html += '<img src="' + msg.attachment + '" alt="Attachment" class="attachment-image" onclick="openImageModal(\'' + msg.attachment + '\')">';
                        html += '</div>';
                    } else if (['mp4', 'mov', 'avi', 'wmv'].includes(fileExt)) {
                        html += '<div class="video-attachment">';
                        html += '<video controls class="attachment-video">';
                        html += '<source src="' + msg.attachment + '" type="video/' + fileExt + '">';
                        html += 'Your browser does not support the video tag.';
                        html += '</video>';
                        html += '<div class="file-info">';
                        html += '<i class="fas fa-file-video"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</div>';
                        html += '</div>';
                    } else if (fileExt === 'pdf') {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file-pdf"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    } else if (['doc', 'docx'].includes(fileExt)) {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file-word"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    } else {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    }
                }
                
                html += '</div>';
                html += '<div class="message-meta">';
                html += '<span class="message-time">' + formatDate(msg.sent_at) + '</span>';
                html += '</div>';
                html += '</div>';
            });
            html += '<div class="search-results-footer"><button id="clear-search-results" class="btn">Back to Full Chat</button></div>';
            chatContainer.innerHTML = html;
            
            // Add event listener for clearing search results
            document.getElementById('clear-search-results').addEventListener('click', function() {
                chatContainer.innerHTML = originalContent;
                // Reattach event listeners if needed
                document.getElementById('search-messages').value = '';
                clearSearchBtn.style.display = 'none';
            });
        } else {
            chatContainer.innerHTML = originalContent;
            alert('No messages found matching your search.');
        }
    })
    .catch(error => {
        console.error('Error searching messages:', error);
        chatContainer.innerHTML = originalContent;
        alert('Error searching messages. Please try again.');
    });
}

// Farmer search function
function searchFarmers() {
    const searchTerm = document.getElementById('farmer-search').value.toLowerCase().trim();
    const farmerItems = document.querySelectorAll('.farmer-item');
    const clearSearchBtn = document.getElementById('clear-farmer-search');
    
    if (searchTerm === '') {
        // Show all farmers
        farmerItems.forEach(item => {
            item.style.display = 'flex';
        });
        clearSearchBtn.style.display = 'none';
        return;
    }
    
    clearSearchBtn.style.display = 'inline-block';
    
    // Filter farmers based on search term
    farmerItems.forEach(item => {
        const farmerName = item.querySelector('.farmer-name').textContent.toLowerCase();
        const farmerLocation = item.querySelector('.farmer-location').textContent.toLowerCase();
        
        if (farmerName.includes(searchTerm) || farmerLocation.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Load more messages function
function loadMoreMessages() {
    const messageContainer = document.getElementById('chat-messages');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (!messageContainer || !loadMoreBtn) return;
    
    // Get the first message ID (oldest message)
    const messages = messageContainer.querySelectorAll('.message');
    if (messages.length === 0) return;
    
    const firstMessageId = parseInt(messages[0].getAttribute('data-message-id'));
    const otherId = messageContainer.getAttribute('data-other-id');
    
    if (!firstMessageId || !otherId) return;
    
    // Save current scroll position
    const scrollTop = messageContainer.scrollTop;
    const scrollHeight = messageContainer.scrollHeight;
    
    // Disable button and show loading state
    loadMoreBtn.disabled = true;
    loadMoreBtn.textContent = 'Loading...';
    
    // Fetch older messages
    fetch(`../endpoints/load_more_messages.php?other_id=${otherId}&before_message_id=${firstMessageId}&limit=20`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.messages.length > 0) {
            // Prepend messages to the container
            let html = '';
            data.messages.forEach(function(msg) {
                const isSent = msg.sender_id == window.userId;
                const messageClass = isSent ? 'sent' : 'received';
                html += '<div class="message ' + messageClass + '" data-message-id="' + msg.id + '">';
                html += '<div class="message-content">';
                if (msg.message) {
                    html += '<div class="message-text">' + formatMessage(escapeHtml(msg.message)) + '</div>';
                }
                
                // Handle attachments
                if (msg.attachment) {
                    const fileName = msg.attachment.split('/').pop();
                    const fileExt = fileName.split('.').pop().toLowerCase();
                    
                    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
                        html += '<div class="image-attachment">';
                        html += '<img src="' + msg.attachment + '" alt="Attachment" class="attachment-image" onclick="openImageModal(\'' + msg.attachment + '\')">';
                        html += '</div>';
                    } else if (['mp4', 'mov', 'avi', 'wmv'].includes(fileExt)) {
                        html += '<div class="video-attachment">';
                        html += '<video controls class="attachment-video">';
                        html += '<source src="' + msg.attachment + '" type="video/' + fileExt + '">';
                        html += 'Your browser does not support the video tag.';
                        html += '</video>';
                        html += '<div class="file-info">';
                        html += '<i class="fas fa-file-video"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</div>';
                        html += '</div>';
                    } else if (fileExt === 'pdf') {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file-pdf"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    } else if (['doc', 'docx'].includes(fileExt)) {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file-word"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    } else {
                        html += '<div class="file-attachment">';
                        html += '<a href="' + msg.attachment + '" target="_blank">';
                        html += '<i class="fas fa-file"></i>';
                        html += '<span>' + fileName + '</span>';
                        html += '</a>';
                        html += '</div>';
                    }
                }
                
                html += '</div>';
                html += '<div class="message-meta">';
                html += '<span class="message-time">' + formatDate(msg.sent_at) + '</span>';
                
                // Add delivery status for sent messages
                if (isSent) {
                    html += '<div class="message-status">';
                    if (msg.delivery_status && msg.delivery_status === 'read') {
                        html += '<span class="status-read" title="Read">&#10003;&#10003;&#10003;</span>';
                    } else if (msg.delivery_status && msg.delivery_status === 'delivered') {
                        html += '<span class="status-delivered" title="Delivered">&#10003;&#10003;</span>';
                    } else {
                        html += '<span class="status-sent" title="Sent">&#10003;</span>';
                    }
                    html += '</div>';
                    
                    // Add edit/delete buttons for own messages
                    html += '<div class="message-actions">';
                    html += '<button class="btn-edit" onclick="editMessage(' + msg.id + ', this)" title="Edit message"><i class="fas fa-edit"></i></button>';
                    html += '<button class="btn-delete" onclick="deleteMessage(' + msg.id + ', this)" title="Delete message"><i class="fas fa-trash"></i></button>';
                    html += '</div>';
                }
                
                html += '</div>';
                
                
                html += '</div>';
            });
            
            // Insert messages at the beginning
            messageContainer.insertAdjacentHTML('afterbegin', html);
            
            // Maintain scroll position
            const newScrollHeight = messageContainer.scrollHeight;
            messageContainer.scrollTop = scrollTop + (newScrollHeight - scrollHeight);
            
            // Hide load more button if no more messages
            if (!data.has_more) {
                loadMoreContainer.style.display = 'none';
            }
        } else {
            // Hide load more button if no more messages
            loadMoreContainer.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Error loading more messages:', error);
        alert('Error loading more messages. Please try again.');
    })
    .finally(() => {
        // Reset button
        loadMoreBtn.disabled = false;
        loadMoreBtn.textContent = 'Load More Messages';
    });
}

</script>
</body>
</html>