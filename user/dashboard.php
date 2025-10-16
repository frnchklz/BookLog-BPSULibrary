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

// Get user's borrowed books
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author, bk.isbn
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ? AND b.return_date IS NULL
    ORDER BY b.due_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrowed_books = $stmt->get_result();

// Get user's recently returned books
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ? AND b.return_date IS NOT NULL
    ORDER BY b.return_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$returned_books = $stmt->get_result();

// Get user's overdue books
$overdue_books = getUserOverdueBooks($user_id);

// Get new book arrivals
$stmt = $conn->prepare("
    SELECT * FROM books
    ORDER BY created_at DESC
    LIMIT 6
");
$stmt->execute();
$new_arrivals = $stmt->get_result();

// Include header
$pageTitle = "Dashboard";
include('../includes/header.php');
?>

<div class="row">
    <div class="col-md-12">
        <h2>Welcome, <?= $_SESSION['name'] ?></h2>
        <p>This is your personal dashboard. You can manage your book borrowings and account details here.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Books Borrowed</h5>
                <h2 class="display-4"><?= $borrowed_books->num_rows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#current-borrows">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Books Overdue</h5>
                <h2 class="display-4"><?= $overdue_books->num_rows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#overdue-books">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">Available Slots</h5>
                <h2 class="display-4"><?= MAX_BOOKS_PER_USER - $borrowed_books->num_rows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="search.php">Borrow More</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white mb-4">
            <div class="card-body">
                <h5 class="card-title">New Arrivals</h5>
                <h2 class="display-4"><?= $new_arrivals->num_rows ?></h2>
            </div>
            <div class="card-footer d-flex align-items-center justify-content-between">
                <a class="small text-white stretched-link" href="#new-arrivals">View Details</a>
                <div class="small text-white"><i class="fas fa-angle-right"></i></div>
            </div>
        </div>
    </div>
</div>

<?php if ($overdue_books->num_rows > 0): ?>
<div class="row">
    <div class="col-md-12">
        <div class="alert alert-danger">
            <h4><i class="fas fa-exclamation-triangle"></i> Attention!</h4>
            <p>You have <?= $overdue_books->num_rows ?> overdue book(s). Please return them as soon as possible to avoid fines.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card" id="current-borrows">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Current Borrows</h5>
            </div>
            <div class="card-body">
                <?php if ($borrowed_books->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Borrow Date</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($book = $borrowed_books->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($book['title']) ?></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= formatDate($book['borrow_date']) ?></td>
                                        <td><?= formatDate($book['due_date']) ?></td>
                                        <td>
                                            <?php if (strtotime($book['due_date']) < time()): ?>
                                                <span class="badge badge-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Current</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="return.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-primary">Return</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">You don't have any books borrowed at the moment.</p>
                    <a href="search.php" class="btn btn-primary">Browse Books</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($overdue_books->num_rows > 0): ?>
        <div class="card mt-4" id="overdue-books">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Overdue Books</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($book = $overdue_books->fetch_assoc()): ?>
                                <?php
                                $due_date = new DateTime($book['due_date']);
                                $today = new DateTime();
                                $days_overdue = $today->diff($due_date)->days;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($book['title']) ?></td>
                                    <td><?= formatDate($book['due_date']) ?></td>
                                    <td><?= $days_overdue ?> days</td>
                                    <td>
                                        <a href="return.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-warning">Return Now</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="col-md-4">
        <div class="card" id="new-arrivals">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">New Book Arrivals</h5>
            </div>
            <div class="card-body">
                <?php if ($new_arrivals->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($book = $new_arrivals->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <h6><?= htmlspecialchars($book['title']) ?></h6>
                                <small class="text-muted">
                                    <strong>Author:</strong> <?= htmlspecialchars($book['author']) ?><br>
                                    <strong>Added:</strong> <?= formatDate($book['created_at']) ?>
                                </small>
                                <div class="mt-2">
                                    <a href="book-details.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No new arrivals at the moment.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="search.php" class="btn btn-outline-primary btn-sm">Browse All Books</a>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Recently Returned</h5>
            </div>
            <div class="card-body">
                <?php if ($returned_books->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($book = $returned_books->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <h6><?= htmlspecialchars($book['title']) ?></h6>
                                <small class="text-muted">
                                    <strong>Returned on:</strong> <?= formatDate($book['return_date']) ?>
                                </small>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">You haven't returned any books yet.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="history.php" class="btn btn-outline-success btn-sm">View Full History</a>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
