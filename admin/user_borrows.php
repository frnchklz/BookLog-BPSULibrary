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
$stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'User not found');
    header("Location: users.php");
    exit();
}

$user = $result->fetch_assoc();

// Initialize filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// Build query
$query = "
    SELECT b.*, bk.title, bk.author 
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ?
";
$count_query = "SELECT COUNT(*) as total FROM borrows b WHERE b.user_id = ?";

$params = [$user_id];
$types = "i";

if ($status == 'active') {
    $query .= " AND b.return_date IS NULL";
    $count_query .= " AND b.return_date IS NULL";
} elseif ($status == 'returned') {
    $query .= " AND b.return_date IS NOT NULL";
    $count_query .= " AND b.return_date IS NOT NULL";
} elseif ($status == 'overdue') {
    $today = date('Y-m-d');
    $query .= " AND b.due_date < ? AND b.return_date IS NULL";
    $count_query .= " AND b.due_date < ? AND b.return_date IS NULL";
    $params[] = $today;
    $types .= "s";
}

if (!empty($start_date)) {
    $query .= " AND b.borrow_date >= ?";
    $count_query .= " AND b.borrow_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if (!empty($end_date)) {
    $query .= " AND b.borrow_date <= ?";
    $count_query .= " AND b.borrow_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

// Execute count query
$stmt = $conn->prepare($count_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);

// Add sorting and pagination to main query
$query .= " ORDER BY 
    CASE 
        WHEN b.return_date IS NULL AND b.due_date < CURDATE() THEN 1
        WHEN b.return_date IS NULL THEN 2
        ELSE 3
    END,
    b.borrow_date DESC 
    LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$borrows = $stmt->get_result();

// Include header
$pageTitle = "User Borrows - " . $user['name'];
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Borrowing History for <?= htmlspecialchars($user['name']) ?></h2>
    <div>
        <a href="user_details.php?id=<?= $user_id ?>" class="btn btn-info mr-2">
            <i class="fas fa-user"></i> User Details
        </a>
        <a href="users.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">User Information</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong> <?= htmlspecialchars($user['name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
            </div>
            <div class="col-md-6">
                <?php
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
                ?>
                <p><strong>Total Books Borrowed:</strong> <?= $stats['total_borrows'] ?></p>
                <p><strong>Currently Borrowed:</strong> <?= $stats['active_borrows'] ?></p>
                <p><strong>Overdue Books:</strong> <?= $stats['overdue'] ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <input type="hidden" name="id" value="<?= $user_id ?>">
            
            <div class="col-md-3 form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Transactions</option>
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active Borrows</option>
                    <option value="returned" <?= $status == 'returned' ? 'selected' : '' ?>>Returned Books</option>
                    <option value="overdue" <?= $status == 'overdue' ? 'selected' : '' ?>>Overdue Books</option>
                </select>
            </div>
            <div class="col-md-3 form-group">
                <label for="start_date">From Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3 form-group">
                <label for="end_date">To Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
            </div>
            <div class="col-md-3 form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
                <?php if (!empty($status) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="user_borrows.php?id=<?= $user_id ?>" class="btn btn-secondary btn-block mt-2">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($borrows->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
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
                                        <?= formatDate($borrow['return_date']) ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Not returned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($borrow['return_date']) {
                                        echo '<span class="badge badge-success">Returned</span>';
                                    } elseif (strtotime($borrow['due_date']) < time()) {
                                        echo '<span class="badge badge-danger">Overdue</span>';
                                    } else {
                                        echo '<span class="badge badge-info">Active</span>';
                                    }
                                    ?>
                                </td>
                                
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?= $user_id ?>&status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page-1 ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?id=<?= $user_id ?>&status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?id=<?= $user_id ?>&status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page+1 ?>">Next</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <p>No borrowing records found matching your criteria.</p>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
