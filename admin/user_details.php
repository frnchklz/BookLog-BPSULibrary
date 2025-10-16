<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid user ID');
    header("Location: users.php");
    exit();
}

$user_id = intval($_GET['id']);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'user'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'User not found');
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Get borrowing statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_borrows,
        SUM(CASE WHEN return_date IS NULL THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN return_date IS NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM borrows
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Get user's borrowing history
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author 
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ?
    ORDER BY 
        CASE 
            WHEN b.return_date IS NULL THEN 0
            ELSE 1
        END,
        b.borrow_date DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrows = $stmt->get_result();

// Handle status change if submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_status'])) {
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['active', 'inactive', 'suspended'])) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'user'");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'User status has been updated successfully');
            // Update the user array to reflect the change
            $user['status'] = $new_status;
        } else {
            setAlert('danger', 'Failed to update user status');
        }
    }
}

// Include header
$pageTitle = "User Details";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>User Details</h2>
    <a href="users.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Users</a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body text-center">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=random&color=fff&size=128" class="profile-img" alt="Profile">
                
                <h4 class="mt-3"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                
                <ul class="list-group mt-3 text-left">
                    <li class="list-group-item">
                        <strong>Account Status:</strong> 
                        <?php if ($user['status'] == 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php elseif ($user['status'] == 'inactive'): ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php elseif ($user['status'] == 'suspended'): ?>
                            <span class="badge badge-danger">Suspended</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Member Since:</strong> 
                        <?= formatDate($user['created_at']) ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Last Login:</strong> 
                        <?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?>
                    </li>
                </ul>
                
                <div class="mt-4">
                    <form method="post" action="" class="mb-2">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        
                        <?php if ($user['status'] != 'active'): ?>
                            <input type="hidden" name="new_status" value="active">
                            <button type="submit" name="change_status" class="btn btn-success btn-sm btn-block">
                                <i class="fas fa-check-circle"></i> Activate Account
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($user['status'] != 'inactive'): ?>
                            <input type="hidden" name="new_status" value="inactive">
                            <button type="submit" name="change_status" class="btn btn-secondary btn-sm btn-block mt-2">
                                <i class="fas fa-pause-circle"></i> Deactivate Account
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($user['status'] != 'suspended'): ?>
                            <input type="hidden" name="new_status" value="suspended">
                            <button type="submit" name="change_status" class="btn btn-danger btn-sm btn-block mt-2">
                                <i class="fas fa-ban"></i> Suspend Account
                            </button>
                        <?php endif; ?>
                    </form>
                    
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Borrowing Statistics</h5>
            </div>
            <div class="card-body">
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Books Borrowed
                        <span class="badge badge-primary badge-pill"><?= $stats['total_borrows'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Currently Borrowed
                        <span class="badge badge-info badge-pill"><?= $stats['active_borrows'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Overdue Books
                        <span class="badge badge-danger badge-pill"><?= $stats['overdue'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Available Slots
                        <span class="badge badge-success badge-pill"><?= MAX_BOOKS_PER_USER - $stats['active_borrows'] ?></span>
                    </li>
                </ul>
                
                <div class="text-center mt-3">
                    <a href="user_borrows.php?id=<?= $user_id ?>" class="btn btn-outline-primary btn-sm">View All Borrows</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if ($borrows->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Author</th>
                                    <th>Borrowed On</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($borrow = $borrows->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($borrow['title']) ?></td>
                                        <td><?= htmlspecialchars($borrow['author']) ?></td>
                                        <td><?= formatDate($borrow['borrow_date']) ?></td>
                                        <td><?= formatDate($borrow['due_date']) ?></td>
                                        <td>
                                            <?php if ($borrow['return_date']): ?>
                                                <span class="badge badge-success">Returned on <?= formatDate($borrow['return_date']) ?></span>
                                            <?php elseif (strtotime($borrow['due_date']) < time()): ?>
                                                <span class="badge badge-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Borrowed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">This user hasn't borrowed any books yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Notes & Actions</h5>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h6>Admin Notes</h6>
                    <form method="post" action="save_notes.php">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
                        <div class="form-group">
                            <textarea class="form-control" name="notes" rows="3" placeholder="Add notes about this user (only visible to admins)"><?= isset($user['admin_notes']) ? htmlspecialchars($user['admin_notes']) : '' ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Save Notes</button>
                    </form>
                </div>
                
                    <div class="col-md-6">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6>Extensions & Permissions</h6>
                                <a href="extend_borrows.php?user_id=<?= $user_id ?>" class="btn btn-info btn-sm btn-block mt-2">
                                    <i class="fas fa-calendar-plus"></i> Extend Due Dates
                                </a>
                                <a href="increase_limit.php?user_id=<?= $user_id ?>" class="btn btn-success btn-sm btn-block mt-2">
                                    <i class="fas fa-plus-circle"></i> Increase Borrow Limit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
