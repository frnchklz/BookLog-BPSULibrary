<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

$db = Database::getInstance();
$conn = $db->getConnection();

// Process request approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve']) && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        
        // Update the status to approved
        $stmt = $conn->prepare("UPDATE password_resets SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'Password reset request has been approved.');
        } else {
            setAlert('danger', 'Failed to approve request.');
        }
    } elseif (isset($_POST['reject']) && isset($_POST['request_id']) && isset($_POST['rejection_reason'])) {
        $request_id = intval($_POST['request_id']);
        $rejection_reason = sanitizeInput($_POST['rejection_reason']);
        
        // Update the status to rejected with a reason
        $stmt = $conn->prepare("UPDATE password_resets SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $rejection_reason, $request_id);
        
        if ($stmt->execute()) {
            setAlert('success', 'Password reset request has been rejected.');
        } else {
            setAlert('danger', 'Failed to reject request.');
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: reset_requests.php");
    exit();
}

// Get all pending reset requests
$stmt = $conn->prepare("
    SELECT pr.*, u.name, u.email 
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.status = 'pending' AND pr.expires_at > NOW() AND pr.used = 0
    ORDER BY pr.created_at DESC
");
$stmt->execute();
$pending_requests = $stmt->get_result();

// Include header
$pageTitle = "Password Reset Requests";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Password Reset Requests</h2>
    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- Workflow explanation for admins -->
<div class="alert alert-info mb-4">
    <h5><i class="fas fa-info-circle"></i> Identity Verification Process</h5>
    <p>When users request a password reset with identity verification, they must wait for administrator approval before they can reset their password.</p>
    <p>Please review each request carefully and either approve or reject it based on the provided identity proof.</p>
</div>

<!-- Pending Requests -->
<div class="card mb-4">
    <div class="card-header bg-warning text-white">
        <h5 class="mb-0">Pending Identity Verification Requests</h5>
    </div>
    <div class="card-body">
        <?php if ($pending_requests->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Requested On</th>
                            <th>Identity Proof</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $pending_requests->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($request['name']) ?></td>
                                <td><?= htmlspecialchars($request['email']) ?></td>
                                <td><?= formatDate($request['created_at']) ?></td>
                                <td>
                                    <?php if (!empty($request['identity_proof'])): ?>
                                        <a href="../<?= $request['identity_proof'] ?>" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-id-card"></i> View ID
                                        </a>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">No proof provided</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="post" action="" class="d-inline">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="approve" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#rejectModal<?= $request['id'] ?>">
                                        Reject
                                    </button>
                                    
                                    <!-- Rejection Modal -->
                                    <div class="modal fade" id="rejectModal<?= $request['id'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title">Reject Password Reset Request</h5>
                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                        <span aria-hidden="true">&times;</span>
                                                    </button>
                                                </div>
                                                <form method="post" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                        <div class="form-group">
                                                            <label for="rejection_reason<?= $request['id'] ?>">Reason for Rejection:</label>
                                                            <textarea class="form-control" id="rejection_reason<?= $request['id'] ?>" name="rejection_reason" rows="3" required></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="reject" class="btn btn-danger">Reject</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No pending password reset requests.</p>
        <?php endif; ?>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
