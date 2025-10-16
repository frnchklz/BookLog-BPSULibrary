<?php
// First connect to MySQL and check if the database exists
try {
    // First check if we can connect to MySQL server at all
    $mysql = @new mysqli('localhost', 'root', '');
    
    if ($mysql->connect_error) {
        throw new Exception("Cannot connect to MySQL server: " . $mysql->connect_error);
    }
    
    // Check if database exists
    $db_exists = $mysql->select_db('library0');
    
    if (!$db_exists) {
        // The database doesn't exist, redirect to setup
        $mysql->close();
        header("Location: setup/create_database.php");
        exit();
    }
    
    $mysql->close();
    
    // Database exists, now include our config and check tables
    require_once 'includes/config.php';
    require_once 'includes/db_check.php';
    
    // Check if tables exist and create them if needed
    $table_check = checkAndCreateTables();
    if (!empty($table_check)) {
        // If tables were created or there were issues, display the results
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Database Setup</title>
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
                        <h2>Database Setup</h2>
                    </div>
                    <div class="card-body">
                        <h4>Database Table Creation Results</h4>
                        <ul class="list-group mb-4>';
        
        foreach ($table_check as $result) {
            echo '<li class="list-group-item">' . $result . '</li>';
        }
        
        echo '</ul>
                        <div class="text-center">
                            <a href="index.php" class="btn btn-primary">Continue to Library</a>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
        exit();
    }
    
    // Ensure required directories exist
    $required_dirs = [
        'assets/css',
        'assets/js',
        'assets/covers'
    ];

    foreach ($required_dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
    
    // Redirect to login page
    header("Location: auth/login.php");
    exit();
} catch (Exception $e) {
    // If any exception occurs, redirect to database setup
    header("Location: setup/create_database.php");
    exit();
}
?>
