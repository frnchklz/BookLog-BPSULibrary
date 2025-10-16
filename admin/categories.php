<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Handle category operations
$message = '';
$error = '';
$edit_id = 0;
$edit_name = '';
$edit_description = '';

// Handle Add/Edit Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category']) || isset($_POST['update_category'])) {
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        
        // Validate input
        if (empty($name)) {
            $error = "Category name is required";
        } else {
            // Check if editing or adding
            if (isset($_POST['update_category'])) {
                $id = intval($_POST['category_id']);
                
                // Check if name already exists for another category
                $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $stmt->bind_param("si", $name, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "A category with this name already exists";
                } else {
                    // Update category
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $name, $description, $id);
                    
                    if ($stmt->execute()) {
                        $message = "Category updated successfully";
                    } else {
                        $error = "Failed to update category";
                    }
                }
            } else {
                // Adding new category
                // Check if name already exists
                $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->bind_param("s", $name);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "A category with this name already exists";
                } else {
                    // Insert category
                    $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->bind_param("ss", $name, $description);
                    
                    if ($stmt->execute()) {
                        $message = "Category added successfully";
                    } else {
                        $error = "Failed to add category";
                    }
                }
            }
        }
    }
    
    // Handle Delete Request
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['category_id']);
        
        // Check if category is in use
        $stmt = $conn->prepare("SELECT COUNT(*) AS book_count FROM books WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $book_count = $result->fetch_assoc()['book_count'];
        
        if ($book_count > 0) {
            $error = "Cannot delete this category because it is assigned to $book_count book(s)";
        } else {
            // Delete category
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $message = "Category deleted successfully";
            } else {
                $error = "Failed to delete category";
            }
        }
    }
}

// Handle Edit Request (GET)
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $category = $result->fetch_assoc();
        $edit_name = $category['name'];
        $edit_description = $category['description'];
    } else {
        $error = "Category not found";
        $edit_id = 0;
    }
}

// Get all categories
$stmt = $conn->prepare("SELECT c.*, (SELECT COUNT(*) FROM books WHERE category_id = c.id) AS book_count FROM categories c ORDER BY c.name");
$stmt->execute();
$categories = $stmt->get_result();

// Include header
$pageTitle = "Manage Categories";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Categories</h2>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= $message ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><?= $edit_id ? 'Edit Category' : 'Add New Category' ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <?php if ($edit_id): ?>
                        <input type="hidden" name="category_id" value="<?= $edit_id ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="name">Category Name</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($edit_name) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description (Optional)</label>
                        <textarea name="description" id="description" class="form-control" rows="4"><?= htmlspecialchars($edit_description) ?></textarea>
                    </div>
                    
                    <?php if ($edit_id): ?>
                        <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="add_category" class="btn btn-success">Add Category</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Category List</h5>
            </div>
            <div class="card-body">
                <?php if ($categories->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Books Count</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($category['name']) ?></td>
                                        <td>
                                            <?php if (!empty($category['description'])): ?>
                                                <?= htmlspecialchars(substr($category['description'], 0, 100)) ?>
                                                <?= (strlen($category['description']) > 100) ? '...' : '' ?>
                                            <?php else: ?>
                                                <em class="text-muted">No description</em>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $category['book_count'] ?></td>
                                        <td>
                                            <a href="categories.php?edit=<?= $category['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                            
                                            <?php if ($category['book_count'] == 0): ?>
                                                <form method="post" action="" class="d-inline">
                                                    <input type="hidden" name="category_id" value="<?= $category['id'] ?>">
                                                    
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No categories found. Add your first category using the form.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
