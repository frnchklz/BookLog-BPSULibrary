<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if user ID is provided
if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
    setAlert('danger', 'Invalid user ID');
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['user_id']);

// First, check if borrow_limit column exists
$column_exists = false;
$result = $conn->query("SHOW COLUMNS FROM users LIKE 'borrow_limit'");
if ($result->num_rows > 0) {
    $column_exists = true;
}

// If not, create it
if (!$column_exists) {
    try {
        $conn->query("ALTER TABLE users ADD COLUMN borrow_limit INT NULL AFTER last_login");
        setAlert('info', 'The borrow limit feature has been enabled. Database updated successfully.');
        $column_exists = true;
    } catch (Exception $e) {
        setAlert('danger', 'Failed to update database schema. Please contact the administrator.');
    }
}

// Get user data (with or without borrow_limit depending on if column exists)
if ($column_exists) {
    $stmt = $conn->prepare("SELECT id, name, email, borrow_limit FROM users WHERE id = ?");
} else {
    $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'User not found');
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Check if the user already has a custom borrow limit
$custom_limit = null;
if ($column_exists && isset($user['borrow_limit']) && $user['borrow_limit'] > 0) {
    $custom_limit = $user['borrow_limit'];
}

$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify column exists before processing
    if (!$column_exists) {
        $errors[] = "The borrow limit feature is not available. The database needs to be updated.";
    } else {
        $new_limit = isset($_POST['borrow_limit']) ? intval($_POST['borrow_limit']) : 0;
        $reset_to_default = isset($_POST['reset_to_default']) && $_POST['reset_to_default'] == '1';
        
        if ($reset_to_default) {
            // Reset to system default
            $stmt = $conn->prepare("UPDATE users SET borrow_limit = NULL WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                setAlert('success', 'User borrowing limit has been reset to system default (' . MAX_BOOKS_PER_USER . ')');
                header("Location: user_details.php?id=" . $user_id);
                exit();
            } else {
                $errors[] = "Failed to reset borrowing limit";
            }
        } else {
            // Validate the new limit
            if ($new_limit <= 0) {
                $errors[] = "Borrowing limit must be greater than zero";
            } elseif ($new_limit > 20) {
                $errors[] = "Borrowing limit cannot exceed 20 books for security reasons";
            } else {
                // Update the user's borrowing limit
                $stmt = $conn->prepare("UPDATE users SET borrow_limit = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_limit, $user_id);
                
                if ($stmt->execute()) {
                    setAlert('success', 'User borrowing limit has been updated successfully');
                    header("Location: user_details.php?id=" . $user_id);
                    exit();
                } else {
                    $errors[] = "Failed to update borrowing limit";
                }
            }
        }
    }
}

// Include header
$pageTitle = "Increase Borrowing Limit";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Increase Borrowing Limit</h2>
    <a href="user_details.php?id=<?= $user_id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to User Details</a>
</div>

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Adjust Borrowing Limit for <?= htmlspecialchars($user['name']) ?></h5>
            </div>
            <div class="card-body">
                <?php if (!$column_exists): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Database Update Required</h5>
                        <p>The borrow limit feature requires a database update. Please run the update script:</p>
                        <a href="../setup/update_users_table.php" class="btn btn-primary">Run Database Update</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <p><strong>User:</strong> <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['email']) ?>)</p>
                        <p><strong>Current Borrowing Limit:</strong> 
                            <?php if ($custom_limit): ?>
                                <?= $custom_limit ?> books (Custom limit)
                            <?php else: ?>
                                <?= MAX_BOOKS_PER_USER ?> books (System default)
                            <?php endif; ?>
                        </p>
                        
                        <?php 
                        // Get user's active borrows count
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrows WHERE user_id = ? AND return_date IS NULL");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $active_borrows = $stmt->get_result()->fetch_assoc()['count'];
                        ?>
                        
                        <p><strong>Currently Borrowed:</strong> <?= $active_borrows ?> books</p>
                    </div>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="borrow_limit">New Borrowing Limit</label>
                            <input type="number" class="form-control" id="borrow_limit" name="borrow_limit" 
                                value="<?= $custom_limit ? $custom_limit : MAX_BOOKS_PER_USER ?>" min="1" max="20" required>
                            <small class="form-text text-muted">The maximum number of books this user can borrow simultaneously (1-20).</small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="reset_to_default" name="reset_to_default" value="1">
                            <label class="form-check-label" for="reset_to_default">Reset to system default (<?= MAX_BOOKS_PER_USER ?> books)</label>
                        </div>
                        
                        <div class="alert alert-warning">
                            <p><i class="fas fa-exclamation-triangle"></i> <strong>Important Notes:</strong></p>
                            <ul>
                                <li>Increasing the borrowing limit should be done with caution.</li>
                                <li>Consider the user's borrowing history and reliability before increasing their limit.</li>
                                <li>This override will apply only to this specific user.</li>
                                <li>If the user already has books borrowed, they will be able to borrow additional books up to their new limit.</li>
                            </ul>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary">Update Borrowing Limit</button>
                            <a href="user_details.php?id=<?= $user_id ?>" class="btn btn-secondary ml-2">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Disable the borrowing limit field when "Reset to default" is checked
    document.addEventListener('DOMContentLoaded', function() {
        const resetCheckbox = document.getElementById('reset_to_default');
        if (resetCheckbox) {
            resetCheckbox.addEventListener('change', function() {
                document.getElementById('borrow_limit').disabled = this.checked;
            });
        }
    });
</script>

<?php include('../includes/footer.php'); ?>
