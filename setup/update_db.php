<?php
require_once '../includes/config.php';

// Create connection to MySQL
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Update</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <style>
        body { background-color: #e9f1f7; padding: 20px; }
        .card { border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); margin-top: 20px; }
        .card-header { background-color: #d62839; color: white; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Database Structure Update</h2>
        </div>
        <div class="card-body">';

// Update the password_resets table with required columns
echo '<div class="alert alert-info mt-3">Running automated database schema update for password resets...</div>';

// Create the table if it doesn't exist
$sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `token` varchar(100) NOT NULL,
    `expires_at` datetime NOT NULL,
    `used` tinyint(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<div class='alert alert-success'>Verified password_resets base table</div>";
}

// Define the columns we want to ensure exist
$columns = [
    [
        'name' => 'identity_proof',
        'definition' => 'VARCHAR(255) DEFAULT NULL AFTER used'
    ],
    [
        'name' => 'status',
        'definition' => "ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER identity_proof"
    ],
    [
        'name' => 'rejection_reason',
        'definition' => 'TEXT DEFAULT NULL AFTER status'
    ],
    [
        'name' => 'created_at',
        'definition' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP()"
    ]
];

// Check and add each column
foreach ($columns as $column) {
    $columnExists = $conn->query("SHOW COLUMNS FROM password_resets LIKE '{$column['name']}'");
    
    if ($columnExists->num_rows == 0) {
        $sql = "ALTER TABLE password_resets ADD COLUMN {$column['name']} {$column['definition']}";
        
        if ($conn->query($sql)) {
            echo "<div class='alert alert-success'>Added missing column: {$column['name']}</div>";
        } else {
            echo "<div class='alert alert-danger'>Error adding column {$column['name']}: {$conn->error}</div>";
        }
    } else {
        echo "<div class='alert alert-info'>Column already exists: {$column['name']}</div>";
    }
}

// Try to add foreign key constraint if it doesn't exist
try {
    $sql = "ALTER TABLE password_resets
            ADD CONSTRAINT password_resets_ibfk_1 
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE";
    $conn->query($sql);
    echo "<div class='alert alert-success'>Added foreign key constraint</div>";
} catch (Exception $e) {
    // Constraint might already exist - that's okay
}

$conn->close();

echo '<div class="mt-4 text-center">
        <a href="../index.php" class="btn btn-primary">Go to Home Page</a>
      </div>';

echo '</div>
    </div>
</div>
</body>
</html>';
?>
