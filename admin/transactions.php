<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireLibrarianOrHigher();

$db = Database::getInstance();
$conn = $db->getConnection();
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// Check if borrows.received column exists
$hasReceivedCol = false;
$col_check = $conn->query("SHOW COLUMNS FROM borrows LIKE 'received'");
if ($col_check && $col_check->num_rows > 0) {
    $hasReceivedCol = true;
}

// Build query
$receivedSelect = $hasReceivedCol ? "b.received AS received, " : '';

$query = "
    SELECT {$receivedSelect}b.*, 
           u.name AS user_name, 
           bk.title AS book_title,
           bk.author AS book_author
    FROM borrows b
    JOIN users u ON b.user_id = u.id
    JOIN books bk ON b.book_id = bk.id
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) AS total FROM borrows b WHERE 1=1";

$params = [];
$types = "";

// Apply filters
if ($filter == 'overdue') {
    $today = date('Y-m-d');
    $query .= " AND b.due_date < ? AND b.return_date IS NULL";
    $count_query .= " AND b.due_date < ? AND b.return_date IS NULL";
    $params[] = $today;
    $types .= "s";
} elseif ($filter == 'active') {
    $query .= " AND b.return_date IS NULL";
    $count_query .= " AND b.return_date IS NULL";
} elseif ($filter == 'returned') {
    $query .= " AND b.return_date IS NOT NULL";
    $count_query .= " AND b.return_date IS NOT NULL";
}

if ($user_id > 0) {
    $query .= " AND b.user_id = ?";
    $count_query .= " AND b.user_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

if ($book_id > 0) {
    $query .= " AND b.book_id = ?";
    $count_query .= " AND b.book_id = ?";
    $params[] = $book_id;
    $types .= "i";
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
if (!empty($params)) {
    // Fix binding parameters
    $stmt->bind_param($types, ...$params);
}
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
if (!empty($params)) {
    // Fix binding parameters
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result();

// Handle mark-as-received action (librarian confirms the student received the book)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_received']) && isset($_POST['borrow_id'])) {
    $borrow_id = intval($_POST['borrow_id']);
    // Check if 'received' column exists
    $col_check = $conn->query("SHOW COLUMNS FROM borrows LIKE 'received'");
    if ($col_check && $col_check->num_rows > 0) {
        $u = $conn->prepare("UPDATE borrows SET received = 1 WHERE id = ?");
        $u->bind_param('i', $borrow_id);
        if ($u->execute()) {
            setAlert('success', 'Marked as received.');
        } else {
            setAlert('danger', 'Failed to mark as received: ' . $u->error);
        }
    } else {
        setAlert('warning', "Database is missing 'received' column on borrows table. Run: ALTER TABLE borrows ADD COLUMN received TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Refresh to show changes
    header('Location: transactions.php');
    exit();
}

// Include header
$pageTitle = "Manage Transactions";
include('../includes/header.php');
?>

<h2>Manage Transactions</h2>
<p>View and manage borrowing transactions.</p>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <div class="col-md-3 form-group">
                <label for="filter">Status</label>
                <select name="filter" id="filter" class="form-control">
                    <option value="">All Transactions</option>
                    <option value="active" <?= $filter == 'active' ? 'selected' : '' ?>>Active Borrows</option>
                    <option value="returned" <?= $filter == 'returned' ? 'selected' : '' ?>>Returned Books</option>
                    <option value="overdue" <?= $filter == 'overdue' ? 'selected' : '' ?>>Overdue Books</option>
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
                <?php if (!empty($filter) || !empty($start_date) || !empty($end_date) || $user_id > 0 || $book_id > 0): ?>
                    <a href="transactions.php" class="btn btn-secondary btn-block mt-2">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($transactions->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <?php if ($hasReceivedCol): ?>
                                <th>Received</th>
                                <th>Action</th>
                            <?php else: ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($transaction = $transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?= $transaction['id'] ?></td>
                                <td><?= htmlspecialchars($transaction['user_name']) ?></td>
                                <td><?= htmlspecialchars($transaction['book_title']) ?></td>
                                <td><?= formatDate($transaction['borrow_date']) ?></td>
                                <td><?= formatDate($transaction['due_date']) ?></td>
                                <td>
                                    <?php if ($transaction['return_date']): ?>
                                        <?= formatDate($transaction['return_date']) ?>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Not returned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($transaction['return_date']) {
                                        echo '<span class="badge badge-success">Returned</span>';
                                    } elseif (strtotime($transaction['due_date']) < time()) {
                                        echo '<span class="badge badge-danger">Overdue</span>';
                                    } else {
                                        echo '<span class="badge badge-info">Active</span>';
                                    }
                                    ?>
                                </td>
                                
                                    <?php if ($hasReceivedCol): ?>
                                        <td>
                                            <?php if (!empty($transaction['received'])): ?>
                                                <span class="badge badge-success">Yes</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <?php if (empty($transaction['return_date']) && (empty($transaction['received']) || !$transaction['received'])): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="borrow_id" value="<?= $transaction['id'] ?>">
                                                <button type="submit" name="mark_received" class="btn btn-sm btn-outline-success" onclick="return confirm('Confirm that the student has received this book?')">Mark as Received</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled>—</button>
                                        <?php endif; ?>
                                    </td>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?filter=<?= $filter ?>&user_id=<?= $user_id ?>&book_id=<?= $book_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page-1 ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?filter=<?= $filter ?>&user_id=<?= $user_id ?>&book_id=<?= $book_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?filter=<?= $filter ?>&user_id=<?= $user_id ?>&book_id=<?= $book_id ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page+1 ?>">Next</a>
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
        <p>No transactions found matching your criteria.</p>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
