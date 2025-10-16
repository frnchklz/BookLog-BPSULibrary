<?php 
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all categories
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result();

$errors = [];
$success = false;

// Check if book ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setAlert('danger', 'Invalid book ID');
    header("Location: books.php");
    exit();
}

$book_id = intval($_GET['id']);

// Get book data
$stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'Book not found');
    header("Location: books.php");
    exit();
}

$book = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate input
    $title = sanitizeInput($_POST['title']);
    $author = sanitizeInput($_POST['author']);
    $isbn = sanitizeInput($_POST['isbn']);
    $description = sanitizeInput($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $quantity = intval($_POST['quantity']);
    $publication_year = intval($_POST['publication_year']);
    
    // Validation
    if (empty($title)) {
        $errors[] = "Title is required";
    }
    
    if (empty($author)) {
        $errors[] = "Author is required";
    }
    
    if (empty($isbn)) {
        $errors[] = "ISBN is required";
    } else {
        // Check if ISBN already exists for a different book
        $stmt = $conn->prepare("SELECT id FROM books WHERE isbn = ? AND id != ?");
        $stmt->bind_param("si", $isbn, $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "A different book with this ISBN already exists";
        }
    }
    
    if ($quantity < $book['borrowed']) {
        $errors[] = "Quantity cannot be less than currently borrowed books (" . $book['borrowed'] . ")";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    // If no errors, update the book
    if (empty($errors)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            $stmt = $conn->prepare("
                UPDATE books 
                SET title = ?, author = ?, isbn = ?, description = ?, 
                    category_id = ?, quantity = ?, publication_year = ?, updated_at = NOW()
                WHERE id = ?
            ");
            // Correct bind_param types: 4 strings (s), 4 ints (i)
            $stmt->bind_param("ssssiiii", $title, $author, $isbn, $description, $category_id, $quantity, $publication_year, $book_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Database error: " . $stmt->error);
            }
            
            // Handle cover image upload if present
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['cover']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed)) {
                    $target_dir = "../" . BOOK_COVERS_DIR;
                    $new_filename = "book_" . $book_id . "." . $ext;
                    $target_file = $target_dir . $new_filename;
                    
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    // Remove old cover if it exists
                    if (!empty($book['cover_image'])) {
                        $old_file = $target_dir . $book['cover_image'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    if (move_uploaded_file($_FILES['cover']['tmp_name'], $target_file)) {
                        // Update book record with cover image filename
                        $stmt = $conn->prepare("UPDATE books SET cover_image = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_filename, $book_id);
                        
                        if (!$stmt->execute()) {
                            throw new Exception("Failed to update cover image: " . $stmt->error);
                        }
                    } else {
                        throw new Exception("Failed to upload image");
                    }
                }
            }
            
            // Commit the transaction
            $conn->commit();
            
            setAlert('success', 'Book has been updated successfully');
            
            // Redirect to the same page to prevent form resubmission
            header("Location: edit_book.php?id=" . $book_id . "&updated=1");
            exit();
            
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Check if this is a redirect after successful update
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $success = true;
    
    // Refresh book data after successful update
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $book = $result->fetch_assoc();
    }
}

// Include header
$pageTitle = "Edit Book";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Book</h2>
    <a href="books.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Books</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Book has been updated successfully.
            </div>
        <?php endif; ?>
        
        <form method="post" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" name="title" id="title" class="form-control" value="<?= htmlspecialchars($book['title']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" name="author" id="author" class="form-control" value="<?= htmlspecialchars($book['author']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" name="isbn" id="isbn" class="form-control" value="<?= htmlspecialchars($book['isbn']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php 
                            // Reset categories result pointer
                            $categories->data_seek(0);
                            while ($category = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?= $category['id'] ?>" <?= $book['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="<?= $book['quantity'] ?>" min="<?= $book['borrowed'] ?>" required>
                        <small class="form-text text-muted">Cannot be less than currently borrowed (<?= $book['borrowed'] ?>)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="publication_year">Publication Year</label>
                        <input type="number" name="publication_year" id="publication_year" class="form-control" value="<?= $book['publication_year'] ?>" min="1900" max="<?= date('Y') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cover">Cover Image</label>
                        <?php if (!empty($book['cover_image'])): ?>
                            <div class="mb-2">
                                <img src="<?= BASE_URL . BOOK_COVERS_DIR . $book['cover_image'] ?>" alt="Book Cover" class="img-thumbnail" style="max-height: 150px;">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="cover" id="cover" class="form-control-file">
                        <small class="form-text text-muted">Leave empty to keep current cover. Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="5"><?= htmlspecialchars($book['description']) ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Update Book</button>
                <a href="books.php" class="btn btn-secondary ml-2">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
