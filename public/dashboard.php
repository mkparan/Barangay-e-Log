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

// Ensure is_verified is set in session (updated by require_citizen)
if (!isset($citizen['is_verified'])) {
    $citizen['is_verified'] = false;
}

$annStmt = $db->query("SELECT announcement_id, title, body, image, publish_at FROM announcements WHERE is_published=1 AND (expire_at IS NULL OR expire_at > NOW()) ORDER BY publish_at DESC LIMIT 5");
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

<!-- 5. Persistent Notification Banner for Unverified Accounts -->
<?php if (empty($citizen['is_verified']) || !$citizen['is_verified']): ?>
<div class="container-box mb-4 border-warning border-3 bg-warning bg-opacity-10">
  <div class="d-flex align-items-start">
    <div class="flex-grow-1">
      <h5 class="text-warning mb-2">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>Account Not Verified
      </h5>
      <p class="mb-2">Your account is pending verification. You cannot book appointments until your account is verified by barangay officials.</p>
      <p class="mb-0 text-muted">
        <strong>What to do:</strong> Please visit the barangay hall with a valid ID to verify your account. 
        Once verified, you will be able to book appointments and access all features.
      </p>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container-box mb-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
    <div>
      <h4 class="mb-1">Hello, <?= esc($citizen['name']) ?></h4>
      <p class="text-muted mb-0">Stay updated with barangay announcements, view your appointments, and manage your services.</p>
    </div>
    <div>
      <?php if (!empty($citizen['is_verified']) && $citizen['is_verified']): ?>
        <a href="create_appointment.php" class="btn btn-primary">
          <i class="bi bi-calendar-plus me-2"></i>Create Appointment
        </a>
      <?php else: ?>
        <button class="btn btn-primary" disabled title="Account must be verified to book appointments">
          <i class="bi bi-calendar-plus me-2"></i>Create Appointment
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="container-box border-primary border-2 d-flex flex-column" style="max-height: 700px;">
      <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom flex-shrink-0">
        <div>
          <h5 class="mb-0">
            <i class="bi bi-megaphone-fill text-primary me-2"></i>Barangay Announcements
          </h5>
          <small class="text-muted">Important reminders and news from the barangay</small>
        </div>
        <span class="badge bg-primary"><?= count($announcements) ?> latest</span>
      </div>
      <?php if(empty($announcements)): ?>
        <div class="alert alert-info mb-0">No announcements at this time. Please check back later.</div>
      <?php else: ?>
        <div class="flex-grow-1" style="overflow-y: auto;">
          <?php foreach($announcements as $a): 
            $bodyPreview = strlen($a['body']) > 200 ? substr($a['body'], 0, 200) . '...' : $a['body'];
          ?>
            <div class="p-3 border-bottom">
              <?php if (!empty($a['image'])): ?>
                <img src="/elog_barangay/public/<?= esc($a['image']) ?>" 
                     alt="<?= esc($a['title']) ?>" 
                     class="img-fluid rounded mb-3 w-100" 
                     style="max-height: 200px; object-fit: cover; cursor: pointer;"
                     data-bs-toggle="modal"
                     data-bs-target="#announcementModal<?= $a['announcement_id'] ?>">
              <?php endif; ?>
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="mb-0 fw-bold flex-grow-1"><?= esc($a['title']) ?></h6>
                <small class="text-muted ms-2"><?= esc(date('M d, Y', strtotime($a['publish_at']))) ?></small>
              </div>
              <p class="mb-2"><?= nl2br(esc($bodyPreview)) ?></p>
              
              <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#announcementModal<?= $a['announcement_id'] ?>">
                <i class="bi bi-arrows-fullscreen me-1"></i>View Full Announcement
              </button>
              
              <!-- Full Announcement Modal -->
              <div class="modal fade" id="announcementModal<?= $a['announcement_id'] ?>" tabindex="-1" aria-labelledby="announcementModalLabel<?= $a['announcement_id'] ?>" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="announcementModalLabel<?= $a['announcement_id'] ?>"><?= esc($a['title']) ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      <div class="mb-3">
                        <small class="text-muted">
                          <i class="bi bi-calendar me-1"></i><?= esc(date('F d, Y', strtotime($a['publish_at']))) ?>
                        </small>
                      </div>
                      <?php if (!empty($a['image'])): ?>
                        <div class="mb-4">
                          <img src="/elog_barangay/public/<?= esc($a['image']) ?>" 
                               alt="<?= esc($a['title']) ?>" 
                               class="img-fluid rounded w-100" 
                               style="max-height: 400px; object-fit: contain;">
                        </div>
                      <?php endif; ?>
                      <div class="announcement-body">
                        <p class="mb-0" style="white-space: pre-wrap;"><?= nl2br(esc($a['body'])) ?></p>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
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
