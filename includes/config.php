<?php
// Database configuration - ensure this matches your actual database name
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library0');

// Application settings
define('SITE_NAME', 'BookLog:Bataan Peninsula State University Library');
define('ADMIN_EMAIL', 'booklogbpsulibrary@gmail.com');
define('ITEMS_PER_PAGE', 6);
define('MAX_LOAN_DAYS', 5);
define('MAX_BOOKS_PER_USER', 5);

// Path settings - fix BASE_URL to ensure it ends with a slash
$base_url = 'http://localhost/library0/';
if(substr($base_url, -1) != '/') {
    $base_url .= '/';
}
define('BASE_URL', $base_url);
define('BOOK_COVERS_DIR', 'assets/covers/');

// SMTP configuration for sending emails via PHPMailer
// Replace these with your SMTP server credentials
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'booklogbpsulibrary@gmail.com');
define('SMTP_PASS', 'yagy pjtw xsjh dtiq'); // your Gmail App Password
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // use TLS for Gmail
define('SMTP_FROM_EMAIL', ADMIN_EMAIL);
define('SMTP_FROM_NAME', SITE_NAME);
?>
