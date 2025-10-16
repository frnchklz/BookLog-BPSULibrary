<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get total counts
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM books");
$stmt->execute();
$total_books = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$stmt->execute();
$total_users = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM borrows WHERE return_date IS NULL");
$stmt->execute();
$active_borrows = $stmt->get_result()->fetch_assoc()['total'];

$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM borrows WHERE due_date < ? AND return_date IS NULL");
$stmt->bind_param("s", $today);
$stmt->execute();
$overdue_borrows = $stmt->get_result()->fetch_assoc()['total'];

// Get recent activities
$recent_activities = null;
try {
    $stmt = $conn->prepare("
        SELECT b.*, u.name as user_name, bk.title as book_title, 
        CASE 
            WHEN b.return_date IS NULL AND b.due_date < CURDATE() THEN 'overdue'
            WHEN b.return_date IS NULL THEN 'borrowed'
            ELSE 'returned'
        END as status
        FROM borrows b
        JOIN users u ON b.user_id = u.id
        JOIN books bk ON b.book_id = bk.id
        ORDER BY 
            CASE 
                WHEN b.return_date IS NULL THEN b.borrow_date
                ELSE b.return_date
            END DESC
        LIMIT 10
    ");
    
    // Execute only if table exists
    if ($stmt) {
        $stmt->execute();
        $recent_activities = $stmt->get_result();
    }
} catch (Exception $e) {
    // Silently handle the error - table may not exist yet
    $recent_activities = null;
}

// Get most borrowed books
$popular_books = null;
try {
    $stmt = $conn->prepare("
        SELECT b.book_id, bk.title, bk.author, COUNT(*) as borrow_count
        FROM borrows b
        JOIN books bk ON b.book_id = bk.id
        GROUP BY b.book_id
        ORDER BY borrow_count DESC
        LIMIT 5
    ");
    
    // Execute only if table exists
    if ($stmt) {
        $stmt->execute();
        $popular_books = $stmt->get_result();
    }
} catch (Exception $e) {
    // Silently handle the error - table may not exist yet
    $popular_books = null;
}

// Count pending reset requests
$pending_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM password_resets WHERE status = 'pending' AND expires_at > NOW() AND used = 0");
    $stmt->execute();
    $pending_count = $stmt->get_result()->fetch_assoc()['count'];
} catch (Exception $e) {
    // Handle error silently
    $pending_count = 0;
}

// Include header
if (isHeadLibrarian()) {
    $pageTitle = "Head Librarian Dashboard";
} else {
    $pageTitle = "Admin Dashboard";
}
include('../includes/header.php');
?>

<div class="row">
    <div class="col-md-12">
        <?php if (isHeadLibrarian()): ?>
            <h2>Head Librarian Dashboard</h2>
            <p>Welcome to the head librarian panel. Monitor library activities and manage resources allowed for head librarians.</p>
        <?php else: ?>
            <h2>Admin Dashboard</h2>
            <p>Welcome to the administration panel. Monitor library activities and manage resources.</p>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Total Books</h5>
                <h2 class="display-4"><?= $total_books ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="books.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Registered Users</h5>
                <h2 class="display-4"><?= $total_users ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="users.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Active Borrows</h5>
                <h2 class="display-4"><?= $active_borrows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="transactions.php">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Overdue Books</h5>
                <h2 class="display-4"><?= $overdue_borrows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="transactions.php?filter=overdue">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-history"></i> Recent Activities
            </div>
            <div class="card-body">
                <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Book</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($activity['user_name']) ?></td>
                                        <td><?= htmlspecialchars($activity['book_title']) ?></td>
                                        <td>
                                            <?php if ($activity['status'] == 'borrowed'): ?>
                                                <span class="badge badge-info">Borrowed</span>
                                            <?php elseif ($activity['status'] == 'returned'): ?>
                                                <span class="badge badge-success">Returned</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($activity['status'] == 'returned'): ?>
                                                <?= formatDate($activity['return_date']) ?>
                                            <?php else: ?>
                                                <?= formatDate($activity['borrow_date']) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent activities found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
    <!-- Quick Actions (ilagay sa itaas) -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-cog"></i> Quick Actions
        </div>
        <div class="card-body">
            <div class="list-group">
                <a href="add_book.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-plus"></i> Add New Book
                </a>
                <a href="categories.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-tags"></i> Manage Categories
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-chart-line"></i> Generate Reports
                </a>

                <?php if (isAdmin()): ?>
                    <a href="settings.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-wrench"></i> System Settings
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Popular Books (ilagay sa ilalim) -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-chart-bar"></i> Popular Books
        </div>
        <div class="card-body">
            <?php if ($popular_books && $popular_books->num_rows > 0): ?>
                <div class="list-group">
                    <?php while ($book = $popular_books->fetch_assoc()): ?>
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?= htmlspecialchars($book['title']) ?></h6>
                                <span class="badge badge-primary"><?= $book['borrow_count'] ?></span>
                            </div>
                            <small class="text-muted">by <?= htmlspecialchars($book['author']) ?></small>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No borrowing data available yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
            </div>

<?php include('../includes/footer.php'); ?>
