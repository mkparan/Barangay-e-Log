<?php
$page_title = "Official Presence";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();


$db = db_connect();

if (isset($_GET['check_in'])) {
    $id = $_GET['check_in'];
    $stmt = $db->prepare("INSERT INTO presence (official_id, check_in) VALUES (?, NOW())");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: presence.php?msg=checked_in");
    exit;
}

if (isset($_GET['check_out'])) {
    $id = $_GET['check_out'];
    $stmt = $db->prepare("UPDATE presence SET check_out = NOW() WHERE official_id=? AND check_out IS NULL");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header("Location: presence.php?msg=checked_out");
    exit;
}

$officials = $db->query("SELECT * FROM users WHERE role IN ('official','secretary','captain') ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$presence = $db->query("
    SELECT p.*, u.full_name 
    FROM presence p
    JOIN users u ON p.official_id = u.user_id
    WHERE DATE(p.check_in) = CURDATE()
    ORDER BY p.check_in DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box">

<h4 class="mb-3">Official Presence Today</h4>

<?php if(!empty($_GET['msg'])): ?>
<div class="alert alert-success">Status updated successfully.</div>
<?php endif; ?>

<h5>Check-In / Check-Out</h5>

<div class="list-group mb-4">
<?php foreach ($officials as $o): ?>
  <div class="list-group-item d-flex justify-content-between">
    <span><?= esc($o['full_name']) ?></span>
    <?php 
      $is_present = $db->query("SELECT 1 FROM presence WHERE official_id={$o['user_id']} AND check_out IS NULL")->num_rows > 0;
    ?>
    <?php if ($is_present): ?>
      <a href="?check_out=<?= $o['user_id'] ?>" class="btn btn-danger btn-sm">Check Out</a>
    <?php else: ?>
      <a href="?check_in=<?= $o['user_id'] ?>" class="btn btn-success btn-sm">Check In</a>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
</div>

<hr>

<h5>Today's Presence Log</h5>

<div class="table-responsive">
<table class="table table-striped table-bordered">
  <thead class="table-light">
    <tr>
      <th>Official</th>
      <th>Check-in</th>
      <th>Check-out</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($presence as $p): ?>
    <tr>
      <td><?= esc($p['full_name']) ?></td>
      <td><?= esc($p['check_in']) ?></td>
      <td><?= esc($p['check_out'] ?? '---') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
