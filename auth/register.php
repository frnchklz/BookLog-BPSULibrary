<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';
require_once '../includes/image_urls.php';

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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $name)) {
        $errors[] = "Name must contain only letters and spaces";
    }

    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } elseif (!preg_match('/^[A-Za-z0-9._%+-]+@bpsu\.edu\.ph$/', $email)) {
        $errors[] = "Only BPSU email addresses (@bpsu.edu.ph) are allowed.";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    // Check if email already exists
    if (empty($errors)) {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $errors[] = "Email already exists. Please use a different email or login.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Set role as user (default) and status as active
            $role = 'user';
            $status = 'active';

            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $name, $email, $hashed_password, $role, $status);

            if ($stmt->execute()) {
                // Registration successful
                setAlert('success', 'Registration successful! You can now login.');
                header("Location: login.php");
                exit();
            } else {
                $errors[] = "Registration failed. Please try again.";
            }
        }
    }
}

// Include header
$pageTitle = "Register";
include('../includes/header.php');
?>

<?php if (defined('AUTH_BACKGROUND_URL') && AUTH_BACKGROUND_URL): ?>
    <style>
        body {
            background-image: url('<?= AUTH_BACKGROUND_URL ?>');
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
        }
    </style>
<?php endif; ?>

<div class="auth-page">
    <div class="auth-container container">
        <div class="auth-header">
            <h2 class="auth-title">BATAAN PENINSULA STATE UNIVERSITY LIBRARY</h2>
            <p class="auth-subtitle">WELCOME TO THE BPSU LIBRARY!</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card auth-card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Create an Account</h4>
                    </div>
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
                        
                        <form method="post" action="">
                                                        <div class="form-group">
                                                                <label for="name">Full Name</label>
                                                                <input type="text" class="form-control" id="name" name="name" value="<?= isset($name) ? $name : '' ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                                <label for="email">Email Address</label>
                                                                <input type="text" class="form-control" id="email" name="email" value="<?= isset($email) ? $email : '' ?>" required>
                                                        </div>
                                                        <div class="form-group">
                                                                <label for="password">Password</label>
                                                                <input type="password" class="form-control" id="password" name="password" required>
                                                                <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                                        </div>
                                                        <div class="form-group">
                                                                <label for="confirm_password">Confirm Password</label>
                                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                        </div>
                                                        <div class="form-group form-check">
                                                                <input type="checkbox" class="form-check-input" id="agree_terms" name="agree_terms" required>
                                                                <label class="form-check-label" for="agree_terms">
                                                                        I agree to the <a href="#" data-toggle="modal" data-target="#termsModal">Terms and Conditions</a> and <a href="#" data-toggle="modal" data-target="#privacyModal">Data Privacy Act</a>.
                                                                </label>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary btn-block">Register</button>
                                                </form>
                                                <!-- Terms and Conditions Modal -->
                                                <div class="modal fade" id="termsModal" tabindex="-1" role="dialog" aria-labelledby="termsModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <ol>
                                                                    <li><strong>Use of the System:</strong> This service is provided to students, faculty, and staff for borrowing and returning library materials. Unauthorized users are prohibited.</li>
                                                                    <li><strong>Borrowing Rules:</strong> Follow library borrowing policies. Return items by their due date. Fines may apply for overdue, lost, or damaged items.</li>
                                                                    <li><strong>Account Responsibility:</strong> Keep your account credentials confidential. You are responsible for actions performed under your account.</li>
                                                                    <li><strong>Prohibited Conduct:</strong> Tampering with the system, attempting to bypass security, or misusing resources is not allowed and may lead to disciplinary action.</li>
                                                                    <li><strong>Changes to Terms:</strong> The library may update these terms at any time; continued use indicates acceptance of updates.</li>
                                                                </ol>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Data Privacy Act Modal -->
                                                <div class="modal fade" id="privacyModal" tabindex="-1" role="dialog" aria-labelledby="privacyModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="privacyModalLabel">Data Privacy Act</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <ol>
                                                                    <li><strong>Data Collected:</strong> We collect necessary information (e.g., name, email, student ID, borrowing records) to provide library services.</li>
                                                                    <li><strong>Purpose:</strong> Data is processed for account administration, lending records, notifications, and legitimate library operations.</li>
                                                                    <li><strong>Security & Retention:</strong> Personal data is secured and retained only as long as necessary for the stated purposes.</li>
                                                                    <li><strong>Sharing:</strong> We do not sell personal data. Information may be shared with authorized parties when required by law or to provide services, with safeguards in place.</li>
                                                                    <li><strong>Your Rights:</strong> You may request access, correction, or deletion of your data. Contact the library for requests or concerns.</li>
                                                                </ol>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                        <div class="mt-3 text-center">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            
                            <div class="thank-you-message">
                                <p><strong>Thank you for registering with the BPSU Library!</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<?php include('../includes/footer.php'); ?>
