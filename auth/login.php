<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/image_urls.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin() || isHeadLibrarian()) {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit();
}

// Generate a token to prevent CSRF attacks if not already set
if (!isset($_SESSION['reset_token'])) {
    $_SESSION['reset_token'] = bin2hex(random_bytes(32));
}

// Handle admin reset if requested
if (isset($_GET['reset_admin']) && $_GET['reset_admin'] == 1 && isset($_GET['token']) && $_GET['token'] == $_SESSION['reset_token']) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Delete existing admin account (where email is 'admin')
    $stmt = $conn->prepare("DELETE FROM users WHERE email = 'admin'");
    $stmt->execute();
    
    // Create new admin account with default credentials
    $name = 'Admin';
    $email = 'admin';
    $plainPassword = 'admin123';
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $role = 'admin';
    $status = 'active';
    $created_at = date('Y-m-d H:i:s');
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $hashedPassword, $role, $status, $created_at);
    
    if ($stmt->execute()) {
        setAlert('success', 'Administrator account has been reset successfully. You can now login with username "admin" and password "admin123".');
    } else {
        setAlert('danger', 'Failed to reset administrator account: ' . $stmt->error);
    }
    
    // Clear the token
    unset($_SESSION['reset_token']);
    
    // Redirect to login page
    header('Location: login.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password";
        } elseif (!preg_match('/^[A-Za-z0-9._%+-]+@bpsu\.edu\.ph$/', $email)) {
            $error = "Only BPSU email addresses (@bpsu.edu.ph) are allowed.";
        } else {
        // Direct database connection for troubleshooting
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            $error = "Database connection failed: " . $conn->connect_error;
        } else {
            // Get user data
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Simple password verification
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->bind_param("i", $user['id']);
                    $update->execute();
                    
                    // Redirect based on role (admin & head_librarian go to admin dashboard)
                    if ($user['role'] == 'admin' || $user['role'] == 'head_librarian') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: ../user/dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Invalid email or password";
                }
            } else {
                $error = "Invalid email or password";
            }
            
            $conn->close();
        }
    }
}

// Include header
$pageTitle = "Login";
include('../includes/header.php');
?>

<?php if (defined('AUTH_BACKGROUND_URL') && AUTH_BACKGROUND_URL): ?>
    <style>
        body {
            background-image: url('<?= AUTH_BACKGROUND_URL ?>');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }
    </style>
<?php endif; ?>

<div class="auth-page">
    <div class="auth-container container">
        <div class="auth-header">
            <h2 class="auth-title">BATAAN PENINSULA STATE UNIVERSITY LIBRARY</h2>
            <p class="auth-subtitle">WELCOME TO THE BPSU LIBRARY!</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card auth-card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Login to BPSU Library</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="form-group">
                                <label for="email">Username/Email</label>
                                <input type="text" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>
                        <div class="mt-3 text-center">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
                            <p> <a href="reset-password.php?via=email">Forgot Password</a></p>
                            
                            
                            <div class="thank-you-message">
                                <p><strong>Thank you for using the BPSU Library!</strong></p>
                            </div>
                            
                            <hr>
                            
                            
                           
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        
    </div>
</div>

<?php include('../includes/footer.php'); ?>
