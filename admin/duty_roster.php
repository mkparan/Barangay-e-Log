<?php
$page_title = "Duty Roster";
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

$officials = $db->query("SELECT user_id, full_name FROM users WHERE role IN ('official','secretary','captain') ORDER BY full_name")
                ->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $official = $_POST['official_id'];
    $date = $_POST['duty_date'];
    $start = $_POST['start_time'];
    $end = $_POST['end_time'];

    if (!$official || !$date || !$start || !$end) {
        $errors[] = "All fields required.";
    } else {
        $stmt = $db->prepare("INSERT INTO duty_roster (official_id, duty_date, start_time, end_time) VALUES (?,?,?,?)");
        $stmt->bind_param('isss', $official, $date, $start, $end);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'duty_added', 'duty_roster', $stmt->insert_id);
        header("Location: duty_roster.php?msg=added");
        exit;
    }
}

$rows = $db->query("
    SELECT dr.*, u.full_name 
    FROM duty_roster dr 
    JOIN users u ON dr.official_id = u.user_id
    ORDER BY dr.duty_date DESC, dr.start_time
")->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box">

<h4 class="mb-3">Duty Roster</h4>

<?php if(!empty($_GET['msg'])): ?>
<div class="alert alert-success">Duty schedule <?= esc($_GET['msg']) ?> successfully.</div>
<?php endif; ?>

<h5>Add Duty Schedule</h5>

<?php foreach($errors as $e): ?>
<div class="alert alert-danger"><?= esc($e) ?></div>
<?php endforeach; ?>

<form method="post" class="mb-4">
  <div class="row g-3">
    <div class="col-md-4">
      <label class="form-label">Official</label>
      <select class="form-select" name="official_id" required>
        <option value="">Select official</option>
        <?php foreach($officials as $off): ?>
          <option value="<?= $off['user_id'] ?>"><?= esc($off['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Date</label>
      <input type="date" class="form-control" name="duty_date" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Start</label>
      <input type="time" class="form-control" name="start_time" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">End</label>
      <input type="time" class="form-control" name="end_time" required>
    </div>
  </div>

  <button class="btn btn-primary mt-3">Add Duty</button>
</form>

<hr>

<h5>All Duty Schedules</h5>

<div class="table-responsive">
<table class="table table-bordered table-striped align-middle">
  <thead class="table-light">
    <tr>
      <th>ID</th>
      <th>Official</th>
      <th>Date</th>
      <th>Time</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= esc($r['duty_id']) ?></td>
      <td><?= esc($r['full_name']) ?></td>
      <td><?= esc($r['duty_date']) ?></td>
      <td><?= esc($r['start_time'].' - '.$r['end_time']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
