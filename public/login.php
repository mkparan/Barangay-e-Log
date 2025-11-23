<?php
$page_title = "Citizen Login";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
redirect_if_admin_logged_in();
redirect_if_citizen_logged_in();


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = db_connect();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cin = trim($_POST['cin'] ?? '');
    $stmt = $db->prepare("SELECT citizen_id, cin, first_name, last_name FROM citizens WHERE cin = ? LIMIT 1");
    $stmt->bind_param('s', $cin);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $_SESSION['citizen'] = [
            'citizen_id' => $row['citizen_id'],
            'cin' => $row['cin'],
            'name' => $row['first_name'] . ' ' . $row['last_name']
        ];
        audit_log($row['cin'], null, 'citizen_login', 'citizens', $row['citizen_id']);
        header('Location: dashboard.php');
        exit;
    } else {
        $errors[] = "CIN not found. Please register.";
    }
}
?>

<div class="row login-layout g-4">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="container-box shadow-sm h-100">
            <h3 class="mb-3">Citizen Login</h3>

            <?php foreach($errors as $e): ?>
                <div class="alert alert-danger"><?= esc($e) ?></div>
            <?php endforeach; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">CIN (Citizen ID Number)</label>
                    <input type="text" class="form-control" name="cin" required>
                </div>

                <button class="btn btn-primary w-100">Login</button>
            </form>

            <p class="mt-3">
                <a href="register.php">Don't have an account? Register</a>
            </p>
        </div>
    </div>

    <div class="col-12 col-md-6 col-lg-7 ms-lg-auto">
        <div class="container-box mock-icon-card h-100 d-flex flex-column align-items-center justify-content-center text-center">
            <div class="mock-icon-circle mb-3">
                LOGO
            </div>
            <p class="mb-1 fw-semibold text-uppercase text-muted small">Barangay Seal Placeholder</p>
            <small class="text-muted">Replace this panel with the official icon.</small>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
