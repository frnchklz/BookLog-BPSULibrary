<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header("Location: ../admin/dashboard.php");
    } else {
        header("Location: ../user/dashboard.php");
    }
    exit();
}

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    setAlert('danger', 'Invalid verification token');
    header("Location: reset-password.php");
    exit();
}

$token = sanitizeInput($_GET['token']);
$db = Database::getInstance();
$conn = $db->getConnection();

// Get reset request details
$stmt = $conn->prepare("
    SELECT pr.*, u.name, u.email 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.token = ? AND pr.expires_at > NOW()
");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    setAlert('danger', 'Invalid or expired verification token');
    header("Location: reset-password.php");
    exit();
}

$request = $result->fetch_assoc();

// Include header
$pageTitle = "Verification Status";
include('../includes/header.php');
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Password Reset Verification Status</h4>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <?php if ($request['status'] == 'pending'): ?>
                        <i class="fas fa-clock text-warning fa-5x mb-3"></i>
                        <h3>Verification Pending</h3>
                        <p>Your identity verification is pending administrator review.</p>
                    <?php elseif ($request['status'] == 'approved'): ?>
                        <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                        <h3>Verification Approved</h3>
                        <p>Your identity has been verified and you can now reset your password.</p>
                    <?php elseif ($request['status'] == 'rejected'): ?>
                        <i class="fas fa-times-circle text-danger fa-5x mb-3"></i>
                        <h3>Verification Rejected</h3>
                        <p>Your identity verification has been rejected by an administrator.</p>
                    <?php endif; ?>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Request Details</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Email:</strong>
                                <span><?= htmlspecialchars($request['email']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Request Date:</strong>
                                <span><?= formatDate($request['created_at']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Expires:</strong>
                                <span><?= formatDate($request['expires_at']) ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <strong>Status:</strong>
                                <?php if ($request['status'] == 'pending'): ?>
                                    <span class="badge badge-warning">Pending</span>
                                <?php elseif ($request['status'] == 'approved'): ?>
                                    <span class="badge badge-success">Approved</span>
                                <?php elseif ($request['status'] == 'rejected'): ?>
                                    <span class="badge badge-danger">Rejected</span>
                                <?php endif; ?>
                            </li>
                        </ul>

                        <?php if ($request['status'] == 'rejected' && !empty($request['rejection_reason'])): ?>
                            <div class="alert alert-danger mt-3">
                                <h5>Reason for Rejection:</h5>
                                <p><?= htmlspecialchars($request['rejection_reason']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center">
                    <?php if ($request['status'] == 'approved'): ?>
                        <a href="reset-password.php?token=<?= $token ?>" class="btn btn-primary">Reset Password</a>
                    <?php elseif ($request['status'] == 'rejected'): ?>
                        <p class="mb-3">You may submit a new password reset request if needed.</p>
                        <a href="reset-password.php" class="btn btn-primary">New Reset Request</a>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p><i class="fas fa-info-circle"></i> An administrator will review your verification shortly. Please check back later.</p>
                            <p>If you have any questions, please contact the library administration.</p>
                        </div>
                    <?php endif; ?>
                    
                    <a href="login.php" class="btn btn-secondary ml-2">Back to Login</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
