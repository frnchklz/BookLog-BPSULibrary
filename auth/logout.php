<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Start the session
startSecureSession();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
setAlert('success', 'You have been successfully logged out.');
header("Location: login.php");
exit();
?>
