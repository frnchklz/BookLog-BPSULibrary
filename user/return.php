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

// Check if borrow ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid borrow selection');
    header("Location: dashboard.php");
    exit();
}

$borrow_id = intval($_GET['id']);

// Check if the borrow exists and belongs to the user
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.id = ? AND b.user_id = ? AND b.return_date IS NULL
");
$stmt->bind_param("ii", $borrow_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'The selected borrow record is not valid or does not belong to you');
    header("Location: dashboard.php");
    exit();
}

$borrow = $result->fetch_assoc();

// Process return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_return'])) {
    $return_date = date('Y-m-d');
    $notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update borrow record
        $stmt = $conn->prepare("
            UPDATE borrows 
            SET return_date = ?, notes = ?
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $return_date, $notes, $borrow_id);
        $stmt->execute();
        
        // Update book borrowed count
        $stmt = $conn->prepare("
            UPDATE books 
            SET borrowed = borrowed - 1 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $borrow['book_id']);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        setAlert('success', 'Book "' . $borrow['title'] . '" has been returned successfully');
        header("Location: dashboard.php");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        setAlert('danger', 'An error occurred while processing your return. Please try again.');
        header("Location: dashboard.php");
        exit();
    }
}

// Include header
$pageTitle = "Return Book";
include('../includes/header.php');
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Confirm Book Return</h4>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p>You are about to return the following book:</p>
                </div>
                
                <div class="book-details mb-4">
                    <h5><?= htmlspecialchars($borrow['title']) ?></h5>
                    <p class="text-muted">by <?= htmlspecialchars($borrow['author']) ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card bg-light mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Borrowing Details</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Borrow Date:</strong> <?= formatDate($borrow['borrow_date']) ?></li>
                                    <li><strong>Due Date:</strong> <?= formatDate($borrow['due_date']) ?></li>
                                    <li><strong>Return Date:</strong> <?= date('F j, Y') ?> (Today)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card <?= strtotime($borrow['due_date']) < time() ? 'bg-warning' : 'bg-light' ?> mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Return Status</h6>
                                <?php if (strtotime($borrow['due_date']) < time()): ?>
                                    <?php 
                                    $due_date = new DateTime($borrow['due_date']);
                                    $today = new DateTime();
                                    $days_overdue = $today->diff($due_date)->days;
                                    ?>
                                    <p><strong>Status:</strong> <span class="text-danger">Overdue by <?= $days_overdue ?> days</span></p>
                                    <p>Please note that overdue books may incur fines according to library policy.</p>
                                <?php else: ?>
                                    <p><strong>Status:</strong> <span class="text-success">On time</span></p>
                                    <p>Thank you for returning the book before the due date!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="" class="mt-4">
                    <div class="form-group">
                        <label for="notes">Additional Notes (Optional)</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3" placeholder="Any comments about the book condition or return process"></textarea>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" name="confirm_return" class="btn btn-success mr-2">Confirm Return</button>
                        <a href="javascript:history.back()" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
