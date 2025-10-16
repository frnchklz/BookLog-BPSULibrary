<?php
// Direct database creation script
$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'library0';

// Create connection to MySQL server (without database)
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #e9f1f7; padding: 20px; }
        .card { border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 20px; }
        .card-header { background-color: #d62839; color: white; }
        .btn-primary { background-color: #d62839; border-color: #d62839; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Database Setup</h2>
        </div>
        <div class="card-body">';

// Step 1: Create database
echo "<h4>Creating Database...</h4>";
$sql = "CREATE DATABASE IF NOT EXISTS `$dbName`";
if ($conn->query($sql) === TRUE) {
    echo '<div class="alert alert-success">Database created successfully!</div>';
    
    // Now select the database
    $conn->select_db($dbName);
    
    echo '<div class="alert alert-info">You can now run the <a href="install.php">installation script</a> to set up tables and admin user.</div>';
    
    echo '<div class="text-center mt-4">
        <a href="install.php" class="btn btn-primary">Continue to Installation</a>
    </div>';
} else {
    echo '<div class="alert alert-danger">Error creating database: ' . $conn->error . '</div>';
}

$conn->close();

echo '</div>
    </div>
</div>
</body>
</html>';
?>
