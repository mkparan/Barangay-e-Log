<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

// Auth checks
require_admin();
block_citizen_from_admin();

$db = db_connect();

$id = $_GET['id'] ?? null;
if (!$id) {
    // If invalid ID, we can't do much. 
    // We'll show the header then the error.
    $page_title = "Error";
    require_once __DIR__ . '/../inc/header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Invalid appointment ID.</div></div>";
    require_once __DIR__ . '/../inc/footer.php';
    exit;
}

// Check for POST submission (Save Changes)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['preferred_date'] ?? null;
    $start = $_POST['preferred_start'] ?? null;
    $end = $_POST['preferred_end'] ?? null;

    if (!$date) {
        $errors[] = "Date is required.";
    } else {
        $stmt2 = $db->prepare("UPDATE appointments SET preferred_date=?, preferred_start=?, preferred_end=?, status='pending' WHERE appointment_id=?");
        $stmt2->bind_param('sssi', $date, $start, $end, $id);
        $stmt2->execute();

        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_rescheduled', 'appointments', $id);
        
        // Redirect BEFORE rendering any HTML
        header('Location: appointments.php?msg=rescheduled&filter=all');
        exit;
    }
}

// Fetch appointment details for the form
$stmt = $db->prepare("SELECT * FROM appointments WHERE appointment_id=?");
$stmt->bind_param('i', $id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    $page_title = "Error";
    require_once __DIR__ . '/../inc/header.php';
    echo "<div class='container mt-5'><div class='alert alert-danger'>Appointment not found.</div></div>";
    require_once __DIR__ . '/../inc/footer.php';
    exit;
}

// NOW we can include the header and show output
$page_title = "Reschedule Appointment";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box col-lg-6 mx-auto">

<h4 class="mb-3">Reschedule Appointment #<?= esc($id) ?></h4>

<?php foreach($errors as $e): ?>
<div class="alert alert-danger"><?= esc($e) ?></div>
<?php endforeach; ?>

<form method="post">
    <div class="mb-3">
        <label class="form-label">Preferred Date</label>
        <input type="date" name="preferred_date" class="form-control" value="<?= esc($app['preferred_date']) ?>" required>
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label">Start Time</label>
            <input type="time" name="preferred_start" class="form-control" value="<?= esc($app['preferred_start']) ?>">
        </div>
        <div class="col-md-6 mb-3">
            <label class="form-label">End Time</label>
            <input type="time" name="preferred_end" class="form-control" value="<?= esc($app['preferred_end']) ?>">
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="appointments.php" class="btn btn-secondary">Cancel</a>
        <button class="btn btn-primary">Save Changes</button>
    </div>
</form>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
