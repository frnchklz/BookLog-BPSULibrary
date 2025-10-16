<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

startSecureSession();
requireAdminOrHeadLibrarian();

// Initialize variables
$request = null;
$error = null;

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = "Invalid request ID";
} else {
    $request_id = intval($_GET['id']);
    
    // Get request details with user information
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT pr.*, u.name, u.email 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.id = ?
    ");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = "Request not found";
    } else {
        $request = $result->fetch_assoc();
        
        // Check if identity proof exists
        if (empty($request['identity_proof'])) {
            $error = "No identity proof was provided for this request";
        } else {
            // Get file path and check if it exists
            $proof_path = "../" . $request['identity_proof'];
            if (!file_exists($proof_path)) {
                $error = "Identity proof file not found";
            }
        }
    }
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['approve']) && isset($_POST['request_id'])) {
        $req_id = intval($_POST['request_id']);
        
        $stmt = $conn->prepare("UPDATE password_resets SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $req_id);
        
        if ($stmt->execute()) {
            // Send notification to user about approval
            $stmt = $conn->prepare("SELECT token FROM password_resets WHERE id = ?");
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
            $token = $stmt->get_result()->fetch_assoc()['token'];
            
            // Redirect to notification script
            header("Location: ../auth/notify_reset.php?token=" . $token . "&action=approved");
            exit();
        } else {
            $error = "Failed to approve request";
        }
    } elseif (isset($_POST['reject']) && isset($_POST['request_id']) && isset($_POST['rejection_reason'])) {
        $req_id = intval($_POST['request_id']);
        $reason = sanitizeInput($_POST['rejection_reason']);
        
        if (empty($reason)) {
            $error = "Please provide a reason for rejection";
        } else {
            $stmt = $conn->prepare("UPDATE password_resets SET status = 'rejected', rejection_reason = ? WHERE id = ?");
            $stmt->bind_param("si", $reason, $req_id);
            
            if ($stmt->execute()) {
                // Send notification to user about rejection
                $stmt = $conn->prepare("SELECT token FROM password_resets WHERE id = ?");
                $stmt->bind_param("i", $req_id);
                $stmt->execute();
                $token = $stmt->get_result()->fetch_assoc()['token'];
                
                // Redirect to notification script
                header("Location: ../auth/notify_reset.php?token=" . $token . "&action=rejected");
                exit();
            } else {
                $error = "Failed to reject request";
            }
        }
    }
}

// Include header
$pageTitle = "View Identity Proof";
include('../includes/header.php');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>View Identity Proof</h2>
    <a href="reset_requests.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Requests</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <p><?= $error ?></p>
        <a href="reset_requests.php" class="btn btn-primary">Return to Requests</a>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Request Details</h5>
                </div>
                <div class="card-body">
                    <h5><?= htmlspecialchars($request['name']) ?></h5>
                    <p class="text-muted"><?= htmlspecialchars($request['email']) ?></p>
                    
                    <ul class="list-group list-group-flush mt-3">
                        <li class="list-group-item">
                            <strong>Request Date:</strong> <?= formatDate($request['created_at']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Expires:</strong> <?= formatDate($request['expires_at']) ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Status:</strong> 
                            <?php if ($request['status'] == 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php elseif ($request['status'] == 'approved'): ?>
                                <span class="badge badge-success">Approved</span>
                            <?php elseif ($request['status'] == 'rejected'): ?>
                                <span class="badge badge-danger">Rejected</span>
                            <?php endif; ?>
                        </li>
                        <?php if ($request['status'] == 'rejected' && !empty($request['rejection_reason'])): ?>
                            <li class="list-group-item">
                                <strong>Rejection Reason:</strong><br>
                                <?= nl2br(htmlspecialchars($request['rejection_reason'])) ?>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <?php if ($request['status'] == 'pending'): ?>
                        <div class="alert alert-warning mt-4">
                            <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Action Required</h6>
                            <p class="mb-0">This user cannot reset their password until you approve their verification.</p>
                        </div>
                        
                        <div class="mt-4">
                            <form method="post" action="" class="mb-2">
                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                <button type="submit" name="approve" class="btn btn-success btn-block">Approve Request</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-block" data-toggle="modal" data-target="#rejectModal">
                                Reject Request
                            </button>
                        </div>
                        
                        <!-- Reject Modal -->
                        <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-hidden="true">
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
                                                <label for="rejection_reason">Reason for Rejection</label>
                                                <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="3" required></textarea>
                                                <small class="form-text text-muted">Please provide a reason why this request is being rejected. This will be shown to the user.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <button type="submit" name="reject" class="btn btn-danger">Confirm Rejection</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Identity Proof Document</h5>
                </div>
                <div class="card-body text-center">
                    <?php
                    $file_ext = pathinfo($request['identity_proof'], PATHINFO_EXTENSION);
                    $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif']);
                    $is_pdf = strtolower($file_ext) == 'pdf';
                    
                    if ($is_image):
                    ?>
                        <img src="<?= BASE_URL . $request['identity_proof'] ?>" alt="Identity Proof" class="img-fluid">
                    <?php elseif ($is_pdf): ?>
                        <div class="embed-responsive embed-responsive-16by9">
                            <iframe class="embed-responsive-item" src="<?= BASE_URL . $request['identity_proof'] ?>" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <p>This file type cannot be previewed directly.</p>
                        <a href="<?= BASE_URL . $request['identity_proof'] ?>" class="btn btn-primary" target="_blank">Download File</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include('../includes/footer.php'); ?>
