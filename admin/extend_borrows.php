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

// Get user data
$stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'User not found');
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Get user's active borrows
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ? AND b.return_date IS NULL
    ORDER BY b.due_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrows = $stmt->get_result();

// Handle extension submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['extend_borrows'])) {
    // Get extension data
    $extension_days = isset($_POST['extension_days']) ? intval($_POST['extension_days']) : 0;
    $selected_borrows = isset($_POST['selected_borrows']) ? $_POST['selected_borrows'] : [];
    
    // Validate input
    if ($extension_days <= 0) {
        $error_message = 'Please specify a valid number of days to extend.';
    } elseif (empty($selected_borrows)) {
        $error_message = 'Please select at least one book to extend.';
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $success_count = 0;
            
            foreach ($selected_borrows as $borrow_id) {
                // Sanitize ID
                $borrow_id = intval($borrow_id);
                
                // Get current due date
                $stmt = $conn->prepare("SELECT due_date FROM borrows WHERE id = ? AND user_id = ? AND return_date IS NULL");
                $stmt->bind_param("ii", $borrow_id, $user_id);
                $stmt->execute();
                $due_date_result = $stmt->get_result();
                
                if ($due_date_result->num_rows > 0) {
                    $due_date = $due_date_result->fetch_assoc()['due_date'];
                    
                    // Calculate new due date
                    $new_due_date = date('Y-m-d', strtotime($due_date . ' + ' . $extension_days . ' days'));
                    
                    // Update the due date
                    $stmt = $conn->prepare("UPDATE borrows SET due_date = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_due_date, $borrow_id);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            if ($success_count > 0) {
                $success_message = "Successfully extended due date for {$success_count} book(s) by {$extension_days} days.";
                
                // Refresh borrows list
                $stmt = $conn->prepare("
                    SELECT b.*, bk.title, bk.author
                    FROM borrows b
                    JOIN books bk ON b.book_id = bk.id
                    WHERE b.user_id = ? AND b.return_date IS NULL
                    ORDER BY b.due_date ASC
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $borrows = $stmt->get_result();
            } else {
                $error_message = 'No books were extended. Please try again.';
            }
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Include header
$pageTitle = "Extend Borrows - " . $user['name'];
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Extend Book Due Dates</h2>
    <div>
        <a href="user_details.php?id=<?= $user_id ?>" class="btn btn-info mr-2">
            <i class="fas fa-user"></i> Back to User
        </a>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> All Users
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">User Information</h5>
    </div>
    <div class="card-body">
        <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        <p><strong>Active Borrows:</strong> <?= $borrows->num_rows ?></p>
    </div>
</div>

<?php if (!empty($success_message)): ?>
    <div class="alert alert-success"><?= $success_message ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger"><?= $error_message ?></div>
<?php endif; ?>

<?php if ($borrows->num_rows > 0): ?>
    <form method="post" action="">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Books Available for Extension</h5>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="extension_days">Extend Due Date By (days):</label>
                    <div class="input-group mb-3">
                        <input type="number" class="form-control" id="extension_days" name="extension_days" min="1" max="90" value="7" required>
                        <div class="input-group-append">
                            <span class="input-group-text">days</span>
                        </div>
                    </div>
                    <small class="form-text text-muted">Enter the number of days to extend the due date</small>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="checkAll">
                                        <label class="custom-control-label" for="checkAll"></label>
                                    </div>
                                </th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Borrow Date</th>
                                <th>Current Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($borrow = $borrows->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="borrow-<?= $borrow['id'] ?>" 
                                                name="selected_borrows[]" value="<?= $borrow['id'] ?>">
                                            <label class="custom-control-label" for="borrow-<?= $borrow['id'] ?>"></label>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($borrow['title']) ?></td>
                                    <td><?= htmlspecialchars($borrow['author']) ?></td>
                                    <td><?= formatDate($borrow['borrow_date']) ?></td>
                                    <td><?= formatDate($borrow['due_date']) ?></td>
                                    <td>
                                        <?php
                                        if (strtotime($borrow['due_date']) < time()) {
                                            echo '<span class="badge badge-danger">Overdue</span>';
                                        } else {
                                            // Calculate days remaining
                                            $days_remaining = ceil((strtotime($borrow['due_date']) - time()) / (60 * 60 * 24));
                                            if ($days_remaining <= 3) {
                                                echo '<span class="badge badge-warning">Due soon (' . $days_remaining . ' days)</span>';
                                            } else {
                                                echo '<span class="badge badge-success">Active (' . $days_remaining . ' days left)</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-center">
                <button type="submit" name="extend_borrows" class="btn btn-primary">
                    <i class="fas fa-calendar-plus"></i> Extend Selected Books
                </button>
            </div>
        </div>
    </form>
    
    <div class="card mt-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Extension Guidelines</h5>
        </div>
        <div class="card-body">
            <ul>
                <li>Extensions will be added to the current due date of each selected book.</li>
                <li>Overdue books can also be extended to give the user more time to return them.</li>
                <li>Be cautious with frequent extensions as they may affect book availability for other users.</li>
                <li>For books that are in high demand, consider shorter extension periods.</li>
                <li>The system will keep a record of all extensions granted to users.</li>
            </ul>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>This user does not have any active borrows that can be extended.</p>
    </div>
<?php endif; ?>

<script>
    // Check all / uncheck all functionality
    document.addEventListener('DOMContentLoaded', function() {
        const checkAll = document.getElementById('checkAll');
        if (checkAll) {
            checkAll.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_borrows[]"]');
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = checkAll.checked;
                });
            });
        }
    });
</script>

<?php include('../includes/footer.php'); ?>
