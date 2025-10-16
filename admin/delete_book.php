<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid book ID');
    header("Location: books.php");
    exit();
}

$book_id = intval($_GET['id']);

// Check if the book exists
$stmt = $conn->prepare("SELECT title, borrowed, cover_image FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'Book not found');
    header("Location: books.php");
    exit();
}

$book = $result->fetch_assoc();

// Check if the book is currently borrowed
if ($book['borrowed'] > 0) {
    setAlert('danger', 'Cannot delete book "' . htmlspecialchars($book['title']) . '" because it is currently borrowed by users');
    header("Location: books.php");
    exit();
}

// Process deletion
try {
    // Start transaction
    $conn->begin_transaction();
    
    // Delete the book
    $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    
    // Check if the book had a cover image and delete it
    if (!empty($book['cover_image'])) {
        $cover_path = '../' . BOOK_COVERS_DIR . $book['cover_image'];
        if (file_exists($cover_path)) {
            unlink($cover_path);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    setAlert('success', 'Book "' . htmlspecialchars($book['title']) . '" has been deleted successfully');
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    setAlert('danger', 'Error deleting book: ' . $e->getMessage());
}

// Redirect back to books page
header("Location: books.php");
exit();
?>
