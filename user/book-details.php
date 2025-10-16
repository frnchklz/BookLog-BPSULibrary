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

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid book selection');
    header("Location: search.php");
    exit();
}

$book_id = intval($_GET['id']);

// Get book details
$stmt = $conn->prepare("
    SELECT b.*, c.name as category_name, 
    (b.quantity - b.borrowed) as available
    FROM books b
    LEFT JOIN categories c ON b.category_id = c.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'Book not found');
    header("Location: search.php");
    exit();
}

$book = $result->fetch_assoc();

// Check if user has already borrowed this book
$stmt = $conn->prepare("
    SELECT id FROM borrows 
    WHERE user_id = ? AND book_id = ? AND return_date IS NULL
");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$result = $stmt->get_result();
$already_borrowed = ($result->num_rows > 0);

// Get related books (same category)
$related_books = [];
if ($book['category_id']) {
    $stmt = $conn->prepare("
        SELECT id, title, author, cover_image, 
        (quantity - borrowed) as available
        FROM books 
        WHERE category_id = ? AND id != ?
        ORDER BY RAND()
        LIMIT 4
    ");
    $stmt->bind_param("ii", $book['category_id'], $book_id);
    $stmt->execute();
    $related_books = $stmt->get_result();
}

// Get borrowing history for this book
$stmt = $conn->prepare("
    SELECT COUNT(*) as borrow_count
    FROM borrows
    WHERE book_id = ?
");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$borrow_stats = $stmt->get_result()->fetch_assoc();

// Include header
$pageTitle = $book['title'];
include('../includes/header.php');
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="javascript:history.back()" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <h2><?= htmlspecialchars($book['title']) ?></h2>
        <p class="text-muted">by <?= htmlspecialchars($book['author']) ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <?php if (!empty($book['cover_image']) && file_exists("../" . BOOK_COVERS_DIR . $book['cover_image'])): ?>
                    <img src="<?= BASE_URL . BOOK_COVERS_DIR . $book['cover_image'] ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="img-fluid book-detail-img mb-3">
                <?php else: ?>
                    <div class="text-center p-5 bg-light mb-3" style="height: 300px;">
                        <i class="fas fa-book fa-5x text-muted mt-5"></i>
                        <p class="mt-3">No cover available</p>
                    </div>
                <?php endif; ?>
                
                <div class="book-availability mb-3">
                    <h5>Availability Status</h5>
                    <?php if ($book['available'] > 0): ?>
                        <span class="badge badge-success p-2">Available (<?= $book['available'] ?> of <?= $book['quantity'] ?>)</span>
                    <?php else: ?>
                        <span class="badge badge-danger p-2">Currently Unavailable</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($book['available'] > 0 && !$already_borrowed && canUserBorrowMore($user_id)): ?>
                    <a href="borrow.php?id=<?= $book['id'] ?>" class="btn btn-primary btn-lg btn-block">
                        <i class="fas fa-book-reader"></i> Borrow This Book
                    </a>
                <?php elseif ($already_borrowed): ?>
                    <button class="btn btn-secondary btn-lg btn-block" disabled>
                        <i class="fas fa-check"></i> Already Borrowed
                    </button>
                <?php elseif (!canUserBorrowMore($user_id)): ?>
                    <button class="btn btn-warning btn-lg btn-block" disabled>
                        <i class="fas fa-exclamation-triangle"></i> Borrowing Limit Reached
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary btn-lg btn-block" disabled>
                        <i class="fas fa-times"></i> Unavailable
                    </button>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Book Details</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?></li>
                    <li class="list-group-item"><strong>Category:</strong> <?= htmlspecialchars($book['category_name']) ?></li>
                    <li class="list-group-item"><strong>Publication Year:</strong> <?= $book['publication_year'] ?: 'Not specified' ?></li>
                    <li class="list-group-item"><strong>Added on:</strong> <?= formatDate($book['created_at']) ?></li>
                    <li class="list-group-item"><strong>Times Borrowed:</strong> <?= $borrow_stats['borrow_count'] ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Description</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($book['description'])): ?>
                    <p><?= nl2br(htmlspecialchars($book['description'])) ?></p>
                <?php else: ?>
                    <p class="text-muted">No description available for this book.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($related_books && $related_books->num_rows > 0): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Related Books</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php while ($related = $related_books->fetch_assoc()): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 book-card">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($related['title']) ?></h5>
                                        <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($related['author']) ?></h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                <strong>Available:</strong> 
                                                <?php if ($related['available'] > 0): ?>
                                                    <span class="text-success">Yes</span>
                                                <?php else: ?>
                                                    <span class="text-danger">No</span>
                                                <?php endif; ?>
                                            </small>
                                        </p>
                                        <a href="book-details.php?id=<?= $related['id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
