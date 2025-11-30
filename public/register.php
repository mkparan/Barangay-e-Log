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
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (!$cin || !$first || !$last) {
        $errors[] = "Barangay ID, first and last name are required.";
    } elseif (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    } else {
        // Check if password_hash column exists, if not add it
        $checkColumn = $db->query("SHOW COLUMNS FROM citizens LIKE 'password_hash'");
        if ($checkColumn->num_rows == 0) {
            $db->query("ALTER TABLE citizens ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        }
        
        $stmt = $db->prepare("SELECT citizen_id FROM citizens WHERE cin = ?");
        $stmt->bind_param('s', $cin);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Barangay ID already registered.";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO citizens (cin, first_name, middle_name, last_name, birth_date, gender, contact_number, email, address, gov_affiliations, password_hash, is_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)");
            $birth = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
            $middle = trim($_POST['middle_name'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $addr = trim($_POST['address'] ?? '');
            $aff = trim($_POST['gov_aff'] ?? '');
            $stmt->bind_param('sssssssssss', $cin, $first, $middle, $last, $birth, $gender, $contact, $email, $addr, $aff, $passwordHash);
            if ($stmt->execute()) {
                audit_log($cin, null, 'citizen_register', 'citizens', $stmt->insert_id);
                header('Location: login.php?registered=1');
                exit;
            } else {
                $errors[] = "DB Error: " . $stmt->error;
            }
        }
    }
}

$page_title = "Register - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
?>
<div class="container-box col-lg-6 mx-auto">
  <h3 class="mb-3">Citizen Registration</h3>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>

  <form method="post" novalidate>
    <div class="mb-3">
      <label class="form-label">Barangay ID <span class="text-danger">*</span></label>
      <input class="form-control" name="cin" required placeholder="Enter your Barangay ID">
      <small class="text-muted">Your account will need to be verified by barangay officials before you can book appointments.</small>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">First name</label>
        <input class="form-control" name="first_name" required>
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Last name</label>
        <input class="form-control" name="last_name" required>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label">Middle name</label>
      <input class="form-control" name="middle_name">
    </div>
    <div class="mb-3">
      <label class="form-label">Birth date</label>
      <input type="date" class="form-control" name="birth_date">
    </div>
    <div class="mb-3">
      <label class="form-label">Gender <span class="text-danger">*</span></label>
      <select class="form-select" name="gender" required>
        <option value="">Select Gender</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Other">Other</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Contact number</label>
      <input class="form-control" name="contact_number">
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input class="form-control" name="email" type="email">
    </div>
    <div class="mb-3">
      <label class="form-label">Address</label>
      <textarea class="form-control" name="address"></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Gov affiliations (4Ps, PhilHealth, PWD, Senior)</label>
      <input class="form-control" name="gov_aff">
    </div>
    
    <hr class="my-4">
    <h5 class="mb-3">Account Security</h5>
    
    <div class="mb-3">
      <label class="form-label">Password <span class="text-danger">*</span></label>
      <div class="input-group">
        <input type="password" class="form-control" name="password" id="registerPassword" required placeholder="Enter your password" minlength="8">
        <button class="btn btn-outline-secondary" type="button" id="toggleRegisterPassword">
          <i class="bi bi-eye" id="registerPasswordIcon"></i>
        </button>
      </div>
      <small class="text-muted">Minimum 8 characters</small>
    </div>
    <div class="mb-3">
      <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
      <div class="input-group">
        <input type="password" class="form-control" name="confirm_password" id="registerConfirmPassword" required placeholder="Confirm your password" minlength="8">
        <button class="btn btn-outline-secondary" type="button" id="toggleRegisterConfirmPassword">
          <i class="bi bi-eye" id="registerConfirmPasswordIcon"></i>
        </button>
      </div>
    </div>

    <div class="d-grid">
      <button class="btn btn-primary">Register</button>
    </div>
    <p class="mt-2"><a href="login.php">Already registered? Login</a></p>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function setupPasswordToggle(toggleBtnId, passwordInputId, iconId) {
        const toggleBtn = document.getElementById(toggleBtnId);
        const passwordInput = document.getElementById(passwordInputId);
        const passwordIcon = document.getElementById(iconId);
        
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
    }
    
    setupPasswordToggle('toggleRegisterPassword', 'registerPassword', 'registerPasswordIcon');
    setupPasswordToggle('toggleRegisterConfirmPassword', 'registerConfirmPassword', 'registerConfirmPasswordIcon');
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
