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

// Check if the user can borrow more books
$user_borrows = getUserActiveBorrowsCount($user_id);
if ($user_borrows >= MAX_BOOKS_PER_USER) {
    setAlert('danger', 'You have reached the maximum number of books you can borrow (' . MAX_BOOKS_PER_USER . ')');
    header("Location: dashboard.php");
    exit();
}

// Check if the book exists and is available
$stmt = $conn->prepare("
    SELECT title, author, quantity, borrowed 
    FROM books 
    WHERE id = ? AND (quantity - borrowed) > 0
");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'The selected book is not available for borrowing');
    header("Location: search.php");
    exit();
}

$book = $result->fetch_assoc();

// Check if the user already has this book borrowed
$stmt = $conn->prepare("
    SELECT id FROM borrows 
    WHERE user_id = ? AND book_id = ? AND return_date IS NULL
");
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    setAlert('warning', 'You already have this book borrowed');
    header("Location: dashboard.php");
    exit();
}

// Process borrow request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_borrow'])) {
    // Calculate due date
    $borrow_date = date('Y-m-d');
    $due_date = calculateDueDate($borrow_date);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Insert borrow record
        $stmt = $conn->prepare("
            INSERT INTO borrows (user_id, book_id, borrow_date, due_date) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iiss", $user_id, $book_id, $borrow_date, $due_date);
        $stmt->execute();
        
        // Update book borrowed count
        $stmt = $conn->prepare("
            UPDATE books SET borrowed = borrowed + 1 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        setAlert('success', 'You have successfully borrowed: ' . $book['title']);
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        setAlert('danger', 'An error occurred while processing your request. Please try again.');
        header("Location: search.php");
        exit();
    }
}

// Include header
$pageTitle = "Borrow Book";
include('../includes/header.php');
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Confirm Book Borrowing</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>You are about to borrow the following book:</p>
                </div>
                
                <div class="book-details mb-4">
                    <h5><?= htmlspecialchars($book['title']) ?></h5>
                    <p class="text-muted">by <?= htmlspecialchars($book['author']) ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Borrowing Details</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Borrow Date:</strong> <?= date('F j, Y') ?></li>
                                    <li><strong>Due Date:</strong> <?= formatDate(calculateDueDate()) ?></li>
                                    <li><strong>Maximum Loan Period:</strong> <?= MAX_LOAN_DAYS ?> days</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Your Borrowing Status</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Books Currently Borrowed:</strong> <?= $user_borrows ?></li>
                                    <li><strong>Maximum Books Allowed:</strong> <?= MAX_BOOKS_PER_USER ?></li>
                                    <li><strong>Remaining Capacity:</strong> <?= MAX_BOOKS_PER_USER - $user_borrows ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning mt-3">
                    <p><strong>Important Note:</strong> By borrowing this book, you agree to return it before the due date. 
                    Late returns may result in fines or borrowing privileges being suspended.</p>
                </div>
                
                <form method="post" action="" class="mt-4 text-center">
                    <button type="submit" name="confirm_borrow" class="btn btn-success mr-2">Confirm Borrowing</button>
                    <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
