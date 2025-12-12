<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../includes/chat_functions.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'error' => ''];

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['error'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle both JSON and FormData requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a FormData request (file upload) or JSON request
    $isFormData = !empty($_FILES) || stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
    
    if ($isFormData) {
        // Handle FormData request (file upload)
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $message_content = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        // Handle file attachment
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $attachment = $_FILES['attachment'];
        }
    } else {
        // Handle JSON request (text only)
        $input = json_decode(file_get_contents('php://input'), true);
        $receiver_id = isset($input['receiver_id']) ? intval($input['receiver_id']) : 0;
        $message_content = isset($input['message']) ? trim($input['message']) : '';
        $attachment = null;
    }
    
    // For farmers and experts, automatically determine the receiver if not provided
    if ($receiver_id === 0 && ($user_role === 'farmer' || $user_role === 'expert')) {
        if ($user_role === 'farmer') {
            // Get the assigned expert for this farmer
            $stmt = $conn->prepare("
                SELECT e.id
                FROM experts e
                JOIN expert_farmer_assignments efa ON e.id = efa.expert_id
                WHERE efa.farmer_id = ? AND efa.status = 'active' AND e.status = 'active'
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $receiver_id = $row['id'];
            }
        } else if ($user_role === 'expert') {
            // For experts, we still need the farmer_id to be provided
            // This is handled in the chat interface where farmer_id is known
        }
    }
    
    if ($receiver_id === 0) {
        $response['error'] = 'Receiver ID is required.';
        echo json_encode($response);
        exit();
    }
    
    if (empty($message_content) && !$attachment) {
        $response['error'] = 'Message content or attachment is required.';
        echo json_encode($response);
        exit();
    }
    
    // Determine receiver role based on sender's role
    $receiver_role = ($user_role === 'farmer') ? 'expert' : (($user_role === 'expert') ? 'farmer' : 'admin');
    
    // Log the message sending attempt for debugging
    error_log("Sending message: sender_id=$user_id, sender_role=$user_role, receiver_id=$receiver_id, receiver_role=$receiver_role, message=$message_content");
    
    $send_result = sendChatMessage($user_id, $user_role, $receiver_id, $receiver_role, $message_content, $attachment);
    
    if ($send_result['success']) {
        $response['success'] = true;
        $response['message'] = 'Message sent successfully.';
        $response['message_id'] = $send_result['message_id'];
    } else {
        $response['error'] = $send_result['error'];
        error_log("Failed to send message: " . $send_result['error']);
    }
} else {
    $response['error'] = 'Invalid request method.';
}

echo json_encode($response);
?>