<?php
require_once 'db.php';

// Session management
function startSecureSession() {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    
    $session_name = 'elibrary_session';
    $secure = false; // Set to true if using HTTPS
    $httponly = true;
    
    session_name($session_name);
    session_start();
    session_regenerate_id(true);
}

// User authentication
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isHeadLibrarian() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'head_librarian';
}

function isAdminOrHeadLibrarian() {
    return isAdmin() || isHeadLibrarian();
}

function requireAdminOrHeadLibrarian() {
    requireLogin();
    if (!isAdminOrHeadLibrarian()) {
        header("Location: " . BASE_URL . "user/dashboard.php");
        exit();
    }
}

function isLibrarian() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'librarian';
}

function isAdminHeadOrLibrarian() {
    return isAdmin() || isHeadLibrarian() || isLibrarian();
}

function requireLibrarianOrHigher() {
    requireLogin();
    if (!isAdminHeadOrLibrarian()) {
        header("Location: " . BASE_URL . "user/dashboard.php");
        exit();
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: " . BASE_URL . "user/dashboard.php");
        exit();
    }
}

// Sanitization
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format dates
function formatDate($date) {
    $dt = new DateTime($date);
    return $dt->format('F j, Y');
}

// Calculate due date
function calculateDueDate($issue_date = null) {
    if ($issue_date === null) {
        $issue_date = date('Y-m-d');
    }
    
    $date = new DateTime($issue_date);
    $date->add(new DateInterval('P' . MAX_LOAN_DAYS . 'D'));
    return $date->format('Y-m-d');
}

// Notifications
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $type = $_SESSION['alert']['type'];
        $message = $_SESSION['alert']['message'];
        
        echo "<div class='alert alert-$type'>$message</div>";
        
        unset($_SESSION['alert']);
    }
}

// Book availability check
function isBookAvailable($book_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT quantity, borrowed FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
        return ($book['quantity'] > $book['borrowed']);
    }
    
    return false;
}

// Get user's active borrows count
function getUserActiveBorrowsCount($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrows WHERE user_id = ? AND return_date IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get user's maximum borrowing limit
function getUserBorrowLimit($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // First check if the column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'borrow_limit'");
    if ($result->num_rows == 0) {
        // Column doesn't exist, return system default
        return MAX_BOOKS_PER_USER;
    }
    
    // Column exists, check user's limit
    $stmt = $conn->prepare("SELECT borrow_limit FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // If a custom limit is set, use it, otherwise use the system default
        return ($user['borrow_limit'] > 0) ? $user['borrow_limit'] : MAX_BOOKS_PER_USER;
    }
    
    // Fallback to system default if user not found
    return MAX_BOOKS_PER_USER;
}

// Check if user can borrow more books
function canUserBorrowMore($user_id) {
    return getUserActiveBorrowsCount($user_id) < getUserBorrowLimit($user_id);
}

// Get overdue books for a user
function getUserOverdueBooks($user_id) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT b.*, bk.title 
        FROM borrows b
        JOIN books bk ON b.book_id = bk.id
        WHERE b.user_id = ? AND b.due_date < ? AND b.return_date IS NULL
    ");
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    
    return $stmt->get_result();
}

/**
 * Send an email
 * In a production environment, this would use an actual mail service
 * This is a placeholder function that logs emails instead of sending them
 * 
 * @param string $to Email recipient
 * @param string $subject Email subject
 * @param string $message Email message (can be HTML)
 * @param string $from From email address (optional)
 * @return boolean Success status
 */
/**
 * Send an email using PHPMailer if available, otherwise fall back to logging.
 * Supports HTML messages when $isHtml is true.
 *
 * To enable real SMTP sending, install PHPMailer via Composer in the project root:
 *   composer require phpmailer/phpmailer
 *
 * @param string $to
 * @param string $subject
 * @param string $message
 * @param string|null $from
 * @param bool $isHtml
 * @return bool
 */
function sendEmail($to, $subject, $message, $from = null, $isHtml = false) {
    if ($from === null) {
        $from = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : ADMIN_EMAIL;
    }

    $errorInfo = '';

    // Try to use PHPMailer if it's installed via Composer
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            // Allow empty SMTP_SECURE constant or use as string
            if (defined('SMTP_SECURE') && SMTP_SECURE) {
                $mail->SMTPSecure = SMTP_SECURE;
            }
            $mail->Port = SMTP_PORT;

            // Recipients
            $mail->setFrom($from, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME);
            $mail->addAddress($to);

            // Content
            $mail->Subject = $subject;
            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $message;
                $mail->AltBody = strip_tags($message);
            } else {
                $mail->Body = $message;
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Capture PHPMailer error and fall through to logging fallback
            $errorInfo = 'PHPMailer error: ' . $e->getMessage();
        }
    } else {
        $errorInfo = 'PHPMailer not installed';
    }

    // Fallback: log the email to a file (keeps previous behavior)
    $log_file = __DIR__ . '/../logs/email_log.txt';
    $log_dir = dirname($log_file);

    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    $log_message = "------------------------------------\n";
    $log_message .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $log_message .= "To: $to\n";
    $log_message .= "From: $from\n";
    $log_message .= "Subject: $subject\n";
    $log_message .= "Message:\n$message\n";
    if (!empty($errorInfo)) {
        $log_message .= "ErrorInfo: $errorInfo\n";
    }
    $log_message .= "------------------------------------\n\n";

    $written = file_put_contents($log_file, $log_message, FILE_APPEND);
    return ($written !== false);
}
?>
