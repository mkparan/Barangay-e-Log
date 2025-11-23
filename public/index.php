<?php
$page_title = "Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

$db = db_connect();

$annQ = $db->query("SELECT announcement_id, title, body, publish_at FROM announcements WHERE is_published=1 AND (expire_at IS NULL OR expire_at > NOW()) ORDER BY publish_at DESC LIMIT 5");
$announcements = $annQ ? $annQ->fetch_all(MYSQLI_ASSOC) : [];

$today = date('Y-m-d');
$rosterStmt = $db->prepare("SELECT dr.*, u.user_id, u.full_name, u.role FROM duty_roster dr JOIN users u ON dr.official_id = u.user_id WHERE dr.duty_date = ? ORDER BY dr.start_time");
$rosterStmt->bind_param('s', $today);
$rosterStmt->execute();
$roster = $rosterStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$presenceStmt = $db->query("SELECT p.*, u.full_name FROM presence p JOIN users u ON p.official_id = u.user_id WHERE p.check_out IS NULL");
$presentNow = $presenceStmt ? $presenceStmt->fetch_all(MYSQLI_ASSOC) : [];

$totalAnnouncements = count($announcements);
$scheduledToday = count($roster);
$presentCount = count($presentNow);
?>
<div class="card border-0 shadow-sm rounded-4 mb-4">
  <div class="card-body p-4">
    <div class="row g-4 align-items-center">
      <div class="col-md-8">
        <p class="text-muted mb-1">Barangay e-Log Portal</p>
        <h2 class="fw-semibold mb-2">Stay informed and book services with ease.</h2>
        <p class="text-muted mb-4">View the latest barangay announcements, check today's officials, and reserve an appointment without lining up.</p>
      </div>
      <div class="col-md-4">
        <div class="bg-light rounded-4 p-3 h-100">
          <p class="text-uppercase text-muted small mb-3">At a glance</p>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span>Active announcements</span>
              <strong><?= $totalAnnouncements ?></strong>
            </div>
            <small class="text-muted">Fresh updates from the barangay</small>
          </div>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <span>Officials on duty</span>
              <strong><?= $scheduledToday ?></strong>
            </div>
            <small class="text-muted">Scheduled today</small>
          </div>
          <div>
            <div class="d-flex justify-content-between align-items-center">
              <span>Currently present</span>
              <strong><?= $presentCount ?></strong>
            </div>
            <small class="text-muted">Checked in right now</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small">Need to book?</h6>
        <h4 class="mb-3">Secure an appointment</h4>
        <p class="text-muted">Registered citizens can choose a service, see requirements, and reserve a slot before visiting the <b>Barangay Hall</b>.</p>
        <div class="d-grid gap-2">
          <a href="login.php" class="btn btn-primary btn-sm">Go to Dashboard</a>
          <a href="register.php" class="btn btn-outline-primary btn-sm">Create an Account</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small">Today’s schedule</h6>
        <h4 class="mb-3">Duty roster</h4>
        <?php if(empty($roster)): ?>
          <p class="text-muted mb-0">No officials scheduled today.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach($roster as $r): ?>
              <li class="mb-2">
                <strong><?= esc($r['full_name']) ?></strong>
                <div class="text-muted small"><?= esc($r['start_time']) ?> - <?= esc($r['end_time']) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body">
        <h6 class="text-muted text-uppercase small">On-site now</h6>
        <h4 class="mb-3">Present officials</h4>
        <?php if(empty($presentNow)): ?>
          <p class="text-muted mb-0">No officials checked in.</p>
        <?php else: ?>
          <ul class="list-unstyled mb-0">
            <?php foreach($presentNow as $p): ?>
              <li class="mb-2">
                <?= esc($p['full_name']) ?><br>
                <small class="text-muted">Checked in at <?= esc(date('h:i A', strtotime($p['check_in']))) ?></small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
    <div>
      <h5 class="mb-0">Latest Announcements</h5>
      <small class="text-muted">Important reminders and news from the barangay</small>
    </div>
    <span class="badge bg-primary"><?= $totalAnnouncements ?></span>
  </div>
  <div class="card-body">
    <?php if(empty($announcements)): ?>
      <div class="alert alert-info mb-0">No announcements at this time.</div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach($announcements as $a): ?>
          <div class="list-group-item px-0">
            <div class="d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><?= esc($a['title']) ?></h6>
              <small class="text-muted"><?= esc(date('M d, Y', strtotime($a['publish_at']))) ?></small>
            </div>
            <p class="mb-0 mt-2"><?= nl2br(esc($a['body'])) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
