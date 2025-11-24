<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_citizen();

if (empty($_SESSION['citizen'])) {
    header('Location: login.php');
    exit;
}

$db = db_connect();
$citizen = $_SESSION['citizen'];

// Get full citizen data
$stmt = $db->prepare("SELECT * FROM citizens WHERE citizen_id = ?");
$stmt->bind_param('i', $citizen['citizen_id']);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();

$errors = [];
$success = false;

// Handle profile picture upload separately (BEFORE header output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture']) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/uploads/profiles/';
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
        $filename = 'citizen_' . $citizen['citizen_id'] . '_' . time() . '.' . $extension;
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old profile picture if exists
            if (!empty($profile['profile_picture']) && file_exists(__DIR__ . '/' . $profile['profile_picture'])) {
                @unlink(__DIR__ . '/' . $profile['profile_picture']);
            }
            
            $relativePath = 'uploads/profiles/' . $filename;
            $updPic = $db->prepare("UPDATE citizens SET profile_picture=? WHERE citizen_id=?");
            $updPic->bind_param('si', $relativePath, $citizen['citizen_id']);
            if ($updPic->execute()) {
                audit_log($citizen['cin'], null, 'profile_picture_update', 'citizens', $citizen['citizen_id']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['upload_picture'])) {
    $first = trim($_POST['first_name'] ?? '');
    $middle = trim($_POST['middle_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $birth = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $gender = trim($_POST['gender'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gov_aff = trim($_POST['gov_affiliations'] ?? '');

    if (!$first || !$last) {
        $errors[] = "First name and last name are required.";
    } else {
        $upd = $db->prepare("UPDATE citizens SET first_name=?, middle_name=?, last_name=?, birth_date=?, gender=?, contact_number=?, email=?, address=?, gov_affiliations=? WHERE citizen_id=?");
        $upd->bind_param('sssssssssi', $first, $middle, $last, $birth, $gender, $contact, $email, $address, $gov_aff, $citizen['citizen_id']);
        if ($upd->execute()) {
            audit_log($citizen['cin'], null, 'profile_update', 'citizens', $citizen['citizen_id']);
            $_SESSION['citizen']['name'] = $first . ' ' . $last;
            $success = true;
            // Refresh profile data
            $stmt->execute();
            $profile = $stmt->get_result()->fetch_assoc();
        } else {
            $errors[] = "DB Error: " . $upd->error;
        }
    }
}

// Check for session errors
if (!empty($_SESSION['profile_error'])) {
    $errors[] = $_SESSION['profile_error'];
    unset($_SESSION['profile_error']);
}

$page_title = "Profile - Barangay e-Log";
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
            $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150"><circle cx="75" cy="75" r="75" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="60" fill="#6c757d">' . strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
            ?>
            <img id="profileImagePreview" src="<?= $profilePic ? esc('/elog_barangay/public/' . $profilePic) : $defaultPic ?>" 
                 alt="Profile Picture" 
                 class="rounded-circle border" 
                 style="width: 150px; height: 150px; object-fit: cover; border-width: 3px !important;">
            <div id="uploadOverlay" class="position-absolute top-0 start-0 w-100 h-100 rounded-circle d-none align-items-center justify-content-center" style="background: rgba(0,0,0,0.7);">
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
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Barangay ID</label>
            <input type="text" class="form-control" value="<?= esc($profile['cin']) ?>" disabled>
            <small class="text-muted">Barangay ID cannot be changed</small>
          </div>
          <div class="col-md-4">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" class="form-control" value="<?= esc($profile['first_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Middle Name</label>
            <input type="text" name="middle_name" class="form-control" value="<?= esc($profile['middle_name'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" class="form-control" value="<?= esc($profile['last_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Birth Date</label>
            <input type="date" name="birth_date" class="form-control" value="<?= esc($profile['birth_date'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
              <option value="">-- Select --</option>
              <option value="Male" <?= ($profile['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($profile['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($profile['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" value="<?= esc($profile['contact_number'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= esc($profile['email'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Address</label>
            <textarea name="address" class="form-control" rows="3"><?= esc($profile['address'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Government Affiliations</label>
            <textarea name="gov_affiliations" class="form-control" rows="2" placeholder="List any government affiliations or memberships"><?= esc($profile['gov_affiliations'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="mt-4">
          <button type="submit" class="btn btn-primary">Update Profile</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="container-box text-center mb-4">
      <?php 
      $profilePic = !empty($profile['profile_picture']) ? $profile['profile_picture'] : null;
      $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><circle cx="60" cy="60" r="60" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="50" fill="#6c757d">' . strtoupper(substr($profile['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
      ?>
      <img src="<?= $profilePic ? esc('/elog_barangay/public/' . $profilePic) : $defaultPic ?>" 
           alt="Profile Picture" 
           class="rounded-circle border mb-3" 
           style="width: 120px; height: 120px; object-fit: cover; border-width: 3px !important;">
      <h5 class="mb-1"><?= esc(trim(($profile['first_name'] ?? '') . ' ' . ($profile['middle_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></h5>
      <p class="text-muted small mb-0"><?= esc($profile['cin']) ?></p>
    </div>
    <div class="container-box">
      <h5 class="mb-3">Account Summary</h5>
      <dl class="row">
        <dt class="col-sm-5">Barangay ID:</dt>
        <dd class="col-sm-7"><?= esc($profile['cin']) ?></dd>
        
        <dt class="col-sm-5">Full Name:</dt>
        <dd class="col-sm-7"><?= esc(trim(($profile['first_name'] ?? '') . ' ' . ($profile['middle_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))) ?></dd>
        
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
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

