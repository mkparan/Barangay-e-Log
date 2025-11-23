<?php
$page_title = "Profile - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Profile updated successfully!
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

      <form method="post">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">CIN (Citizen ID Number)</label>
            <input type="text" class="form-control" value="<?= esc($profile['cin']) ?>" disabled>
            <small class="text-muted">CIN cannot be changed</small>
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
    <div class="container-box">
      <h5 class="mb-3">Account Summary</h5>
      <dl class="row">
        <dt class="col-sm-5">CIN:</dt>
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

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

