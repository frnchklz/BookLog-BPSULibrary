<?php
require_once '../includes/config.php';

// Start session management directly here
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection without requiring the database to exist
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'library0';

try {
    // First connect without specifying a database
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Create the database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbName`";
    if (!$conn->query($sql)) {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($dbName);
    
    // Create users table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `users` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `email` varchar(100) NOT NULL,
      `password` varchar(255) NOT NULL,
      `role` enum('admin','user') NOT NULL DEFAULT 'user',
      `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
      `created_at` datetime NOT NULL,
      `last_login` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($sql)) {
        throw new Exception("Error creating users table: " . $conn->error);
    }
    
    // Generate a token to prevent CSRF attacks if not already set
    if (!isset($_SESSION['reset_token'])) {
        $_SESSION['reset_token'] = bin2hex(random_bytes(32));
    }
    
    // Process the reset if confirmed
    if (isset($_GET['confirm']) && $_GET['confirm'] == 1 && isset($_GET['token']) && $_GET['token'] == $_SESSION['reset_token']) {
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
            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Administrator account has been reset successfully. You can now login with username "admin" and password "admin123".'
            ];
        } else {
            $_SESSION['alert'] = [
                'type' => 'danger',
                'message' => 'Failed to reset administrator account. Error: ' . $stmt->error
            ];
        }
        
        // Clear the token
        unset($_SESSION['reset_token']);
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    $error_message = "Database operation failed: " . $e->getMessage();
}

// Basic HTML header
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #e9f1f7; }
        .card { border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 50px; }
        .card-header { background-color: #d62839; color: white; }
        .btn-danger { background-color: #d62839; border-color: #d62839; }
        .btn-danger:hover { background-color: #ba1f33; border-color: #ba1f33; }
    </style>
</head>
<body>
<div class="container">';

// Display any errors
if (isset($error_message)) {
    echo '<div class="alert alert-danger mt-4">
        <h4>Error:</h4>
        <p>' . $error_message . '</p>
        <p>Please make sure your database server is running and that you have appropriate permissions.</p>
        <p><a href="../setup/create_database.php" class="btn btn-primary">Run Database Setup</a></p>
    </div>';
} else {
    // Normal page content
    echo '<div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Reset Administrator Login</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Warning!</h5>
                        <p>You are about to reset the administrator account to its default settings. This will:</p>
                        <ul>
                            <li>Reset the admin username to: <strong>admin</strong></li>
                            <li>Reset the admin password to: <strong>admin123</strong></li>
                            <li>Any custom settings for the admin account will be lost</li>
                        </ul>
                        <p>This operation cannot be undone.</p>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="reset_admin.php?confirm=1&token=' . $_SESSION['reset_token'] . '" class="btn btn-danger">Yes, Reset Admin Login</a>
                        <a href="login.php" class="btn btn-secondary ml-2">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

echo '</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
?>
