<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireLogin();

// Redirect if admin or head librarian
if (isAdmin() || isHeadLibrarian()) {
    header("Location: ../admin/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize filter parameters
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// Build query
$query = "
    SELECT b.*, bk.title, bk.author, bk.isbn, bk.cover_image, 
    CASE 
        WHEN b.return_date IS NULL AND b.due_date < CURDATE() THEN 'overdue'
        WHEN b.return_date IS NULL THEN 'active'
        ELSE 'returned'
    END as status
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ?
";

$count_query = "SELECT COUNT(*) as total FROM borrows b WHERE b.user_id = ?";

$params = [$user_id];
$types = "i";

// Apply filters
if ($status == 'active') {
    $query .= " AND b.return_date IS NULL AND b.due_date >= CURDATE()";
    $count_query .= " AND b.return_date IS NULL AND b.due_date >= CURDATE()";
} elseif ($status == 'returned') {
    $query .= " AND b.return_date IS NOT NULL";
    $count_query .= " AND b.return_date IS NOT NULL";
} elseif ($status == 'overdue') {
    $query .= " AND b.return_date IS NULL AND b.due_date < CURDATE()";
    $count_query .= " AND b.return_date IS NULL AND b.due_date < CURDATE()";
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

// Add sorting and pagination
$query .= " ORDER BY 
    CASE 
        WHEN b.return_date IS NULL THEN b.due_date
        ELSE b.return_date
    END DESC
    LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$borrows = $stmt->get_result();

// Get user's borrow statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_borrows,
        SUM(CASE WHEN return_date IS NULL AND due_date >= CURDATE() THEN 1 ELSE 0 END) as active_borrows,
        SUM(CASE WHEN return_date IS NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_borrows,
        SUM(CASE WHEN return_date IS NOT NULL THEN 1 ELSE 0 END) as returned_books
    FROM borrows
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Include header
$pageTitle = "Borrowing History";
include('../includes/header.php');
?>

<div class="row">
    <div class="col-md-12">
        <h2>My Borrowing History</h2>
        <p>View your complete book borrowing history and current loans.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="card-title">Total Borrows</h5>
                <h2 class="display-4"><?= $stats['total_borrows'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h5 class="card-title">Active Loans</h5>
                <h2 class="display-4"><?= $stats['active_borrows'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h5 class="card-title">Overdue Books</h5>
                <h2 class="display-4"><?= $stats['overdue_borrows'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title">Returned Books</h5>
                <h2 class="display-4"><?= $stats['returned_books'] ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <div class="col-md-4 form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Borrows</option>
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Currently Borrowed</option>
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
            <div class="col-md-2 form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block">Filter</button>
                <?php if (!empty($status) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="history.php" class="btn btn-secondary btn-block mt-2">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($borrows->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Book Details</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($borrow = $borrows->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex">
                                        <?php if (!empty($borrow['cover_image']) && file_exists("../" . BOOK_COVERS_DIR . $borrow['cover_image'])): ?>
                                            <img src="<?= BASE_URL . BOOK_COVERS_DIR . $borrow['cover_image'] ?>" alt="Cover" class="mr-3" style="width: 50px; height: 70px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="mr-3 bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 70px;">
                                                <i class="fas fa-book text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($borrow['title']) ?></h6>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($borrow['author']) ?><br>
                                                ISBN: <?= htmlspecialchars($borrow['isbn']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= formatDate($borrow['borrow_date']) ?></td>
                                <td><?= formatDate($borrow['due_date']) ?></td>
                                <td>
                                    <?php if ($borrow['return_date']): ?>
                                        <?= formatDate($borrow['return_date']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not returned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($borrow['status'] == 'active'): ?>
                                        <span class="badge badge-info">Currently Borrowed</span>
                                    <?php elseif ($borrow['status'] == 'returned'): ?>
                                        <span class="badge badge-success">Returned</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="book-details.php?id=<?= $borrow['book_id'] ?>" class="btn btn-sm btn-info">Book Details</a>
                                    <?php if ($borrow['status'] != 'returned'): ?>
                                        <a href="return.php?id=<?= $borrow['id'] ?>" class="btn btn-sm btn-success">Return</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page-1 ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?= $status ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&page=<?= $page+1 ?>">Next</a>
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
        <p>No borrowing history found<?= (!empty($status) || !empty($start_date) || !empty($end_date)) ? ' matching your filter criteria' : '' ?>.</p>
        <?php if (empty($stats['total_borrows'])): ?>
            <a href="search.php" class="btn btn-primary mt-2">Browse Books to Borrow</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
