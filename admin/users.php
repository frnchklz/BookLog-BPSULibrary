<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize search parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = ITEMS_PER_PAGE;
$offset = ($page - 1) * $items_per_page;

// Build query
$query = "SELECT * FROM users WHERE role = 'user'";
$count_query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";

$params = [];
$types = "";

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $query .= " AND (name LIKE ? OR email LIKE ?)";
    $count_query .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if (!empty($status)) {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
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
$query .= " ORDER BY created_at DESC LIMIT ?, ?";
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
$users = $stmt->get_result();

// Handle user status change
if (isset($_POST['change_status']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $new_status = sanitizeInput($_POST['new_status']);
    
    if (in_array($new_status, ['active', 'inactive', 'suspended'])) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'user'");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'User status has been updated successfully');
        } else {
            setAlert('danger', 'Failed to update user status');
        }
        
        // Redirect to avoid form resubmission
        header("Location: users.php" . (empty($_SERVER['QUERY_STRING']) ? "" : "?" . $_SERVER['QUERY_STRING']));
        exit();
    }
}

// Include header
$pageTitle = "Manage Users";
include('../includes/header.php');
?>

<h2>Manage Users</h2>
<p>View and manage library users.</p>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="" class="row">
            <div class="col-md-6 form-group">
                <label for="search">Search</label>
                <input type="text" name="search" id="search" class="form-control" placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-4 form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-block">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($users->num_rows > 0): ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered On</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['name']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($user['status'] == 'inactive'): ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= formatDate($user['created_at']) ?></td>
                                <td><?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-toggle="dropdown">
                                            Action
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="user_details.php?id=<?= $user['id'] ?>">View Details</a>
                                            <a class="dropdown-item" href="user_borrows.php?id=<?= $user['id'] ?>">View Borrows</a>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($user['status'] != 'active'): ?>
                                                <form method="post" action="">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="new_status" value="active">
                                                    <button type="submit" name="change_status" class="dropdown-item text-success">Activate</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                           
                                        </div>
                                    </div>
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
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&status=<?= $status ?>&page=<?= $page-1 ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <a class="page-link" href="#">Previous</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?search=<?= urlencode($search) ?>&status=<?= $status ?>&page=<?= $page+1 ?>">Next</a>
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
        <p>No users found matching your criteria.</p>
        <?php if (!empty($search) || !empty($status)): ?>
            <a href="users.php" class="btn btn-outline-primary">Clear Search</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
