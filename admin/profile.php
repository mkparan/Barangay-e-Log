<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$db = db_connect();
$user = $_SESSION['user'];

// Get full user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$errors = [];
$success = false;

// Handle profile picture upload separately (BEFORE header output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../public/uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['profile_picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        $_SESSION['profile_error'] = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.";
        header('Location: profile.php');
        exit;
    } elseif ($file['size'] > $maxSize) {
        $_SESSION['profile_error'] = "File size too large. Maximum size is 5MB.";
        header('Location: profile.php');
        exit;
    } else {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'admin_' . $user['user_id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists
            if (!empty($profile['profile_picture']) && file_exists(__DIR__ . '/../public/' . $profile['profile_picture'])) {
                @unlink(__DIR__ . '/../public/' . $profile['profile_picture']);
            }
            
            $relativePath = 'uploads/profiles/' . $filename;
            $updPic = $db->prepare("UPDATE users SET profile_picture=? WHERE user_id=?");
            $updPic->bind_param('si', $relativePath, $user['user_id']);
            if ($updPic->execute()) {
                audit_log($user['username'], $user['user_id'], 'profile_picture_update', 'users', $user['user_id']);
                header('Location: profile.php?pic_updated=1');
                exit;
            } else {
                $_SESSION['profile_error'] = "DB Error: " . $updPic->error;
                header('Location: profile.php');
                exit;
            }
        } else {
            $_SESSION['profile_error'] = "Failed to upload profile picture.";
            header('Location: profile.php');
            exit;
        }
    }
}

// Handle profile update (separate from password change)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['upload_picture']) && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$fullName) {
        $errors[] = "Full name is required.";
    } else {
        $upd = $db->prepare("UPDATE users SET full_name=?, contact_number=?, email=? WHERE user_id=?");
        $upd->bind_param('sssi', $fullName, $contact, $email, $user['user_id']);
        if ($upd->execute()) {
            audit_log($user['username'], $user['user_id'], 'profile_update', 'users', $user['user_id']);
            $_SESSION['user']['full_name'] = $fullName;
            $success = true;
            // Refresh profile data
            $stmt->execute();
            $profile = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "DB Error: " . $upd->error;
        }
    }
}

// Handle password change (separate from profile update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // First, verify current password
    if (empty($currentPassword)) {
        $errors[] = "Current password is required to change your password.";
    } elseif (empty($newPassword)) {
        $errors[] = "New password is required.";
    } else {
        // Get current password hash from database
        $checkStmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $checkStmt->bind_param('i', $user['user_id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkRow = $checkResult->fetch_assoc()) {
            if (!password_verify($currentPassword, $checkRow['password_hash'])) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($newPassword) < 8) {
                $errors[] = "New password must be at least 8 characters long.";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "New passwords do not match.";
            } else {
                // Verify new password is different from current password
                if (password_verify($newPassword, $checkRow['password_hash'])) {
                    $errors[] = "New password must be different from your current password.";
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updPass = $db->prepare("UPDATE users SET password_hash=? WHERE user_id=?");
                    $updPass->bind_param('si', $hash, $user['user_id']);
                    if ($updPass->execute()) {
                        audit_log($user['username'], $user['user_id'], 'password_change', 'users', $user['user_id']);
                        $success = true;
                        // Clear password fields on success
                        $_POST['current_password'] = '';
                        $_POST['new_password'] = '';
                        $_POST['confirm_password'] = '';
                    } else {
                        $errors[] = "DB Error: " . $updPass->error;
                    }
                }
            }
        } else {
            $errors[] = "Unable to verify current password. Please try again.";
        }
    }
}

// Check for session errors
if (!empty($_SESSION['profile_error'])) {
    $errors[] = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}

$page_title = "Profile - Admin";
require_once __DIR__ . '/../inc/header.php';
?>

<?php if ($success || !empty($_GET['pic_updated'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?= !empty($_GET['pic_updated']) ? 'Profile picture updated successfully!' : 'Profile updated successfully!' ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="container-box">
      <h4 class="mb-3">Profile Information</h4>
      
      <?php foreach($errors as $e): ?>
        <div class="alert alert-danger"><?= esc($e) ?></div>
      <?php endforeach; ?>

      <div class="row g-3 mb-4">
        <div class="col-12 text-center">
          <div class="mb-3 position-relative d-inline-block">
            <?php 
            $profilePic = !empty($profile['profile_picture']) ? $profile['profile_picture'] : null;
            $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150"><circle cx="75" cy="75" r="75" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="60" fill="#6c757d">' . strtoupper(substr($profile['full_name'] ?? 'U', 0, 1)) . '</text></svg>');
            ?>
            <img id="profileImagePreview" src="<?= $profilePic ? esc('/elog_barangay/public/' . $profilePic) : $defaultPic ?>" 
                 alt="Profile Picture" 
                 class="rounded-circle border" 
                 class="profile-picture-xl">
            <div id="uploadOverlay" class="position-absolute top-0 start-0 w-100 h-100 rounded-circle d-none align-items-center justify-content-center upload-overlay">
              <div class="text-white text-center">
                <div class="spinner-border spinner-border-sm mb-2" role="status"></div>
                <div class="small">Uploading...</div>
              </div>
            </div>
          </div>
          <form id="pictureUploadForm" method="post" enctype="multipart/form-data" class="d-inline">
            <input type="hidden" name="upload_picture" value="1">
            <label for="profile_picture" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-camera me-2"></i>Change Profile Picture
            </label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="d-none">
          </form>
          <small class="text-muted d-block mt-2">Max 5MB. JPEG, PNG, GIF, or WebP</small>
        </div>
      </div>
      
      <form method="post">
        <input type="hidden" name="update_profile" value="1">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" value="<?= esc($profile['username']) ?>" disabled>
            <small class="text-muted">Username cannot be changed</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <input type="text" class="form-control" value="<?= esc(ucfirst($profile['role'])) ?>" disabled>
            <small class="text-muted">Role cannot be changed</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" value="<?= esc($profile['full_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" value="<?= esc($profile['contact_number'] ?? '') ?>">
          </div>
          <div class="col-md-12">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= esc($profile['email'] ?? '') ?>">
          </div>
        </div>
        
        <div class="mt-4">
          <button type="submit" class="btn btn-primary">Update Profile</button>
        </div>
      </form>
      
      <hr class="my-4">
      
      <form method="post">
        <input type="hidden" name="change_password" value="1">
        <h5 class="mb-3">Change Password</h5>
        <div class="row g-3">
          <div class="col-md-12">
            <label class="form-label">Current Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" id="current_password" value="">
              <button class="btn btn-outline-secondary" type="button" id="toggleCurrentPassword">
                <i class="bi bi-eye" id="currentPasswordIcon"></i>
              </button>
            </div>
            <small class="text-muted">Required to change your password</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="new_password" class="form-control" placeholder="Enter new password" id="new_password" value="">
              <button class="btn btn-outline-secondary" type="button" id="toggleNewPassword">
                <i class="bi bi-eye" id="newPasswordIcon"></i>
              </button>
            </div>
            <small class="text-muted">Minimum 8 characters</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" id="confirm_password" value="">
              <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                <i class="bi bi-eye" id="confirmPasswordIcon"></i>
              </button>
            </div>
          </div>
        </div>
        
        <div class="mt-4">
          <button type="submit" class="btn btn-warning">Change Password</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="container-box text-center mb-4">
      <?php 
      $profilePic = !empty($profile['profile_picture']) ? $profile['profile_picture'] : null;
      $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><circle cx="60" cy="60" r="60" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="50" fill="#6c757d">' . strtoupper(substr($profile['full_name'] ?? 'U', 0, 1)) . '</text></svg>');
      ?>
      <img src="<?= $profilePic ? esc('/elog_barangay/public/' . $profilePic) : $defaultPic ?>" 
           alt="Profile Picture" 
           class="rounded-circle border mb-3" 
           class="profile-picture-lg">
      <h5 class="mb-1"><?= esc($profile['full_name']) ?></h5>
      <p class="text-muted small mb-0"><?= esc(ucfirst($profile['role'])) ?></p>
    </div>
    <div class="container-box">
      <h5 class="mb-3">Account Summary</h5>
      <dl class="row">
        <dt class="col-sm-5">Username:</dt>
        <dd class="col-sm-7"><?= esc($profile['username']) ?></dd>
        
        <dt class="col-sm-5">Full Name:</dt>
        <dd class="col-sm-7"><?= esc($profile['full_name']) ?></dd>
        
        <dt class="col-sm-5">Role:</dt>
        <dd class="col-sm-7"><?= esc(ucfirst($profile['role'])) ?></dd>
        
        <dt class="col-sm-5">Member Since:</dt>
        <dd class="col-sm-7"><?= esc(date('M d, Y', strtotime($profile['created_at']))) ?></dd>
        
        <?php if (!empty($profile['email'])): ?>
        <dt class="col-sm-5">Email:</dt>
        <dd class="col-sm-7"><?= esc($profile['email']) ?></dd>
        <?php endif; ?>
        
        <?php if (!empty($profile['contact_number'])): ?>
        <dt class="col-sm-5">Contact:</dt>
        <dd class="col-sm-7"><?= esc($profile['contact_number']) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const fileInput = document.getElementById('profile_picture');
  const uploadForm = document.getElementById('pictureUploadForm');
  const uploadOverlay = document.getElementById('uploadOverlay');
  const imagePreview = document.getElementById('profileImagePreview');
  
  if (fileInput && uploadForm) {
    fileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      if (!file) return;
      
      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        alert('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        fileInput.value = '';
        return;
      }
      
      // Validate file size (5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size too large. Maximum size is 5MB.');
        fileInput.value = '';
        return;
      }
      
      // Show preview
      const reader = new FileReader();
      reader.onload = function(e) {
        imagePreview.src = e.target.result;
      };
      reader.readAsDataURL(file);
      
      // Show upload overlay
      uploadOverlay.classList.remove('d-none');
      uploadOverlay.classList.add('d-flex');
      
      // Submit form
      uploadForm.submit();
    });
  }
  
  // Password toggle functionality
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
  
  // Setup toggles for all password fields
  setupPasswordToggle('toggleCurrentPassword', 'current_password', 'currentPasswordIcon');
  setupPasswordToggle('toggleNewPassword', 'new_password', 'newPasswordIcon');
  setupPasswordToggle('toggleConfirmPassword', 'confirm_password', 'confirmPasswordIcon');
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

