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

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Process profile update
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle profile image upload for regular users
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_image'];

        // Basic validation
        $allowed_ext = ['jpg','jpeg','png','gif'];
        $max_size = 2 * 1024 * 1024; // 2MB

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error (code: {$file['error']}).";
        } elseif (!in_array($ext, $allowed_ext)) {
            $errors[] = "Invalid file type. Allowed: jpg, jpeg, png, gif.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File is too large. Maximum size is 2MB.";
        } else {
            // Ensure upload directory exists
            $target_dir = __DIR__ . '/../uploads/profile_pics/';
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            // Generate unique filename
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
            $target_path = $target_dir . $new_filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Update users table; add column if missing
                $update_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                if (!$update_stmt) {
                    $alter_sql = "ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL";
                    $conn->query($alter_sql);
                    $update_stmt = $conn->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                }

                if ($update_stmt) {
                    $rel_path = 'uploads/profile_pics/' . $new_filename;
                    $update_stmt->bind_param("si", $rel_path, $user_id);
                    if ($update_stmt->execute()) {
                        $_SESSION['profile_image'] = $rel_path;
                        $user['profile_image'] = $rel_path;
                        $success = true;
                        setAlert('success', 'Profile picture uploaded successfully');
                    } else {
                        $errors[] = 'Failed to save profile image to database.';
                        @unlink($target_path);
                    }
                } else {
                    $errors[] = 'Database error while saving profile image.';
                    @unlink($target_path);
                }
            } else {
                $errors[] = 'Failed to move uploaded file.';
            }
        }
    }

    if (isset($_POST['update_profile'])) {
        // Get post data
        $name = sanitizeInput($_POST['name']);
        $email = sanitizeInput($_POST['email']);
        
        // Validate input
        if (empty($name)) {
            $errors[] = "Name cannot be empty";
        }
        
        if (empty($email)) {
            $errors[] = "Email cannot be empty";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        }
        
        // Check if email exists (if changed)
        if ($email != $user['email']) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "Email is already in use by another account";
            }
        }
        
        // If no errors, update profile
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $email, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                $success = true;
                $user['name'] = $name;
                $user['email'] = $email;
                
                setAlert('success', 'Your profile has been updated successfully');
            } else {
                $errors[] = "Failed to update profile";
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password)) {
            $errors[] = "Current password is required";
        }
        
        if (empty($new_password)) {
            $errors[] = "New password is required";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($new_password != $confirm_password) {
            $errors[] = "New passwords do not match";
        }
        
        // Verify current password
        if (empty($errors) && !password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        // If no errors, update password
        if (empty($errors)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = true;
                setAlert('success', 'Your password has been changed successfully');
            } else {
                $errors[] = "Failed to change password";
            }
        }
    }
}

// Get user's borrowing history
$stmt = $conn->prepare("
    SELECT b.*, bk.title, bk.author 
    FROM borrows b
    JOIN books bk ON b.book_id = bk.id
    WHERE b.user_id = ?
    ORDER BY 
        CASE 
            WHEN b.return_date IS NULL THEN 0
            ELSE 1
        END,
        b.borrow_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$borrows = $stmt->get_result();

// Include header
$pageTitle = "My Profile";
include('../includes/header.php');
?>

<div class="row">
    <div class="col-md-12">
        <h2>My Profile</h2>
        <p>View and update your account information.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>


<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Account Information</h5>
            </div>
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($user['profile_image']) && file_exists(__DIR__ . '/../' . $user['profile_image'])): ?>
                        <img src="<?= BASE_URL . $user['profile_image'] ?>" class="profile-img" alt="Profile">
                    <?php else: ?>
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=random&color=fff&size=128" class="profile-img" alt="Profile">
                    <?php endif; ?>
                </div>
                <form method="post" action="" enctype="multipart/form-data" class="mb-3">
                    <div class="form-group">
                        <label for="profile_image">Upload Profile Picture</label>
                        <input type="file" name="profile_image" id="profile_image" class="form-control-file" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Upload</button>
                </form>
                
                <h4><?= htmlspecialchars($user['name']) ?></h4>
                <p class="text-muted"><?= htmlspecialchars($user['email']) ?></p>
                
                <ul class="list-group mt-3 text-left">
                    <li class="list-group-item">
                        <strong>Account Type:</strong> 
                        <span class="badge badge-info">Library Member</span>
                    </li>
                    <li class="list-group-item">
                        <strong>Status:</strong> 
                        <?php if ($user['status'] == 'active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php elseif ($user['status'] == 'inactive'): ?>
                            <span class="badge badge-secondary">Inactive</span>
                        <?php elseif ($user['status'] == 'suspended'): ?>
                            <span class="badge badge-danger">Suspended</span>
                        <?php endif; ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Member Since:</strong> 
                        <?= formatDate($user['created_at']) ?>
                    </li>
                    <li class="list-group-item">
                        <strong>Last Login:</strong> 
                        <?= $user['last_login'] ? formatDate($user['last_login']) : 'Never' ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Borrowing Statistics</h5>
            </div>
            <div class="card-body">
                <?php
                // Get borrowing statistics
                $stmt = $conn->prepare("
                    SELECT 
                        COUNT(*) as total_borrows,
                        SUM(CASE WHEN return_date IS NULL THEN 1 ELSE 0 END) as active_borrows,
                        SUM(CASE WHEN return_date IS NULL AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                    FROM borrows
                    WHERE user_id = ?
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stats = $stmt->get_result()->fetch_assoc();
                ?>
                
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Total Books Borrowed
                        <span class="badge badge-primary badge-pill"><?= $stats['total_borrows'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Currently Borrowed
                        <span class="badge badge-info badge-pill"><?= $stats['active_borrows'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Overdue Books
                        <span class="badge badge-danger badge-pill"><?= $stats['overdue'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Available Slots
                        <span class="badge badge-success badge-pill"><?= MAX_BOOKS_PER_USER - $stats['active_borrows'] ?></span>
                    </li>
                </ul>
                
                <div class="mt-3">
                    <a href="history.php" class="btn btn-outline-primary btn-sm btn-block">View Complete History</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Edit Profile</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Recent Borrowing Activity</h5>
            </div>
            <div class="card-body">
                <?php if ($borrows->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Book</th>
                                    <th>Borrowed On</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($borrow = $borrows->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($borrow['title']) ?></td>
                                        <td><?= formatDate($borrow['borrow_date']) ?></td>
                                        <td><?= formatDate($borrow['due_date']) ?></td>
                                        <td>
                                            <?php if ($borrow['return_date']): ?>
                                                <span class="badge badge-success">Returned on <?= formatDate($borrow['return_date']) ?></span>
                                            <?php elseif (strtotime($borrow['due_date']) < time()): ?>
                                                <span class="badge badge-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge badge-info">Borrowed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">You haven't borrowed any books yet.</p>
                    <a href="search.php" class="btn btn-primary">Browse Books</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
