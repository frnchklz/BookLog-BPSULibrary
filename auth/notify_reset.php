<?php
/**
 * Password Reset Notification Utility
 * 
 * This file is used to send notifications to users about the status of their
 * password reset requests.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Ensure only admins can run this directly
if (php_sapi_name() !== 'cli') {
    startSecureSession();
    
    if (!isAdmin()) {
        header("Location: ../auth/login.php");
        exit();
    }
}

/**
 * Send notification about password reset request status
 * 
 * @param int $request_id The ID of the password reset request
 * @param string $status The new status (approved/rejected)
 * @param string $rejection_reason Optional reason for rejection
 * @return bool Success status
 */
function notifyPasswordResetStatus($request_id, $status, $rejection_reason = null) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get request details
    $stmt = $conn->prepare("
        SELECT pr.token, u.name, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        return false;
    }
    
    $request = $result->fetch_assoc();
    
    // Prepare email content based on status
    if ($status == 'approved') {
        $subject = "Password Reset Request Approved";
        $reset_link = BASE_URL . 'auth/reset-password.php?token=' . $request['token'];
        
        $message = "Hello " . $request['name'] . ",\n\n";
        $message .= "Your password reset request has been approved. You can now reset your password using the link below:\n\n";
        $message .= $reset_link . "\n\n";
        $message .= "This link will expire in 24 hours. If you did not request this password reset, please contact us immediately.\n\n";
        $message .= "Regards,\n";
        $message .= SITE_NAME . " Team";
    } 
    else if ($status == 'rejected') {
        $subject = "Password Reset Request Rejected";
        
        $message = "Hello " . $request['name'] . ",\n\n";
        $message .= "We regret to inform you that your password reset request has been rejected";
        
        if ($rejection_reason) {
            $message .= " for the following reason:\n\n";
            $message .= $rejection_reason . "\n\n";
        } else {
            $message .= ".\n\n";
        }
        
        $message .= "If you believe this is an error or need further assistance, please contact our support team.\n";
        $message .= "You may also submit a new request with proper identification.\n\n";
        $message .= "Regards,\n";
        $message .= SITE_NAME . " Team";
    }
    else {
        return false;
    }
    
    // Send the email
    return sendEmail($request['email'], $subject, $message);
}

// If this script is executed directly with parameters, process the notification
if (isset($_GET['request_id']) && isset($_GET['status'])) {
    $request_id = intval($_GET['request_id']);
    $status = sanitizeInput($_GET['status']);
    $rejection_reason = isset($_GET['reason']) ? sanitizeInput($_GET['reason']) : null;
    
    if (notifyPasswordResetStatus($request_id, $status, $rejection_reason)) {
        echo "Notification sent successfully.";
    } else {
        echo "Failed to send notification.";
    }
}
?>
