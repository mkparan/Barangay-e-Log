<?php
$page_title = "Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';

$db = db_connect();

$annQ = $db->query("SELECT announcement_id, title, body, image, publish_at FROM announcements WHERE is_published=1 AND (expire_at IS NULL OR expire_at > NOW()) ORDER BY publish_at DESC LIMIT 5");
$announcements = $annQ ? $annQ->fetch_all(MYSQLI_ASSOC) : [];

$today = date('Y-m-d');
// Get officials who checked in today and haven't checked out
$presenceStmt = $db->query("
    SELECT p.*, u.full_name, u.role 
    FROM presence p 
    JOIN users u ON p.official_id = u.user_id 
    WHERE DATE(p.check_in) = CURDATE() AND p.check_out IS NULL 
    ORDER BY p.check_in DESC
");
$presentNow = $presenceStmt ? $presenceStmt->fetch_all(MYSQLI_ASSOC) : [];

$totalAnnouncements = count($announcements);
$presentCount = count($presentNow);
?>
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body p-4">
        <p class="text-muted mb-1">Barangay e-Log Portal</p>
        <h2 class="fw-semibold mb-2">Stay informed and book services with ease.</h2>
        <p class="text-muted mb-0">View the latest barangay announcements, check today's officials, and reserve an appointment without lining up.</p>
      </div>
    </div>
    
    <div class="row g-4">
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
      <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body">
            <h6 class="text-muted text-uppercase small">On-site now</h6>
            <h4 class="mb-3">Present officials</h4>
            <?php if(empty($presentNow)): ?>
              <p class="text-muted mb-0">No officials checked in today.</p>
            <?php else: ?>
              <div class="row g-3">
                <?php foreach($presentNow as $p): ?>
                  <div class="col-md-6">
                    <div class="d-flex align-items-center">
                      <div class="flex-grow-1">
                        <strong><?= esc($p['full_name']) ?></strong>
                        <br>
                        <small class="text-muted">
                          <?= ucfirst(str_replace('_', ' ', esc($p['role']))) ?>
                        </small>
                        <br>
                        <small class="text-muted">Checked in at <?= esc(date('h:i A', strtotime($p['check_in']))) ?></small>
                      </div>
                      <span class="badge bg-success ms-2">
                        <i class="bi bi-check-circle me-1"></i>Present
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <p class="text-uppercase text-muted small mb-3">At a glance</p>
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <span>Active announcements</span>
            <strong><?= $totalAnnouncements ?></strong>
          </div>
          <small class="text-muted">Fresh updates from the barangay</small>
        </div>
        <div class="mb-0">
          <div class="d-flex justify-content-between align-items-center">
            <span>Currently present</span>
            <strong><?= $presentCount ?></strong>
          </div>
          <small class="text-muted">Officials checked in today</small>
        </div>
      </div>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4 border-primary border-2">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">
            <i class="bi bi-megaphone-fill me-2"></i>Latest Announcements
          </h5>
          <small>Important reminders and news</small>
        </div>
        <span class="badge bg-light text-primary"><?= $totalAnnouncements ?></span>
      </div>
      <div class="card-body p-0">
        <?php if(empty($announcements)): ?>
          <div class="alert alert-info m-3 mb-0">No announcements at this time.</div>
        <?php else: ?>
          <div class="scrollable-content">
            <?php foreach($announcements as $a): ?>
              <div class="p-3 border-bottom">
                <?php if (!empty($a['image'])): ?>
                  <img src="/elog_barangay/public/<?= esc($a['image']) ?>" 
                       alt="<?= esc($a['title']) ?>" 
                       class="img-fluid rounded mb-3 w-100" 
                       class="announcement-image">
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h6 class="mb-0 fw-bold flex-grow-1"><?= esc($a['title']) ?></h6>
                  <small class="text-muted ms-2"><?= esc(date('M d, Y', strtotime($a['publish_at']))) ?></small>
                </div>
                <p class="mb-0"><?= nl2br(esc($a['body'])) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
