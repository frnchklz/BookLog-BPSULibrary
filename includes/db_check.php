<?php
/**
 * Database verification utility
 * Checks if all required tables exist and creates them if missing
 */

// Required tables in the correct order of creation (respecting foreign keys)
$required_tables = [
    'users',
    'categories',
    'books',
    'borrows',
    'password_resets'
];

/**
 * Check if all required tables exist, create any missing ones
 * @return array Results of the check/creation process
 */
function checkAndCreateTables() {
    $results = [];
    $db = null;
    
    try {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($db->connect_error) {
            throw new Exception("Connection failed: " . $db->connect_error);
        }
        
        global $required_tables;
        $missing_tables = [];
        
        // Check which tables are missing
        foreach ($required_tables as $table) {
            $result = $db->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows == 0) {
                $missing_tables[] = $table;
                $results[] = "Table '$table' is missing and will be created.";
            }
        }
        
        // If any tables are missing, run the installation SQL
        if (!empty($missing_tables)) {
            // Read the SQL file
            $sql_file = file_get_contents(__DIR__ . '/../setup/database.sql');
            
            // Remove database creation commands
            $sql_file = str_replace("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`;", "", $sql_file);
            $sql_file = str_replace("USE `" . DB_NAME . "`;", "", $sql_file);
            
            // Split and execute statements
            $statements = explode(';', $sql_file);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    try {
                        if ($db->query($statement)) {
                            if (preg_match('/CREATE TABLE.*?`(.*?)`/i', $statement, $matches)) {
                                $results[] = "Created table: " . $matches[1];
                            }
                        }
                    } catch (Exception $e) {
                        $results[] = "Error: " . $e->getMessage();
                    }
                }
            }
            
            // Create admin user if it doesn't exist
            $admin_check = $db->query("SELECT id FROM users WHERE email = 'admin'");
            if ($admin_check->num_rows == 0) {
                $name = 'Admin';
                $email = 'admin';
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $role = 'admin';
                $status = 'active';
                $created_at = date('Y-m-d H:i:s');
                
                $sql = "INSERT INTO users (name, email, password, role, status, created_at) 
                        VALUES ('$name', '$email', '$password', '$role', '$status', '$created_at')";
                
                if ($db->query($sql)) {
                    $results[] = "Created admin user (admin/admin123)";
                } else {
                    $results[] = "Error creating admin user: " . $db->error;
                }
            }
        }
        
    } catch (Exception $e) {
        $results[] = "Database error: " . $e->getMessage();
    } finally {
        // Close connection if it was opened
        if ($db) {
            $db->close();
        }
    }
    
    return $results;
}
