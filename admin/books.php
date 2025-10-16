<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Get categories for filter
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->get_result();

// Initialize search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// Build query
$query = "SELECT b.*, c.name as category_name FROM books b 
          LEFT JOIN categories c ON b.category_id = c.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM books b WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $count_query .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if ($category > 0) {
    $query .= " AND b.category_id = ?";
    $count_query .= " AND b.category_id = ?";
    $params[] = $category;
    $types .= "i";
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
$query .= " ORDER BY b.title ASC LIMIT ?, ?";
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
$books = $stmt->get_result();

// Include header
$pageTitle = "Manage Books";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Manage Books</h2>
    <a href="add_book.php" class="btn btn-success"><i class="fas fa-plus"></i> Add New Book</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <div class="col-md-6 form-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Search by title, author or ISBN" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="category">Category</label>
                <select name="category" id="category" class="form-control">
                    <option value="0">All Categories</option>
                    <?php while ($cat = $categories->fetch_assoc()): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($books->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($book['title']) ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['isbn']) ?></td>
                                <td><?= htmlspecialchars($book['category_name']) ?></td>
                                <td><?= $book['quantity'] ?></td>
                                <td><?= $book['quantity'] - $book['borrowed'] ?></td>
                                <td>
                                    <a href="edit_book.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&category=<?= $category ?>&page=<?= $page-1 ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&category=<?= $category ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&category=<?= $category ?>&page=<?= $page+1 ?>">Next</a>
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
        <p>No books found matching your criteria.</p>
        <?php if (!empty($search) || $category > 0): ?>
            <a href="books.php" class="btn btn-outline-primary">Clear Search</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
