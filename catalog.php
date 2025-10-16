<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    startSecureSession();
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all categories for filter
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
$query = "SELECT b.*, c.name as category_name, 
          (b.quantity - b.borrowed) as available 
          FROM books b
          LEFT JOIN categories c ON b.category_id = c.id
          WHERE 1=1";
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
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $items_per_page);

// Add sorting and pagination
$query .= " ORDER BY b.title ASC LIMIT ?, ?";
$params[] = $offset;
$params[] = $items_per_page;
$types .= "ii";

// Execute main query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books = $stmt->get_result();

// Include header
$pageTitle = "Book Catalog";
include('includes/header.php');
?>

<div class="jumbotron">
    <h1 class="display-4">Our Book Catalog</h1>
    <p class="lead">Browse our extensive collection of books. Sign in to borrow them.</p>
    <hr class="my-4">
    <p>Use the search filters below to find books by title, author, or category.</p>
</div>

<div class="row">
    <div class="col-md-12">
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
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <?php if ($books->num_rows > 0): ?>
            <p>Showing <?= min($offset + 1, $total_rows) ?> to <?= min($offset + $items_per_page, $total_rows) ?> of <?= $total_rows ?> results</p>
            
            <div class="row">
                <?php while ($book = $books->fetch_assoc()): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
                                <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($book['author']) ?></h6>
                                <p class="card-text">
                                    <small class="text-muted">
                                        <strong>ISBN:</strong> <?= htmlspecialchars($book['isbn']) ?><br>
                                        <strong>Category:</strong> <?= htmlspecialchars($book['category_name']) ?><br>
                                        <strong>Available:</strong> 
                                        <?php if ($book['available'] > 0): ?>
                                            <span class="text-success"><?= $book['available'] ?> of <?= $book['quantity'] ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">Out of stock</span>
                                        <?php endif; ?>
                                    </small>
                                </p>
                                <div class="mt-auto text-center">
                                    <a href="auth/login.php" class="btn btn-primary">Login to Borrow</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
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
            
        <?php else: ?>
            <div class="alert alert-info">
                <p>No books found matching your search criteria.</p>
                <?php if (!empty($search) || $category > 0): ?>
                    <a href="catalog.php" class="btn btn-outline-primary">Clear Search</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4 mb-4">
            <p class="lead">Want to borrow books? You need to sign in first.</p>
            <a href="auth/login.php" class="btn btn-primary mr-2">Login</a>
            <a href="auth/register.php" class="btn btn-success">Register</a>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>
