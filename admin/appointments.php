<?php
$page_title = "Announcements";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
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
        $stmt = $db->prepare("INSERT INTO announcements (title, body, is_published) VALUES (?,?,?)");
        $stmt->bind_param('ssi', $title, $body, $publish);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_created', 'announcements', $stmt->insert_id);
        header("Location: announcements.php?msg=created");
        exit;
    }
}

$ann = $db->query("SELECT * FROM announcements ORDER BY publish_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box">

<h4 class="mb-3">Barangay Announcements</h4>

<?php if(!empty($_GET['msg'])): ?>
<div class="alert alert-success">Announcement <?= esc($_GET['msg']) ?> successfully.</div>
<?php endif; ?>

<h5>Create Announcement</h5>

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
        <input class="form-check-input" type="checkbox" name="is_published" value="1">
        <label class="form-check-label">Publish Now</label>
    </div>
    <button class="btn btn-primary">Post Announcement</button>
</form>

<hr>

<h5>Existing Announcements</h5>

<div class="table-responsive">
<table class="table table-striped align-middle">
    <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Published</th>
            <th>Body</th>
            <th width="150">Action</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($ann as $a): ?>
        <tr>
            <td><?= esc($a['announcement_id']) ?></td>
            <td><?= esc($a['title']) ?></td>
            <td>
                <?php if ($a['is_published']): ?>
                    <span class="badge bg-success">Published</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Hidden</span>
                <?php endif; ?>
            </td>
            <td><?= nl2br(esc($a['body'])) ?></td>
            <td>
                <a class="btn btn-sm btn-danger" 
                   href="announcements.php?delete=<?= $a['announcement_id'] ?>"
                   onclick="return confirm('Delete this announcement?')">
                   Delete
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

</div>

<?php
// DELETE HANDLER
if (!empty($_GET['delete'])) {
    $del = $_GET['delete'];
    $db->query("DELETE FROM announcements WHERE announcement_id=$del");
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'announcement_deleted', 'announcements', $del);
    echo "<script>window.location='announcements.php?msg=deleted'</script>";
}
?>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
