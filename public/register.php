<?php
$page_title = "Register - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

$db = db_connect();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cin = trim($_POST['cin'] ?? '');
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    if (!$cin || !$first || !$last) {
        $errors[] = "CIN, first and last name are required.";
    } else {
        $stmt = $db->prepare("SELECT citizen_id FROM citizens WHERE cin = ?");
        $stmt->bind_param('s', $cin);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "CIN already registered.";
        } else {
            $stmt = $db->prepare("INSERT INTO citizens (cin, first_name, middle_name, last_name, birth_date, gender, contact_number, email, address, gov_affiliations) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $birth = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
            $middle = trim($_POST['middle_name'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $addr = trim($_POST['address'] ?? '');
            $aff = trim($_POST['gov_aff'] ?? '');
            $stmt->bind_param('ssssssssss', $cin, $first, $middle, $last, $birth, $gender, $contact, $email, $addr, $aff);
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
?>
<div class="container-box col-lg-6 mx-auto">
  <h3 class="mb-3">Citizen Registration</h3>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>

  <form method="post" novalidate>
    <div class="mb-3">
      <label class="form-label">CIN</label>
      <input class="form-control" name="cin" required>
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

    <div class="d-grid">
      <button class="btn btn-primary">Register</button>
    </div>
    <p class="mt-2"><a href="login.php">Already registered? Login</a></p>
  </form>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
