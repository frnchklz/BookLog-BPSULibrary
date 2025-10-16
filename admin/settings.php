<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdmin();

$db = Database::getInstance();
$conn = $db->getConnection();

// Define settings array with default values
$settings = [
    'site_name' => SITE_NAME,
    'admin_email' => ADMIN_EMAIL,
    'max_books_per_user' => MAX_BOOKS_PER_USER,
    'max_loan_days' => MAX_LOAN_DAYS,
    'items_per_page' => ITEMS_PER_PAGE
];

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $new_settings = [
        'site_name' => sanitizeInput($_POST['site_name']),
        'admin_email' => sanitizeInput($_POST['admin_email']),
        'max_books_per_user' => intval($_POST['max_books_per_user']),
        'max_loan_days' => intval($_POST['max_loan_days']),
        'items_per_page' => intval($_POST['items_per_page'])
    ];
    
    // Validation
    if (empty($new_settings['site_name'])) {
        $errors[] = "Site name cannot be empty";
    }
    
    if (empty($new_settings['admin_email']) || !filter_var($new_settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid admin email";
    }
    
    if ($new_settings['max_books_per_user'] < 1) {
        $errors[] = "Maximum books per user must be at least 1";
    }
    
    if ($new_settings['max_loan_days'] < 1) {
        $errors[] = "Maximum loan days must be at least 1";
    }
    
    if ($new_settings['items_per_page'] < 5) {
        $errors[] = "Items per page must be at least 5";
    }
    
    // If no errors, update config file
    if (empty($errors)) {
        try {
            $config_file = '../includes/config.php';
            $config_content = file_get_contents($config_file);
            
            // Replace values in config file
            $config_content = preg_replace('/define\([\'"]SITE_NAME[\'"],\s*[\'"].*?[\'"]\)/', 'define(\'SITE_NAME\', \'' . addslashes($new_settings['site_name']) . '\')', $config_content);
            $config_content = preg_replace('/define\([\'"]ADMIN_EMAIL[\'"],\s*[\'"].*?[\'"]\)/', 'define(\'ADMIN_EMAIL\', \'' . addslashes($new_settings['admin_email']) . '\')', $config_content);
            $config_content = preg_replace('/define\([\'"]MAX_BOOKS_PER_USER[\'"],\s*\d+\)/', 'define(\'MAX_BOOKS_PER_USER\', ' . $new_settings['max_books_per_user'] . ')', $config_content);
            $config_content = preg_replace('/define\([\'"]MAX_LOAN_DAYS[\'"],\s*\d+\)/', 'define(\'MAX_LOAN_DAYS\', ' . $new_settings['max_loan_days'] . ')', $config_content);
            $config_content = preg_replace('/define\([\'"]ITEMS_PER_PAGE[\'"],\s*\d+\)/', 'define(\'ITEMS_PER_PAGE\', ' . $new_settings['items_per_page'] . ')', $config_content);
            
            // Write updated content back to file
            file_put_contents($config_file, $config_content);
            
            $success = true;
            setAlert('success', 'Settings updated successfully. You may need to refresh the page to see all changes.');
            
            // Update current settings array
            $settings = $new_settings;
        } catch (Exception $e) {
            $errors[] = "Failed to update settings: " . $e->getMessage();
        }
    }
}

// Include header
$pageTitle = "System Settings";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>System Settings</h2>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Configuration</h4>
    </div>
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Settings updated successfully.
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">General Settings</h5>
                    
                    <div class="form-group">
                        <label for="site_name">Site Name</label>
                        <input type="text" name="site_name" id="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                        <small class="form-text text-muted">The name of your library system</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Admin Email</label>
                        <input type="email" name="admin_email" id="admin_email" class="form-control" value="<?= htmlspecialchars($settings['admin_email']) ?>" required>
                        <small class="form-text text-muted">Used for system notifications</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="items_per_page">Items Per Page</label>
                        <input type="number" name="items_per_page" id="items_per_page" class="form-control" value="<?= $settings['items_per_page'] ?>" min="5" required>
                        <small class="form-text text-muted">Number of items to show in paginated lists</small>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h5 class="mb-3">Borrowing Settings</h5>
                    
                    <div class="form-group">
                        <label for="max_books_per_user">Maximum Books Per User</label>
                        <input type="number" name="max_books_per_user" id="max_books_per_user" class="form-control" value="<?= $settings['max_books_per_user'] ?>" min="1" required>
                        <small class="form-text text-muted">Maximum number of books a user can borrow at once</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_loan_days">Maximum Loan Period (Days)</label>
                        <input type="number" name="max_loan_days" id="max_loan_days" class="form-control" value="<?= $settings['max_loan_days'] ?>" min="1" required>
                        <small class="form-text text-muted">Maximum number of days a book can be borrowed</small>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle"></i> Changing these settings will affect system behavior. Proceed with caution.
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
