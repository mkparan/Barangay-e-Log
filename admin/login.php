<?php
$page_title = "Admin Login";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
redirect_if_admin_logged_in();
redirect_if_citizen_logged_in();



$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') $errors[] = 'Username and password required';
    else {
        if (admin_login($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Invalid credentials';
        }
    }
}
?>
<div class="container-box col-lg-5 mx-auto">
  <h3 class="mb-3">Official / Admin Login</h3>
  <?php foreach($errors as $e): ?><div class="alert alert-danger"><?= esc($e) ?></div><?php endforeach; ?>
  <form method="post">
    <div class="mb-3"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required></div>
    <div class="d-grid"><button class="btn btn-primary">Login</button></div>
  </form>
  <p class="mt-3"><a href="/elog_barangay/public/index.php">Back to public</a></p>
</div>
<?php require_once __DIR__ . '/../inc/footer.php'; ?>
