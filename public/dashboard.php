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
?>

<div class="container-box mb-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
    <div>
      <h4 class="mb-1">Hello, <?= esc($citizen['name']) ?></h4>
      <p class="text-muted mb-0">Stay updated with barangay announcements and news.</p>
    </div>
  </div>
</div>

<div class="container-box mb-4">
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


<?php require_once __DIR__ . '/../inc/footer.php'; ?>
