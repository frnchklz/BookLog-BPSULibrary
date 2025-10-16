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
        // Check if ISBN already exists
        $stmt = $conn->prepare("SELECT id FROM books WHERE isbn = ?");
        $stmt->bind_param("s", $isbn);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $errors[] = "A book with this ISBN already exists";
        }
    }
    
    if ($quantity <= 0) {
        $errors[] = "Quantity must be greater than zero";
    }
    
    if ($category_id <= 0) {
        $errors[] = "Please select a category";
    }
    
    // If no errors, insert the book
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO books (title, author, isbn, description, category_id, quantity, borrowed, publication_year, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())
        ");
        $stmt->bind_param("ssssiii", $title, $author, $isbn, $description, $category_id, $quantity, $publication_year);
        
        if ($stmt->execute()) {
            $book_id = $conn->insert_id;
            
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
                    
                    if (move_uploaded_file($_FILES['cover']['tmp_name'], $target_file)) {
                        // Update book record with cover image filename
                        $stmt = $conn->prepare("UPDATE books SET cover_image = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_filename, $book_id);
                        $stmt->execute();
                    }
                }
            }
            
            setAlert('success', 'Book has been added successfully');
            header("Location: books.php");
            exit();
        } else {
            $errors[] = "Failed to add book. Please try again.";
        }
    }
}

// Include header
$pageTitle = "Add New Book";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Add New Book</h2>
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
        
        <form method="post" action="" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" name="title" id="title" class="form-control" value="<?= isset($title) ? $title : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" name="author" id="author" class="form-control" value="<?= isset($author) ? $author : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" name="isbn" id="isbn" class="form-control" value="<?= isset($isbn) ? $isbn : '' ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <option value="<?= $category['id'] ?>" <?= isset($category_id) && $category_id == $category['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="quantity">Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="<?= isset($quantity) ? $quantity : '1' ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="publication_year">Publication Year</label>
                        <input type="number" name="publication_year" id="publication_year" class="form-control" value="<?= isset($publication_year) ? $publication_year : date('Y') ?>" min="1900" max="<?= date('Y') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="cover">Cover Image</label>
                        <input type="file" name="cover" id="cover" class="form-control-file">
                        <small class="form-text text-muted">Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="5"><?= isset($description) ? $description : '' ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Add Book</button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
