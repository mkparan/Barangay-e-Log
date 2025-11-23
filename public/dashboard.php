<?php
$page_title = "Dashboard - Barangay e-Log";
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

$annStmt = $db->query("SELECT announcement_id, title, body, publish_at FROM announcements WHERE is_published=1 AND (expire_at IS NULL OR expire_at > NOW()) ORDER BY publish_at DESC LIMIT 5");
$announcements = $annStmt ? $annStmt->fetch_all(MYSQLI_ASSOC) : [];

// Get current appointments
$stmt = $db->prepare("SELECT a.* , u.full_name AS official_name FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id WHERE a.citizen_id = ? ORDER BY a.created_at DESC");
$stmt->bind_param('i', $citizen['citizen_id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$currentAppointments = [];
foreach ($appointments as $appt) {
    $status = strtolower($appt['status'] ?? '');
    $isClosed = in_array($status, ['completed', 'declined', 'cancelled']);
    $isPast = !empty($appt['preferred_date']) && $appt['preferred_date'] < $today;
    if (!($isClosed || $isPast)) {
        $currentAppointments[] = $appt;
    }
}
?>

<div class="container-box mb-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
    <div>
      <h4 class="mb-1">Hello, <?= esc($citizen['name']) ?></h4>
      <p class="text-muted mb-0">Stay updated with barangay announcements, view your appointments, and manage your services.</p>
    </div>
    <div>
      <a href="create_appointment.php" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-2"></i>Create Appointment
      </a>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="container-box">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Barangay Announcements</h5>
        <span class="badge bg-primary"><?= count($announcements) ?> latest</span>
      </div>
      <?php if(empty($announcements)): ?>
        <div class="alert alert-info mb-0">No announcements at this time. Please check back later.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach($announcements as $a): ?>
            <div class="list-group-item">
              <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-1"><?= esc($a['title']) ?></h6>
                <small class="text-muted"><?= esc(date('M d, Y', strtotime($a['publish_at']))) ?></small>
              </div>
              <p class="mb-0"><?= nl2br(esc($a['body'])) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Current Appointments</h5>
        <a href="create_appointment.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <?php if(empty($currentAppointments)): ?>
        <div class="alert alert-info mb-0">You have no pending or upcoming appointments.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach(array_slice($currentAppointments, 0, 5) as $a): ?>
            <div class="list-group-item px-0">
              <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                  <strong class="d-block"><?= esc($a['service_type']) ?></strong>
                  <small class="text-muted"><?= esc($a['preferred_date']) ?></small>
                </div>
                <span class="badge bg-info text-dark text-uppercase ms-2"><?= esc($a['status']) ?></span>
              </div>
              <div class="mt-1">
                <small class="text-muted">Queue #<?= esc($a['queue_number']) ?> · <?= esc($a['official_name'] ?? 'Unassigned') ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if(count($currentAppointments) > 5): ?>
          <div class="mt-3 text-center">
            <a href="create_appointment.php" class="btn btn-sm btn-outline-primary">View All (<?= count($currentAppointments) ?>)</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>


<?php require_once __DIR__ . '/../inc/footer.php'; ?>
