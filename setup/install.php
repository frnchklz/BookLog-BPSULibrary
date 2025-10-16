<?php
// Database installation script
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
    <title>E-Library Installation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #e9f1f7; }
        .card { border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .card-header { background-color: #d62839; color: white; }
        .btn-primary { background-color: #d62839; border-color: #d62839; }
        .btn-primary:hover { background-color: #ba1f33; border-color: #ba1f33; }
    </style>
</head>
<body>
<div class="container mt-5 mb-5">
    <div class="card">
        <div class="card-header">
            <h2>E-Library System Installation</h2>
        </div>
        <div class="card-body">';

// Step 1: Create database if not exists
echo "<h4>Step 1: Creating Database</h4>";
$sql = "CREATE DATABASE IF NOT EXISTS `$dbName`";
if ($conn->query($sql) === TRUE) {
    echo '<div class="alert alert-success">Database created successfully or already exists.</div>';
} else {
    die('<div class="alert alert-danger">Error creating database: ' . $conn->error . '</div>');
}

// Select the database
$conn->select_db($dbName);

// Step 2: Create tables
echo "<h4>Step 2: Creating Tables</h4>";

// Read the SQL file and execute it
$sql_file = file_get_contents('database.sql');

// Remove the database creation part since we've already created it
$sql_file = str_replace("CREATE DATABASE IF NOT EXISTS `$dbName`;", "", $sql_file);
$sql_file = str_replace("USE `$dbName`;", "", $sql_file);

// Split SQL statements
$statements = explode(';', $sql_file);
foreach ($statements as $statement) {
    $statement = trim($statement);
    if (!empty($statement) && $statement != '') {
        if ($conn->query($statement) === TRUE) {
            if (strpos($statement, 'CREATE TABLE') !== false) {
                $table_name = '';
                if (preg_match('/CREATE TABLE.*?`(.*?)`/i', $statement, $matches)) {
                    $table_name = $matches[1];
                    echo '<div class="alert alert-success">Table "' . $table_name . '" created successfully.</div>';
                }
            }
        } else {
            echo '<div class="alert alert-warning">Error executing statement: ' . $conn->error . '<br>Statement: ' . htmlspecialchars($statement) . '</div>';
        }
    }
}

// Step 3: Check if admin user exists and create if not
echo "<h4>Step 3: Setting Up Admin User</h4>";

// First, drop existing admin user if exists to ensure fresh creation
$conn->query("DELETE FROM users WHERE email = 'admin'");

// Use hardcoded password for admin account - simple and reliable
$plainPassword = 'admin123';
$password = password_hash($plainPassword, PASSWORD_DEFAULT);

// Insert admin user with prepared statement
$sql = "INSERT INTO users (name, email, password, role, status, created_at) 
        VALUES ('Admin', 'admin', ?, 'admin', 'active', NOW())";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $password);

if ($stmt->execute()) {
    echo '<div class="alert alert-success">Admin user created successfully.</div>';
    
    // Verify admin user creation
    $admin_check = $conn->query("SELECT * FROM users WHERE email = 'admin'");
    if ($admin_check->num_rows == 1) {
        $admin_data = $admin_check->fetch_assoc();
        echo '<div class="alert alert-info">
            Admin account details:<br>
            Username: admin<br>
            Password: admin123
        </div>';
    }
} else {
    echo '<div class="alert alert-danger">Error creating admin user: ' . $stmt->error . '</div>';
}

// Step 4: Create required directories
echo "<h4>Step 4: Creating Directory Structure</h4>";
$dirs = [
    '../assets/css',
    '../assets/js',
    '../assets/covers'
];

foreach ($dirs as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo '<div class="alert alert-success">Directory created: ' . $dir . '</div>';
        } else {
            echo '<div class="alert alert-warning">Failed to create directory: ' . $dir . '</div>';
        }
    } else {
        echo '<div class="alert alert-info">Directory already exists: ' . $dir . '</div>';
    }
}

// Close connection
$conn->close();

echo '<div class="alert alert-success alert-permanent mt-4"><strong>Installation completed successfully!</strong><br>
You can now <a href="../index.php" class="alert-link">go to the login page</a> and sign in with:<br>
Username: admin<br>
Password: admin123</div>';

echo '</div>
        <div class="card-footer text-center">
            <a href="../index.php" class="btn btn-primary">Go to Login Page</a>
        </div>
    </div>
</div>
</body>
</html>';
?>
