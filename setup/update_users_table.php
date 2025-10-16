<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Start session
startSecureSession();

// Check if user is admin
if (!isAdmin()) {
    header("Location: ../index.php");
    exit();
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo '<!DOCTYPE html>
<html>
<head>
    <title>Update Database Schema</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>Database Schema Update</h3>
            </div>
            <div class="card-body">';

// Add borrow_limit column to users table if it doesn't exist
$checkColumnSql = "SHOW COLUMNS FROM `users` LIKE 'borrow_limit'";
$result = $conn->query($checkColumnSql);

if ($result->num_rows == 0) {
    $alterSql = "ALTER TABLE `users` ADD COLUMN `borrow_limit` INT NULL AFTER `last_login`";
    
    if ($conn->query($alterSql) === TRUE) {
        echo '<div class="alert alert-success">Successfully added borrow_limit column to users table</div>';
    } else {
        echo '<div class="alert alert-danger">Error adding borrow_limit column: ' . $conn->error . '</div>';
    }
} else {
    echo '<div class="alert alert-info">The borrow_limit column already exists in the users table</div>';
}

echo '<a href="../admin/dashboard.php" class="btn btn-primary mt-3">Return to Dashboard</a>';
echo '</div></div></div></body></html>';

$conn->close();
?>
