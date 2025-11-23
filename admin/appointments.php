<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $publish = $_POST['is_published'] ?? 0;

    if (!$title || !$body) {
        $errors[] = "Title and body are required.";
    } else {
        $stmt = $db->prepare("INSERT INTO announcements (title, body, is_published, posted_by) VALUES (?,?,?,?)");
        $stmt->bind_param('ssii', $title, $body, $publish, $_SESSION['user']['user_id']);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_created', 'announcements', $stmt->insert_id);
        header("Location: appointments.php?msg=created");
        exit;
    }
}

// DELETE HANDLER (before header output)
if (!empty($_GET['delete'])) {
    $del = $_GET['delete'];
    $db->query("DELETE FROM announcements WHERE announcement_id=$del");
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_deleted', 'announcements', $del);
    header("Location: appointments.php?msg=deleted");
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

  <h5 class="mb-3">Create Announcement</h5>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>

  <form method="post" class="mb-4">
    <div class="mb-3">
      <label class="form-label">Title</label>
      <input class="form-control" name="title" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Body</label>
      <textarea class="form-control" name="body" rows="4" required></textarea>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="is_published" value="1" checked>
      <label class="form-check-label">Publish Now</label>
    </div>
    <button class="btn btn-primary">Post Announcement</button>
  </form>

  <hr class="my-4">

  <h5 class="mb-3">Existing Announcements</h5>

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Status</th>
          <th>Body</th>
          <th>Published</th>
          <th width="100">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($ann)): ?>
          <tr>
            <td colspan="6" class="text-center text-muted">No announcements yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach($ann as $a): ?>
            <tr>
              <td><?= esc($a['announcement_id']) ?></td>
              <td><strong><?= esc($a['title']) ?></strong></td>
              <td>
                <?php if ($a['is_published']): ?>
                  <span class="badge bg-success">Published</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Hidden</span>
                <?php endif; ?>
              </td>
              <td><?= nl2br(esc(substr($a['body'], 0, 100))) ?><?= strlen($a['body']) > 100 ? '...' : '' ?></td>
              <td><?= esc(date('M d, Y', strtotime($a['publish_at']))) ?></td>
              <td>
                <a class="btn btn-sm btn-danger" 
                   href="appointments.php?delete=<?= $a['announcement_id'] ?>"
                   onclick="return confirm('Delete this announcement?')">
                   Delete
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
