// General JavaScript functions for Agro Connect

// Confirm delete action
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

// Show/hide elements
function toggleVisibility(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.style.display = element.style.display === 'none' ? 'block' : 'none';
    }
}

// AJAX helper function
function ajaxRequest(url, method = 'GET', data = null, callback = null) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                if (callback) {
                    callback(xhr.responseText);
                }
            } else {
                console.error('AJAX Error:', xhr.status, xhr.statusText);
            }
        }
    };

    if (data) {
        const formData = new URLSearchParams(data).toString();
        xhr.send(formData);
    } else {
        xhr.send();
    }
}

// Build API path relative to current page so AJAX calls work from subfolders
function apiPath(file) {
    const path = window.location.pathname;
    // If we're in a subfolder like /farmer/, /admin/, /expert/ prefix ../
    if (path.includes('/farmer/') || path.includes('/admin/') || path.includes('/expert/')) {
        return '../endpoints/' + file;
    }
    return 'endpoints/' + file;
}

// Enhanced chat functionality
let chatRefreshInterval;
let lastMessageId = 0;
let isRefreshing = false;

// Enhanced message search functionality
function searchMessages() {
    const searchTerm = document.getElementById('message-search').value.toLowerCase();
    const messages = document.querySelectorAll('.message');
    
    messages.forEach(message => {
        const messageText = message.querySelector('.message-content')?.textContent.toLowerCase() || '';
        const senderName = message.querySelector('strong')?.textContent.toLowerCase() || '';
        
        if (searchTerm === '' || messageText.includes(searchTerm) || senderName.includes(searchTerm)) {
            message.style.display = 'block';
        } else {
            message.style.display = 'none';
        }
    });
}

// Enhanced attachment element creation
function createAttachmentElement(attachment) {
    const fileName = attachment.split('/').pop();
    const fileExt = fileName.split('.').pop().toLowerCase();
    
    let html = '<div class="attachment">';
    
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExt)) {
        html += `
            <div class="image-attachment">
                <img src="${attachment}" alt="Attachment" class="attachment-image" onclick="openImageModal('${attachment}')">
                <div class="attachment-info">
                    <i class="fas fa-image"></i>
                    <span>${fileName}</span>
                </div>
            </div>
        `;
    } else if (fileExt === 'pdf') {
        html += `
            <div class="file-attachment">
                <a href="${attachment}" target="_blank">
                    <i class="fas fa-file-pdf"></i>
                    <span>${fileName}</span>
                </a>
            </div>
        `;
    } else if (['doc', 'docx'].includes(fileExt)) {
        html += `
            <div class="file-attachment">
                <a href="${attachment}" target="_blank">
                    <i class="fas fa-file-word"></i>
                    <span>${fileName}</span>
                </a>
            </div>
        `;
    } else {
        html += `
            <div class="file-attachment">
                <a href="${attachment}" target="_blank">
                    <i class="fas fa-file"></i>
                    <span>${fileName}</span>
                </a>
            </div>
        `;
    }
    
    html += '</div>';
    return html;
}

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

    fetch(apiPath('edit_message.php'), {
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
            loadChatMessages(true); // Reload messages instead of full page reload
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to edit message.');
        loadChatMessages(true); // Reload messages instead of full page reload
    });
}

function cancelEdit(messageContent, originalText) {
    messageContent.innerHTML = formatMessage(escapeHtml(originalText));
}

// Delete message functionality
function deleteMessage(messageId, buttonElement) {
    if (!confirm('Are you sure you want to delete this message?')) {
        return;
    }

    fetch(apiPath('delete_message.php'), {
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
            loadChatMessages(true); // Reload messages instead of showing error
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete message.');
        loadChatMessages(true); // Reload messages instead of showing error
    });
}

// Enhanced file upload display
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('attachment');
    const fileNameDisplay = document.getElementById('file-name');
    const clearFileBtn = document.getElementById('clear-file');
    const fileInfo = document.getElementById('file-info');
    
    if (fileInput && fileNameDisplay && clearFileBtn && fileInfo) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileNameDisplay.textContent = file.name;
                clearFileBtn.style.display = 'flex';
                fileInfo.style.display = 'flex';
            } else {
                fileNameDisplay.textContent = '';
                clearFileBtn.style.display = 'none';
                fileInfo.style.display = 'none';
            }
        });
        
        clearFileBtn.addEventListener('click', function() {
            fileInput.value = '';
            fileNameDisplay.textContent = '';
            clearFileBtn.style.display = 'none';
            fileInfo.style.display = 'none';
        });
    }
    
    // Handle form submission
    const chatForm = document.getElementById('chat-form');
    if (chatForm) {
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage(chatForm);
        });
    }
});

// Initialize chat enhancements when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Typing indicator
    const messageInput = document.querySelector('input[name="message"]');
    const sendButton = document.querySelector('.send-button');
    
    // Function to update send button state
    function updateSendButtonState() {
        if (messageInput && sendButton) {
            const hasText = messageInput.value.trim().length > 0;
            const hasFile = document.getElementById('attachment')?.files.length > 0;
            
            // Enable button if there's text or a file
            sendButton.disabled = !(hasText || hasFile);
        }
    }
    
    if (messageInput) {
        // Initial state check
        updateSendButtonState();
        
        // Update button state on input
        messageInput.addEventListener('input', function() {
            updateSendButtonState();
            
            let typingTimer;
            const doneTypingInterval = 1000;

            clearTimeout(typingTimer);
            const urlParams = new URLSearchParams(window.location.search);
            let otherId = urlParams.get('farmer_id');

            if (!otherId) {
                const chatContainer = document.getElementById('chat-messages');
                otherId = chatContainer ? chatContainer.getAttribute('data-other-id') : null;
            }

            if (otherId) {
                setTypingStatus(true, otherId);

                typingTimer = setTimeout(function() {
                    setTypingStatus(false, otherId);
                }, doneTypingInterval);
            }
        });
        
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const form = messageInput.closest('form');
                if (form) {
                    // Only send if there's content
                    if (messageInput.value.trim() || document.getElementById('attachment')?.files.length > 0) {
                        sendMessage(form);
                    }
                }
            }
        });
    }
    
    // Also check file input for changes
    const fileInput = document.getElementById('attachment');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            updateSendButtonState();
        });
    }

    // Message search
    const searchInput = document.getElementById('message-search');
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchMessages, 300); // Debounce search
        });

        // Clear search button
        const clearSearchBtn = document.getElementById('clear-search');
        if (clearSearchBtn) {
            clearSearchBtn.addEventListener('click', function() {
                searchInput.value = '';
                loadChatMessages();
            });
        }
    }

    // Refresh chat button
    const refreshBtn = document.getElementById('refresh-chat');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            loadChatMessages(true);
        });
    }
    
    // Add scroll event listener for loading older messages
    const messageContainer = document.getElementById('chat-messages');
    if (messageContainer) {
        let isLoading = false;
        
        messageContainer.addEventListener('scroll', function() {
            // Check if user scrolled to top
            if (messageContainer.scrollTop === 0 && !isLoading) {
                isLoading = true;
                
                // Show loading indicator
                const loadMoreContainer = document.getElementById('load-more-container');
                if (loadMoreContainer) {
                    loadMoreContainer.style.display = 'block';
                    const loadMoreBtn = document.getElementById('load-more-btn');
                    if (loadMoreBtn) {
                        loadMoreBtn.textContent = 'Loading...';
                        loadMoreBtn.disabled = true;
                    }
                    
                    // Load more messages
                    loadMoreMessages(function() {
                        isLoading = false;
                        // Restore button state
                        if (loadMoreBtn) {
                            loadMoreBtn.textContent = 'Load More Messages';
                            loadMoreBtn.disabled = false;
                        }
                    });
                }
            }
        });
        
        // Periodically update typing indicator
        setInterval(updateTypingIndicator, 2000);
    }

    // Start chat refresh
    startChatRefresh();
    
    // Auto scroll to bottom of messages
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
});

// Load more messages function
function loadMoreMessages(callback) {
    const messageContainer = document.getElementById('chat-messages');
    const loadMoreBtn = document.getElementById('load-more-btn');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (!messageContainer || !loadMoreBtn) {
        if (callback) callback();
        return;
    }
    
    // Get the first message ID (oldest message)
    const messages = messageContainer.querySelectorAll('.message');
    if (messages.length === 0) {
        if (callback) callback();
        return;
    }
    
    const firstMessageId = parseInt(messages[0].getAttribute('data-message-id'));
    const otherId = messageContainer.getAttribute('data-other-id');
    
    if (!firstMessageId || !otherId) {
        if (callback) callback();
        return;
    }
    
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
        if (callback) callback();
    });
}

// Equipment booking
function bookEquipment(equipmentId) {
    // Open modal instead of prompt
    openBookingModal(equipmentId);
}

// Wishlist functionality
function toggleWishlist(equipmentId, button) {
    ajaxRequest(apiPath('toggle_wishlist.php'), 'POST', {
        equipment_id: equipmentId
    }, function(response) {
        const data = JSON.parse(response);
        if (data.success) {
            if (data.action === 'added') {
                button.innerHTML = '<i class="fas fa-heart" style="color: red;"></i>';
                button.classList.add('wishlisted');
            } else {
                button.innerHTML = '<i class="fas fa-heart"></i>';
                button.classList.remove('wishlisted');
            }
        } else {
            alert('Error updating wishlist');
        }
    });
}

function removeFromWishlist(equipmentId, button) {
    if (confirm('Remove this item from your wishlist?')) {
        ajaxRequest(apiPath('toggle_wishlist.php'), 'POST', {
            equipment_id: equipmentId,
            remove: true
        }, function(response) {
            const data = JSON.parse(response);
            if (data.success) {
                location.reload(); // Reload the page automatically after removal
            } else {
                alert('Error removing from wishlist');
            }
        });
    }
}

// Booking modal functions
function openBookingModal(equipmentId) {
    const modal = document.getElementById('booking-modal');
    const equipmentIdInput = document.getElementById('equipment-id');

    if (modal && equipmentIdInput) {
        equipmentIdInput.value = equipmentId;
        modal.style.display = 'block';

        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start-date').min = today;
        document.getElementById('end-date').min = today;
    }
}

function closeBookingModal() {
    const modal = document.getElementById('booking-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Update booking details when dates change
document.addEventListener('DOMContentLoaded', function() {
    const startDateInput = document.getElementById('start-date');
    const endDateInput = document.getElementById('end-date');

    if (startDateInput && endDateInput) {
        [startDateInput, endDateInput].forEach(input => {
            input.addEventListener('change', updateBookingDetails);
        });
    }

    // Booking form submission
    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBooking();
        });
    }
});

function updateBookingDetails() {
    const startDate = document.getElementById('start-date').value;
    const endDate = document.getElementById('end-date').value;
    const equipmentId = document.getElementById('equipment-id').value;
    const detailsDiv = document.getElementById('booking-details');

    if (startDate && endDate && equipmentId) {
        ajaxRequest(apiPath('farmer/bookings/get_booking_details.php'), 'POST', {
            equipment_id: equipmentId,
            start_date: startDate,
            end_date: endDate
        }, function(response) {
            detailsDiv.innerHTML = response;
        });
    } else {
        detailsDiv.innerHTML = '';
    }
}

function submitBooking() {
    const formData = new FormData(document.getElementById('booking-form'));

    ajaxRequest(apiPath('farmer/bookings/book_equipment.php'), 'POST', {
        equipment_id: formData.get('equipment_id'),
        start_date: formData.get('start_date'),
        end_date: formData.get('end_date')
    }, function(response) {
        alert('Booking request submitted successfully!');
        closeBookingModal();
        location.reload();
    });
}

// File upload validation
function validateFileUpload(input) {
    const file = input.files[0];
    if (file) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            alert('Please upload a valid file (JPEG, PNG, GIF, or PDF).');
            input.value = '';
            return false;
        }

        if (file.size > maxSize) {
            alert('File size must be less than 5MB.');
            input.value = '';
            return false;
        }
    }
    return true;
}

// Weather widget
function loadWeather() {
    const weatherContainer = document.getElementById('weather-widget');
    if (weatherContainer) {
        fetch(apiPath('get_weather.php'))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const weather = data.data;
                    const html = `
                        <div class="weather-content">
                            <div class="weather-main">
                                <img src="http://openweathermap.org/img/wn/${weather.icon}@2x.png" alt="${weather.description}" class="weather-icon">
                                <div class="temperature">${weather.temperature}°C</div>
                                <div class="description">${weather.description}</div>
                            </div>
                            <div class="weather-details">
                                <div class="detail-item">
                                    <i class="fas fa-tint"></i>
                                    <span>Humidity: ${weather.humidity}%</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-wind"></i>
                                    <span>Wind: ${weather.wind_speed} m/s</span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>${weather.location}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    weatherContainer.innerHTML = html;
                } else {
                    weatherContainer.innerHTML = '<p>Weather data unavailable. Please try again later.</p>';
                }
            })
            .catch(error => {
                console.error('Weather error:', error);
                weatherContainer.innerHTML = '<p>Weather data unavailable. Please try again later.</p>';
            });
    }
}

// Equipment management functionality
function initEquipmentManagement() {
    // View toggle functionality
    const listViewBtn = document.getElementById('list-view-btn');
    const gridViewBtn = document.getElementById('grid-view-btn');
    const cardViewBtn = document.getElementById('card-view-btn');

    if (listViewBtn && gridViewBtn && cardViewBtn) {
        listViewBtn.addEventListener('click', function() {
            showView('table');
            setActiveButton('list-view-btn');
        });

        gridViewBtn.addEventListener('click', function() {
            showView('grid');
            setActiveButton('grid-view-btn');
        });

        cardViewBtn.addEventListener('click', function() {
            showView('cards');
            setActiveButton('card-view-btn');
        });
    }

    // Sorting and filtering
    const sortSelect = document.getElementById('sort-select');
    const filterType = document.getElementById('filter-type');
    const filterAvailability = document.getElementById('filter-availability');

    if (sortSelect && filterType && filterAvailability) {
        sortSelect.addEventListener('change', filterAndSort);
        filterType.addEventListener('change', filterAndSort);
        filterAvailability.addEventListener('change', filterAndSort);
    }

    // Edit modal functionality
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            openEditModal(this);
        });
    });

    // Close modal
    const closeBtn = document.querySelector('.close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            closeEditModal();
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('edit-modal');
        if (modal && event.target === modal) {
            closeEditModal();
        }
    });
}

function showView(viewType) {
    const table = document.getElementById('equipment-table');
    const grid = document.getElementById('equipment-grid');
    const cards = document.getElementById('equipment-cards');

    if (table) table.style.display = viewType === 'table' ? 'table' : 'none';
    if (grid) grid.style.display = viewType === 'grid' ? 'grid' : 'none';
    if (cards) cards.style.display = viewType === 'cards' ? 'flex' : 'none';
}

function setActiveButton(buttonId) {
    document.querySelectorAll('.view-toggle .btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(buttonId);
    if (activeBtn) activeBtn.classList.add('active');
}

function filterAndSort() {
    const sortBy = document.getElementById('sort-select')?.value;
    const filterType = document.getElementById('filter-type')?.value;
    const filterAvailability = document.getElementById('filter-availability')?.value;

    const items = document.querySelectorAll('#equipment-container > div > div, #equipment-container > table > tbody > tr');

    items.forEach(item => {
        const type = item.getAttribute('data-type');
        const availability = item.getAttribute('data-availability');
        const name = item.getAttribute('data-name');
        const price = parseFloat(item.getAttribute('data-price'));
        const date = parseInt(item.getAttribute('data-date'));

        let show = true;

        if (filterType && type !== filterType) show = false;
        if (filterAvailability && availability !== filterAvailability) show = false;

        item.style.display = show ? '' : 'none';
    });

    // Sort visible items
    const container = document.getElementById('equipment-container');
    if (!container) return;

    const visibleItems = Array.from(items).filter(item => item.style.display !== 'none');

    visibleItems.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            case 'price_per_day':
                return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
            case 'type':
                return a.getAttribute('data-type').localeCompare(b.getAttribute('data-type'));
            case 'created_at':
                return parseInt(b.getAttribute('data-date')) - parseInt(a.getAttribute('data-date'));
            default:
                return 0;
        }
    });

    // Reorder items in DOM
    visibleItems.forEach(item => {
        if (item.tagName === 'TR') {
            const tbody = container.querySelector('tbody');
            if (tbody) tbody.appendChild(item);
        } else {
            container.appendChild(item);
        }
    });
}

function openEditModal(button) {
    const modal = document.getElementById('edit-modal');
    const form = document.getElementById('edit-form');

    if (!modal || !form) return;

    // Populate form fields
    document.getElementById('edit-equipment-id').value = button.getAttribute('data-id') || '';
    document.getElementById('edit-name').value = button.getAttribute('data-name') || '';
    document.getElementById('edit-description').value = button.getAttribute('data-description') || '';
    document.getElementById('edit-type').value = button.getAttribute('data-type') || '';
    document.getElementById('edit-price').value = button.getAttribute('data-price') || '';
    document.getElementById('edit-location').value = button.getAttribute('data-location') || '';
    document.getElementById('edit-existing-image').value = button.getAttribute('data-image') || '';

    // Show current image preview
    const imagePreview = document.getElementById('current-image-preview');
    if (imagePreview) {
        const imagePath = button.getAttribute('data-image');
        if (imagePath) {
            imagePreview.innerHTML = '<img src="../' + imagePath + '" style="max-width: 100px; max-height: 100px;">';
        } else {
            imagePreview.innerHTML = 'No image';
        }
    }

    modal.style.display = 'block';
}

function closeEditModal() {
    const modal = document.getElementById('edit-modal');
    if (modal) modal.style.display = 'none';
}

// Farmer equipment management functionality
function initFarmerEquipmentManagement() {
    // View toggle functionality
    const listViewBtn = document.getElementById('farmer-list-view-btn');
    const gridViewBtn = document.getElementById('farmer-grid-view-btn');
    const cardViewBtn = document.getElementById('farmer-card-view-btn');

    if (listViewBtn && gridViewBtn && cardViewBtn) {
        listViewBtn.addEventListener('click', function() {
            showFarmerView('table');
            setFarmerActiveButton('farmer-list-view-btn');
        });

        gridViewBtn.addEventListener('click', function() {
            showFarmerView('grid');
            setFarmerActiveButton('farmer-grid-view-btn');
        });

        cardViewBtn.addEventListener('click', function() {
            showFarmerView('cards');
            setFarmerActiveButton('farmer-card-view-btn');
        });
    }

    // Sorting and filtering
    const sortSelect = document.getElementById('farmer-sort-select');
    const filterType = document.getElementById('farmer-filter-type');

    if (sortSelect && filterType) {
        sortSelect.addEventListener('change', farmerFilterAndSort);
        filterType.addEventListener('change', farmerFilterAndSort);
    }
}

function showFarmerView(viewType) {
    const table = document.getElementById('farmer-equipment-table');
    const grid = document.getElementById('farmer-equipment-grid');
    const cards = document.getElementById('farmer-equipment-cards');

    if (table) table.style.display = viewType === 'table' ? 'table' : 'none';
    if (grid) grid.style.display = viewType === 'grid' ? 'grid' : 'none';
    if (cards) cards.style.display = viewType === 'cards' ? 'flex' : 'none';
}

function setFarmerActiveButton(buttonId) {
    document.querySelectorAll('.view-toggle .btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(buttonId);
    if (activeBtn) activeBtn.classList.add('active');
}

function farmerFilterAndSort() {
    const sortBy = document.getElementById('farmer-sort-select')?.value;
    const filterType = document.getElementById('farmer-filter-type')?.value;

    const items = document.querySelectorAll('#farmer-equipment-container > div > div, #farmer-equipment-container > table > tbody > tr');

    items.forEach(item => {
        const type = item.getAttribute('data-type');
        const name = item.getAttribute('data-name');
        const price = parseFloat(item.getAttribute('data-price'));

        let show = true;

        if (filterType && type !== filterType) show = false;

        item.style.display = show ? '' : 'none';
    });

    // Sort visible items
    const container = document.getElementById('farmer-equipment-container');
    if (!container) return;

    const visibleItems = Array.from(items).filter(item => item.style.display !== 'none');

    visibleItems.sort((a, b) => {
        switch (sortBy) {
            case 'name':
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            case 'price_per_day':
                return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
            case 'type':
                return a.getAttribute('data-type').localeCompare(b.getAttribute('data-type'));
            default:
                return 0;
        }
    });

    // Reorder items in DOM
    visibleItems.forEach(item => {
        if (item.tagName === 'TR') {
            const tbody = container.querySelector('tbody');
            if (tbody) tbody.appendChild(item);
        } else {
            container.appendChild(item);
        }
    });
}

// View toggle functionality for wishlist and bookings
function initViewToggle(pageType) {
    const listViewBtn = document.getElementById('list-view-btn');
    const gridViewBtn = document.getElementById('grid-view-btn');

    if (listViewBtn && gridViewBtn) {
        listViewBtn.addEventListener('click', function() {
            setView('list');
        });

        gridViewBtn.addEventListener('click', function() {
            setView('grid');
        });
    }

    // Load saved view preference on page load
    const savedView = localStorage.getItem(pageType + 'ViewPreference') || 'list';
    setView(savedView);
}

function setView(viewType) {
    // Determine page type
    const isWishlist = window.location.pathname.includes('wishlist');
    const isBookings = window.location.pathname.includes('my_bookings');

    let listView, gridView;

    if (isWishlist) {
        listView = document.getElementById('wishlist-list-view');
        gridView = document.getElementById('wishlist-grid-view');
    } else if (isBookings) {
        listView = document.getElementById('booking-list-view');
        gridView = document.getElementById('booking-grid-view');
    } else {
        // Fallback for other pages
        listView = document.getElementById('list-view');
        gridView = document.getElementById('grid-view');
    }

    const listBtn = document.getElementById('list-view-btn');
    const gridBtn = document.getElementById('grid-view-btn');

    if (viewType === 'list') {
        if (listView) listView.style.display = 'block';
        if (gridView) gridView.style.display = 'none';
        if (listBtn) listBtn.classList.add('active');
        if (gridBtn) gridBtn.classList.remove('active');
    } else {
        if (listView) listView.style.display = 'none';
        if (gridView) gridView.style.display = 'block';
        if (listBtn) listBtn.classList.remove('active');
        if (gridBtn) gridBtn.classList.add('active');
    }

    // Save preference to localStorage
    const pageType = isWishlist ? 'wishlistViewPreference' : 'bookingViewPreference';
    localStorage.setItem(pageType, viewType);
}

// Fix grid view toggle for my_bookings.php
document.addEventListener('DOMContentLoaded', function() {
    const listViewBtn = document.getElementById('list-view-btn');
    const gridViewBtn = document.getElementById('grid-view-btn');

    if (listViewBtn && gridViewBtn) {
        listViewBtn.addEventListener('click', function() {
            setView('list');
        });

        gridViewBtn.addEventListener('click', function() {
            setView('grid');
        });
    }
});

// Set user ID for JavaScript (will be overridden by PHP if available)
if (typeof window.userId === 'undefined') {
    window.userId = 0;
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    loadWeather();
    initEquipmentManagement();
    initFarmerEquipmentManagement();
    initViewToggle();
    
    // Initialize chat functionality
    const messageContainer = document.getElementById('chat-messages');
    if (messageContainer) {
        // Start chat refresh
        startChatRefresh();
        
        // Auto scroll to bottom of messages
        messageContainer.scrollTop = messageContainer.scrollHeight;
        
        // Set up typing indicator for message input
        const messageInput = document.querySelector('input[name="message"]');
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
    }
});

// Helper functions for enhanced chat features
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatMessage(text) {
    // Convert URLs to links
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    return text.replace(urlRegex, '<a href="$1" target="_blank">$1</a>');
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
}

function isScrolledToBottom(element) {
    return element.scrollHeight - element.clientHeight <= element.scrollTop + 1;
}

// Load chat messages function
function loadChatMessages(scrollToBottom = false) {
    const messageContainer = document.getElementById('chat-messages');
    if (!messageContainer) return;

    const otherId = messageContainer.getAttribute('data-other-id');
    if (!otherId) return;

    // Show loading indicator
    const originalHTML = messageContainer.innerHTML;
    messageContainer.innerHTML = '<div class="loading">Loading messages...</div>';

    fetch(`${apiPath('get_messages.php')}?other_id=${otherId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
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
                            // Add empty message actions for received messages
                            html += '<div class="message-actions">';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        
                        html += '</div>';
                    });
                }
                messageContainer.innerHTML = html;
                
                // Auto-scroll to bottom if requested or if user was already at bottom
                if (scrollToBottom || isScrolledToBottom(messageContainer)) {
                    messageContainer.scrollTop = messageContainer.scrollHeight;
                }
            } else {
                messageContainer.innerHTML = originalHTML;
                alert('Error loading messages: ' + data.error);
            }
        })
        .catch(error => {
            messageContainer.innerHTML = originalHTML;
            console.error('Error:', error);
            alert('Error loading messages.');
        });
}

// Send message function
function sendMessage(form) {
    const formData = new FormData(form);
    const messageInput = form.querySelector('input[name="message"]');
    const fileInput = form.querySelector('input[name="attachment"]');
    const sendButton = form.querySelector('.send-button');
    
    const message = messageInput.value.trim();
    const receiverId = document.getElementById('chat-messages')?.getAttribute('data-other-id');
    
    // Disable send button during submission
    sendButton.disabled = true;
    sendButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    // Check if we have a file or message
    if ((!message && !fileInput?.files[0]) || !receiverId) {
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
        return;
    }
    
    // Add receiver_id to formData for both text and file messages
    formData.append('receiver_id', receiverId);
    
    // Use AJAX for both text and file uploads
    fetch(apiPath('send_message.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear input fields
            messageInput.value = '';
            if (fileInput) {
                fileInput.value = '';
                // Also clear file info display
                const fileInfo = document.getElementById('file-info');
                const fileNameDisplay = document.getElementById('file-name');
                const clearFileBtn = document.getElementById('clear-file');
                if (fileInfo && fileNameDisplay && clearFileBtn) {
                    fileNameDisplay.textContent = '';
                    clearFileBtn.style.display = 'none';
                    fileInfo.style.display = 'none';
                }
            }
            
            // Reload messages and scroll to bottom
            loadChatMessages(true);
        } else {
            alert('Failed to send message: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send message.');
    })
    .finally(() => {
        // Re-enable send button
        sendButton.disabled = false;
        sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
    });
}

// Start chat refresh interval
function startChatRefresh() {
    // Clear any existing interval
    if (window.chatRefreshInterval) {
        clearInterval(window.chatRefreshInterval);
    }
    
    // Set up interval to refresh messages every 1 second for better real-time experience
    window.chatRefreshInterval = setInterval(function() {
        // Only fetch new messages if user is at the bottom of the chat
        const messageContainer = document.getElementById('chat-messages');
        if (messageContainer) {
            const isAtBottom = isScrolledToBottom(messageContainer);
            if (isAtBottom) {
                fetchNewMessages();
            }
        }
    }, 1000);
}

// Fetch only new messages
function fetchNewMessages() {
    const messageContainer = document.getElementById('chat-messages');
    if (!messageContainer) return;
    
    const otherId = messageContainer.getAttribute('data-other-id');
    if (!otherId) return;
    
    // Get the last message ID to fetch only newer messages
    const messages = messageContainer.querySelectorAll('.message');
    let lastMessageId = 0;
    if (messages.length > 0) {
        lastMessageId = parseInt(messages[messages.length - 1].getAttribute('data-message-id')) || 0;
    }
    
    fetch(`${apiPath('get_messages.php')}?other_id=${otherId}&last_message_id=${lastMessageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.messages.length > 0) {
                let html = '';
                let hasNewMessages = false;
                let shouldNotify = false;
                
                data.messages.forEach(function(msg) {
                    // Only add messages that are newer than what we currently have
                    const existingMessage = document.querySelector(`.message[data-message-id="${msg.id}"]`);
                    if (!existingMessage) {
                        hasNewMessages = true;
                        // Only notify for received messages (not sent by current user)
                        if (msg.sender_id != window.userId) {
                            shouldNotify = true;
                        }
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
                            // Add empty message actions for received messages
                            html += '<div class="message-actions">';
                            html += '</div>';
                        }
                        
                        html += '</div>';
                        
                        
                        html += '</div>';
                    }
                });
                
                // Append new messages
                if (html) {
                    messageContainer.insertAdjacentHTML('beforeend', html);
                    
                    // Auto-scroll to bottom if user is at bottom
                    const isAtBottom = isScrolledToBottom(messageContainer);
                    if (isAtBottom) {
                        messageContainer.scrollTop = messageContainer.scrollHeight;
                        
                        // Play notification sound if window is not focused and there are new received messages
                        if (shouldNotify && !document.hasFocus()) {
                            playNotificationSound();
                            
                            // Flash title to get attention
                            flashTitleNotification();
                        }
                    } else if (shouldNotify) {
                        // Show notification if user is not at bottom
                        showNewMessageNotification();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Error fetching new messages:', error);
        });
}

// Flash title notification
function flashTitleNotification() {
    const originalTitle = document.title;
    let flashCount = 0;
    const maxFlashes = 5;
    
    const flashInterval = setInterval(() => {
        if (flashCount >= maxFlashes || document.hasFocus()) {
            document.title = originalTitle;
            clearInterval(flashInterval);
            return;
        }
        
        document.title = (flashCount % 2 === 0) ? 'New Message! - ' + originalTitle : originalTitle;
        flashCount++;
    }, 1000);
}

// Show new message notification
function showNewMessageNotification() {
    // Create notification element if it doesn't exist
    let notification = document.getElementById('new-message-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'new-message-notification';
        notification.className = 'new-message-notification';
        notification.innerHTML = `
            <span>New messages received</span>
            <button onclick="scrollToBottom()">View</button>
        `;
        document.body.appendChild(notification);
    }
    
    // Show notification
    notification.style.display = 'flex';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        if (notification) {
            notification.style.display = 'none';
        }
    }, 5000);
}

// Scroll to bottom of messages
function scrollToBottom() {
    const messageContainer = document.getElementById('chat-messages');
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
        
        // Hide notification
        const notification = document.getElementById('new-message-notification');
        if (notification) {
            notification.style.display = 'none';
        }
    }
}

// Check if user is scrolled to bottom
function isScrolledToBottom(element) {
    return element.scrollHeight - element.clientHeight <= element.scrollTop + 10;
}

// Play notification sound
function playNotificationSound() {
    // Create audio context for notification sound
    try {
        const context = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = context.createOscillator();
        const gainNode = context.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(context.destination);
        
        oscillator.type = 'sine';
        oscillator.frequency.value = 800;
        gainNode.gain.value = 0.3;
        
        oscillator.start();
        setTimeout(() => {
            oscillator.stop();
        }, 150);
    } catch (e) {
        console.log('Audio notification not supported');
    }
}

// Typing indicator functions
function setTypingStatus(isTyping, otherId) {
    if (!otherId) {
        const chatContainer = document.getElementById('chat-messages');
        if (chatContainer) {
            otherId = chatContainer.getAttribute('data-other-id');
        }
    }
    
    if (otherId) {
        fetch(apiPath('typing_indicator.php'), {
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

function updateTypingIndicator() {
    const chatContainer = document.getElementById('chat-messages');
    if (chatContainer) {
        const otherId = chatContainer.getAttribute('data-other-id');
        if (otherId) {
            fetch(apiPath('typing_indicator.php?action=get_typing&other_id=' + otherId))
            .then(response => response.json())
            .then(data => {
                const typingIndicator = document.getElementById('typing-indicator');
                const typingText = document.getElementById('typing-text');
                if (typingIndicator && typingText) {
                    if (data.is_typing && data.typing_user) {
                        typingText.textContent = data.typing_user + ' is typing...';
                        typingIndicator.style.display = 'flex';
                    } else {
                        typingIndicator.style.display = 'none';
                    }
                }
            })
            .catch(error => {
                console.error('Error updating typing indicator:', error);
            });
        }
    }
}

