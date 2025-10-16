<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/image_urls.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    startSecureSession();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <!-- Load Bootstrap from CDN to ensure it exists -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- Load our custom CSS with full path -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        /* Small navbar logo sizing */
        .navbar-logo {
            height: 40px;
            width: auto;
            margin-right: 8px;
            border-radius: 4px;
            object-fit: cover;
        }
        @media (max-width: 576px) {
            .navbar-logo { height: 32px; }
        }
        /* Make navbar content evenly spaced left–right */
.navbar .container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  max-width: 95%;   /* makes the navbar slightly narrower for even spacing */
  margin: 0 auto;   /* centers the whole navbar content */
}

/* Adjust the brand (logo + title) positioning */
.navbar-brand {
  display: flex;
  align-items: center;
  gap: 8px;          /* spacing between logo and text */
  margin-left: 10px; /* slight push from the left edge */
}

/* Resize logo a bit for clean alignment */
.navbar-logo {
  height: 35px;
  width: auto;
}

/* Keep menu items in one straight line */
.navbar-nav {
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: nowrap;
}

.navbar-nav .nav-link {
  white-space: nowrap !important;
}

    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?= BASE_URL ?>">
                <?php if (defined('BPSU_LOGO_URL') && BPSU_LOGO_URL): ?>
                    <img src="<?= BPSU_LOGO_URL ?>" alt="<?= SITE_NAME ?> logo" class="navbar-logo" />
                <?php else: ?>
                    <img src="<?= BASE_URL ?>image/1.jpg" alt="<?= SITE_NAME ?> logo" class="navbar-logo" />
                <?php endif; ?>
                <?= SITE_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdminOrHeadLibrarian()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/books.php">Manage Books</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/users.php">Manage Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/transactions.php">Transactions</a></li>
                    <?php elseif (isLibrarian()): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/dashboard.php">Librarian Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>user/search.php">Search Books</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/transactions.php">Books Borrowed</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/transactions.php?filter=overdue">Book Overdue</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>user/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>user/search.php">Search Books</a></li>
                        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>user/history.php">My History</a></li>
                    <?php endif; ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown">
                            <?= $_SESSION['name'] ?>
                        </a>
                        <div class="dropdown-menu">
                            <?php if (isAdminOrHeadLibrarian()): ?>
                                <a class="dropdown-item" href="<?= BASE_URL ?>admin/profile.php">Profile</a>
                                <?php if (isAdmin()): ?>
                                    <a class="dropdown-item" href="<?= BASE_URL ?>admin/settings.php">Settings</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a class="dropdown-item" href="<?= BASE_URL ?>user/profile.php">Profile</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="<?= BASE_URL ?>auth/logout.php">Logout</a>
                        </div>
                    </li>
                <?php else: ?>
    
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>catalog.php"><i class="fas fa-book"></i> Catalog</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>auth/login.php">Login</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>auth/register.php">Register</a></li>
                <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
        <?php displayAlert(); ?>
