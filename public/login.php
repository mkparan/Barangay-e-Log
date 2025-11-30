<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
redirect_if_admin_logged_in();
redirect_if_citizen_logged_in();

$db = db_connect();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cin = trim($_POST['cin'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($cin)) {
        $errors[] = "Barangay ID is required.";
    } elseif (empty($password)) {
        $errors[] = "Password is required.";
    } else {
        // Check if password_hash column exists, if not add it
        $checkColumn = $db->query("SHOW COLUMNS FROM citizens LIKE 'password_hash'");
        if ($checkColumn->num_rows == 0) {
            $db->query("ALTER TABLE citizens ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
        
        $stmt = $db->prepare("SELECT citizen_id, cin, first_name, last_name, is_active, is_verified, password_hash FROM citizens WHERE cin = ? LIMIT 1");
        $stmt->bind_param('s', $cin);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            // Check if account is banned
            if (!$row['is_active']) {
                $errors[] = "Your account has been banned. Please contact the barangay office.";
            } elseif (empty($row['password_hash'])) {
                // If no password is set, allow login (for backward compatibility with existing accounts)
                $_SESSION['citizen'] = [
                    'citizen_id' => $row['citizen_id'],
                    'cin' => $row['cin'],
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'is_verified' => (bool)$row['is_verified']
                ];
                audit_log($row['cin'], null, 'citizen_login', 'citizens', $row['citizen_id']);
                header('Location: dashboard.php');
                exit;
            } elseif (!password_verify($password, $row['password_hash'])) {
                $errors[] = "Invalid Barangay ID or password.";
            } else {
                $_SESSION['citizen'] = [
                    'citizen_id' => $row['citizen_id'],
                    'cin' => $row['cin'],
                    'name' => $row['first_name'] . ' ' . $row['last_name'],
                    'is_verified' => (bool)$row['is_verified']
                ];
                audit_log($row['cin'], null, 'citizen_login', 'citizens', $row['citizen_id']);
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $errors[] = "Invalid Barangay ID or password.";
        }
    }
}

$page_title = "Citizen Login";
require_once __DIR__ . '/../inc/header.php';
?>

<br>
<br>
<div class="row login-layout g-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="container-box shadow-sm h-100">
                <br>
            <h3 class="mb-3">Citizen Login</h3>

            <?php foreach($errors as $e): ?>
                <div class="alert alert-danger"><?= esc($e) ?></div>
            <?php endforeach; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Barangay ID</label>
                    <input type="text" class="form-control" name="cin" required placeholder="Enter your Barangay ID">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="password" id="loginPassword" required placeholder="Enter your password">
                        <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword">
                            <i class="bi bi-eye" id="loginPasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button class="btn btn-primary w-100">Login</button>
            </form>

            <div class="mt-3 text-center">
                <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Forgot Password?</a>
            </div>
            <p class="mt-2 text-center">
                <a href="register.php">Don't have an account? Register</a>
            </p>
        </div>
    </div>

    <div class="col-12 col-md-6 col-lg-7 ms-lg-auto d-flex align-items-center justify-content-center">
        <img src="/elog_barangay/public/assets/images/logo.png" alt="Barangay Duangan Logo" class="logo-large">
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="forgotPasswordModalLabel">
                    <i class="bi bi-key me-2"></i>Forgot Password?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">If you have forgotten your password, please visit the barangay hall in person to reset it.</p>
                
                <div class="alert alert-info mb-3">
                    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Password Reset Process</h6>
                    <ol class="mb-0">
                        <li>Visit the barangay hall during office hours</li>
                        <li>Bring any of the following for verification:
                            <ul>
                                <li>Valid government-issued ID</li>
                                <li>Proof of residence</li>
                                <li>Your Barangay ID</li>
                            </ul>
                        </li>
                        <li>Request a password reset from the staff</li>
                        <li>You will receive a temporary password</li>
                        <li>Log in and change your password immediately</li>
                    </ol>
                </div>
                
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-building me-2"></i>Barangay Hall Information</h6>
                        <p class="mb-1"><strong>Address:</strong> Barangay Duangan</p>
                        <p class="mb-1"><strong>Contact:</strong> (02) 123-4567</p>
                        <p class="mb-0"><strong>Office Hours:</strong> Monday - Friday: 8:00 AM - 5:00 PM, Saturday: 8:00 AM - 12:00 PM</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleLoginPassword');
    const passwordInput = document.getElementById('loginPassword');
    const passwordIcon = document.getElementById('loginPasswordIcon');
    
    if (toggleBtn && passwordInput && passwordIcon) {
        toggleBtn.addEventListener('click', function() {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('bi-eye');
                passwordIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('bi-eye-slash');
                passwordIcon.classList.add('bi-eye');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
