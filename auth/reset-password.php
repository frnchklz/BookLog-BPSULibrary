<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';


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

$message = '';
$step = isset($_GET['token']) ? 'reset' : 'request';
$token = isset($_GET['token']) ? $_GET['token'] : '';
// Allow selecting flow via GET (e.g., reset-password.php?via=email)
$via_default = isset($_GET['via']) && $_GET['via'] === 'email' ? 'email' : 'identity';

// Handle password reset request
// Support two flows: via=identity (existing flow requiring ID upload and admin approval)
// and via=email (simple email link sent immediately, user can reset right away)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 'request') {
    $email = sanitizeInput($_POST['email']);
    $via = isset($_POST['via']) ? sanitizeInput($_POST['via']) : 'identity';

    if (empty($email)) {
        $message = '<div class="alert alert-danger">Please enter your email address</div>';
    } else {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            if ($via === 'email') {
                // Create a token and mark as approved so the user can reset immediately
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $status = 'approved';

                $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at, status, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->bind_param("isss", $user_id, $token, $expires, $status);

                if ($stmt->execute()) {
                    // Send email with reset link
                    $reset_link = BASE_URL . 'auth/reset-password.php?token=' . $token;
                    $subject = 'Password Reset Request';
                    $body = "Hello " . $user['name'] . ",\n\n";
                    $body .= "We received a request to reset your password. Click the link below to reset it:\n\n";
                    $body .= $reset_link . "\n\n";
                    $body .= "This link will expire in 24 hours. If you did not request this, please ignore this email.\n\n";
                    $body .= "Regards,\n" . SITE_NAME . " Team";

                      $mail = new PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'booklogbpsulibrary@gmail.com'; // your Gmail
                    $mail->Password   = 'yagy pjtw xsjh dtiq'; // your App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('booklogbpsulibrary@gmail.com', 'BookLog: BPSU Library');
                    $mail->addAddress($email);
                    $mail->isHTML(false); // text-only message
                    $mail->Subject = $subject;
                    $mail->Body    = $body;

                   $mail->send();
                     } catch (Exception $e) {
                     error_log("Email error: " . $mail->ErrorInfo);
                     }


                    $message = '<div class="alert alert-success">If your email exists in our system, a password reset link has been sent.</div>';
                } else {
                    $message = '<div class="alert alert-danger">Database error. Please try again later.</div>';
                }
            } else {
                // identity flow (existing behavior)
                $identityVerification = false;
                $identityProofPath = '';

                if(!isset($_FILES['identity_proof']) || $_FILES['identity_proof']['error'] != 0) {
                    $message = '<div class="alert alert-danger">Identity verification is required. Please upload a photo ID.</div>';
                } else {
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                    $filename = $_FILES['identity_proof']['name'];
                    $tmp_name = $_FILES['identity_proof']['tmp_name'];
                    $size = $_FILES['identity_proof']['size'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if(!in_array($ext, $allowed)) {
                        $message = '<div class="alert alert-danger">Only JPG, JPEG, PNG and PDF files are allowed!</div>';
                    } else if($size > 5000000) { // 5MB max
                        $message = '<div class="alert alert-danger">File size must be less than 5MB!</div>';
                    } else {
                        $uploadDir = '../uploads/identity_proofs/';
                        if(!file_exists($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $newFilename = 'id_proof_' . $user_id . '_' . time() . '.' . $ext;
                        $uploadPath = $uploadDir . $newFilename;

                        if(move_uploaded_file($tmp_name, $uploadPath)) {
                            $identityVerification = true;
                            $identityProofPath = 'uploads/identity_proofs/' . $newFilename;
                        } else {
                            $message = '<div class="alert alert-danger">Failed to upload identity proof. Please try again.</div>';
                        }
                    }
                }

                if(empty($message) && $identityVerification) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    $status = 'pending';

                    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at, identity_proof, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->bind_param("issss", $user_id, $token, $expires, $identityProofPath, $status);

                    if($stmt->execute()) {
                        setAlert('info', 'Your password reset request with identity verification has been submitted. An administrator will review your request shortly.');
                        header("Location: verification_status.php?token=" . $token);
                        exit();
                    } else {
                        $message = '<div class="alert alert-danger">Database error. Please try again later.</div>';
                    }
                }
            }
        } else {
            // Don't reveal that the email doesn't exist for security
            $message = '<div class="alert alert-success">If your email exists in our system, you will receive a password reset link shortly.</div>';
        }
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $step == 'reset') {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = '<div class="alert alert-danger">Please enter both password fields</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert alert-danger">Passwords do not match</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert alert-danger">Password must be at least 6 characters</div>';
    } else {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        // Check if token is valid and approved
        $stmt = $conn->prepare("
            SELECT pr.user_id 
            FROM password_resets pr
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0 AND pr.status = 'approved'
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $reset = $result->fetch_assoc();
            $user_id = $reset['user_id'];
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                // Mark token as used
                $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                $message = '<div class="alert alert-success">Your password has been successfully reset. <a href="login.php">Click here to login</a></div>';
                $step = 'success'; // Prevent showing the form again
            } else {
                $message = '<div class="alert alert-danger">An error occurred. Please try again.</div>';
            }
        } else {
            // Check if token exists but is pending/rejected
            $stmt = $conn->prepare("
                SELECT status FROM password_resets 
                WHERE token = ? AND expires_at > NOW() AND used = 0
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $reset = $result->fetch_assoc();
                if ($reset['status'] == 'pending') {
                    setAlert('warning', 'Your reset request is still pending administrator approval.');
                    header("Location: verification_status.php?token=" . $token);
                    exit();
                } else if ($reset['status'] == 'rejected') {
                    setAlert('danger', 'Your reset request has been rejected by an administrator.');
                    header("Location: verification_status.php?token=" . $token);
                    exit();
                }
            } else {
                $message = '<div class="alert alert-danger">Invalid or expired token. Please request a new password reset.</div>';
                $step = 'request'; // Go back to request step
            }
        }
    }
}

// Validate token if present in URL - CRITICAL CHECK
if ($step == 'reset' && !empty($token)) {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? AND expires_at > NOW() AND used = 0
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $reset = $result->fetch_assoc();
        
        // STRICT ENFORCEMENT: Always redirect to verification status for any token that isn't approved
        if ($reset['status'] != 'approved') {
            $status_message = $reset['status'] == 'pending' ? 
                'Your reset request requires administrator approval before you can reset your password.' :
                'Your reset request was rejected by an administrator.';
                
            $_SESSION['alert'] = [
                'type' => ($reset['status'] == 'pending' ? 'warning' : 'danger'),
                'message' => $status_message
            ];
            
            // Unconditional redirect for any non-approved status
            header("Location: verification_status.php?token=" . $token);
            exit();
        }
        // Only approved requests will continue past this point
    } else {
        $message = '<div class="alert alert-danger">Invalid or expired token. Please request a new password reset.</div>';
        $step = 'request';
    }
}

// Include header
$pageTitle = "Reset Password";
include('../includes/header.php');
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Reset Password</h4>
            </div>
            <div class="card-body">
                <?= $message ?>
                
                <?php if ($step == 'request'): ?>
                    <p>Enter your email address below to receive password reset instructions.</p>
                    <?php if ($via_default === 'email'): ?>
                        <form method="post" action="">
                            <input type="hidden" name="via" value="email">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Send Reset Link via Email</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <form method="post" action="" enctype="multipart/form-data">
                            <input type="hidden" name="via" value="identity">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="form-group">
                                <label for="identity_proof">Identity Verification <span class="text-danger">*</span></label>
                                <input type="file" class="form-control-file" id="identity_proof" name="identity_proof" required>
                                <small class="form-text text-muted">
                                    Upload a photo ID or document to verify your identity.<br>
                                    Accepted formats: JPG, JPEG, PNG, PDF. Max size: 5MB.<br>
                                    <strong>Note: All password reset requests require administrator approval.</strong>
                                </small>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">Send Reset Request</button>
                            </div>
                        </form>
                        <div class="mt-2 text-center">
                            <a href="reset-password.php?via=email">Or send a reset link via email (no ID required)</a>
                        </div>
                    <?php endif; ?>
                <?php elseif ($step == 'reset'): ?>
                    <p>Enter your new password below.</p>
                    <form method="post" action="">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>
                <?php elseif ($step == 'success'): ?>
                    <div class="text-center">
                        <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                        <h4>Password Reset Complete</h4>
                        <p>Your password has been successfully reset.</p>
                        <a href="login.php" class="btn btn-primary">Login with New Password</a>
                    </div>
                <?php endif; ?>
                
                <div class="mt-3 text-center">
                    <p><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('../includes/footer.php'); ?>
