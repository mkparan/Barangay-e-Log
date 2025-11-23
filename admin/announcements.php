<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();
$errors = [];

// Auto-delete expired announcements
$db->query("DELETE FROM announcements WHERE expire_at IS NOT NULL AND expire_at < NOW()");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publish = $_POST['is_published'] ?? 0;
    $daysToDisplay = (int)($_POST['days_to_display'] ?? 7);
    
    if ($daysToDisplay < 1) {
        $daysToDisplay = 7; // Default to 7 days
    }

    if (!$title || !$body) {
        $errors[] = "Title and body are required.";
    } else {
        // Calculate expire_at based on days to display
        $expireAt = date('Y-m-d H:i:s', strtotime("+{$daysToDisplay} days"));
        
        $stmt = $db->prepare("INSERT INTO announcements (title, body, is_published, posted_by, expire_at) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssiis', $title, $body, $publish, $_SESSION['user']['user_id'], $expireAt);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_created', 'announcements', $stmt->insert_id);
        header("Location: announcements.php?msg=created");
        exit;
    }
}

// DELETE HANDLER (before header output)
if (!empty($_GET['delete'])) {
    $del = $_GET['delete'];
    $db->query("DELETE FROM announcements WHERE announcement_id=$del");
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_deleted', 'announcements', $del);
    header("Location: announcements.php?msg=deleted");
    exit;
}

$ann = $db->query("SELECT * FROM announcements ORDER BY publish_at DESC")->fetch_all(MYSQLI_ASSOC);

$page_title = "Citizen Announcements";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Barangay Announcements</h4>

  <?php if(!empty($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    Announcement <?= esc($_GET['msg']) ?> successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <h5 class="mb-3">Create Announcement</h5>

      <?php foreach($errors as $e): ?>
        <div class="alert alert-danger"><?= esc($e) ?></div>
      <?php endforeach; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">Title <span class="text-danger">*</span></label>
          <input class="form-control" name="title" required placeholder="Enter announcement title">
        </div>
        <div class="mb-3">
          <label class="form-label">Body <span class="text-danger">*</span></label>
          <textarea class="form-control" name="body" rows="6" required placeholder="Enter announcement content"></textarea>
        </div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Display Duration</label>
            <select class="form-select" name="days_to_display" required>
              <option value="1">1 Day</option>
              <option value="2">2 Days</option>
              <option value="3">3 Days</option>
              <option value="5">5 Days</option>
              <option value="7" selected>7 Days (1 Week)</option>
              <option value="14">14 Days (2 Weeks)</option>
              <option value="30">30 Days (1 Month)</option>
            </select>
            <small class="text-muted">Announcement will be automatically deleted after this period</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Status</label>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="is_published" value="1" id="publishCheck" checked>
              <label class="form-check-label" for="publishCheck">
                Publish Immediately
              </label>
            </div>
            <small class="text-muted">Uncheck to save as draft</small>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-send me-2"></i>Post Announcement
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Existing Announcements</h5>
        <span class="badge bg-primary"><?= count($ann) ?> total</span>
      </div>

      <?php if(empty($ann)): ?>
        <div class="alert alert-info mb-0">No announcements yet. Create your first announcement on the left.</div>
      <?php else: ?>
        <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
          <?php foreach($ann as $a): 
            $isExpired = !empty($a['expire_at']) && strtotime($a['expire_at']) < time();
            $daysRemaining = null;
            if (!empty($a['expire_at']) && !$isExpired) {
              $daysRemaining = ceil((strtotime($a['expire_at']) - time()) / (60 * 60 * 24));
            }
          ?>
            <div class="list-group-item px-0 <?= $isExpired ? 'opacity-50' : '' ?>">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                  <h6 class="mb-1"><?= esc($a['title']) ?></h6>
                  <p class="mb-2 text-muted small"><?= nl2br(esc(substr($a['body'], 0, 150))) ?><?= strlen($a['body']) > 150 ? '...' : '' ?></p>
                </div>
                <div class="text-end ms-2">
                  <?php if ($a['is_published']): ?>
                    <span class="badge bg-success">Published</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Draft</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <div class="small text-muted">
                  <div>Published: <?= esc(date('M d, Y h:i A', strtotime($a['publish_at']))) ?></div>
                  <?php if (!empty($a['expire_at'])): ?>
                    <?php if ($isExpired): ?>
                      <div class="text-danger">Expired: <?= esc(date('M d, Y', strtotime($a['expire_at']))) ?></div>
                    <?php else: ?>
                      <div class="text-info">
                        <i class="bi bi-clock me-1"></i>Expires in <?= $daysRemaining ?> day<?= $daysRemaining != 1 ? 's' : '' ?> (<?= esc(date('M d, Y', strtotime($a['expire_at']))) ?>)
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-muted">No expiration set</div>
                  <?php endif; ?>
                </div>
                <a class="btn btn-sm btn-danger" 
                   href="announcements.php?delete=<?= $a['announcement_id'] ?>"
                   onclick="return confirm('Delete this announcement?')">
                   <i class="bi bi-trash"></i>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

