<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();

// Handle check-in
if (isset($_GET['check_in'])) {
    $id = (int)$_GET['check_in'];
    $today = date('Y-m-d');
    
    // Check if already checked in today
    $checkStmt = $db->prepare("SELECT presence_id FROM presence WHERE official_id = ? AND DATE(check_in) = ? AND check_out IS NULL");
    $checkStmt->bind_param('is', $id, $today);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        header("Location: check_in.php?msg=already_checked_in");
        exit;
    }
    
    $stmt = $db->prepare("INSERT INTO presence (official_id, check_in, status) VALUES (?, NOW(), 'present')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_check_in', 'presence', $stmt->insert_id);
    header("Location: check_in.php?msg=checked_in");
    exit;
}

// Handle check-out
if (isset($_GET['check_out'])) {
    $id = (int)$_GET['check_out'];
    $today = date('Y-m-d');
    
    $stmt = $db->prepare("UPDATE presence SET check_out = NOW() WHERE official_id = ? AND DATE(check_in) = ? AND check_out IS NULL");
    $stmt->bind_param('is', $id, $today);
    $stmt->execute();
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_check_out', 'presence', $id);
    header("Location: check_in.php?msg=checked_out");
    exit;
}

// Get all active officials
$officials = $db->query("
    SELECT u.*, 
           (SELECT check_in FROM presence WHERE official_id = u.user_id AND DATE(check_in) = CURDATE() AND check_out IS NULL LIMIT 1) as check_in_time
    FROM users u 
    WHERE u.role != 'admin' AND u.is_active = 1
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

// Get today's presence log
$presenceLog = $db->query("
    SELECT p.*, u.full_name, u.role
    FROM presence p
    JOIN users u ON p.official_id = u.user_id
    WHERE DATE(p.check_in) = CURDATE()
    ORDER BY p.check_in DESC
")->fetch_all(MYSQLI_ASSOC);

$page_title = "Official Check-In";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Official Check-In / Check-Out</h4>
  <p class="text-muted">Officials check in when they arrive at the barangay hall. This reflects on the landing page.</p>

  <?php if(!empty($_GET['msg'])): ?>
    <div class="alert alert-<?= $_GET['msg'] === 'already_checked_in' ? 'warning' : 'success' ?> alert-dismissible fade show">
      <?php
        if ($_GET['msg'] === 'checked_in') echo "Official checked in successfully.";
        elseif ($_GET['msg'] === 'checked_out') echo "Official checked out successfully.";
        elseif ($_GET['msg'] === 'already_checked_in') echo "Official is already checked in today.";
      ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <h5 class="mb-3">Check-In / Check-Out</h5>
      
      <?php if(empty($officials)): ?>
        <div class="alert alert-info mb-0">No active officials found.</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach ($officials as $o): 
            $isCheckedIn = !empty($o['check_in_time']);
          ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong><?= esc($o['full_name']) ?></strong>
                <br>
                <small class="text-muted">
                  <?= ucfirst(str_replace('_', ' ', esc($o['role']))) ?>
                  <?php if ($isCheckedIn): ?>
                    <span class="badge bg-success ms-2">Checked in</span>
                    <br><small>Since: <?= esc(date('h:i A', strtotime($o['check_in_time']))) ?></small>
                  <?php endif; ?>
                </small>
              </div>
              <div>
                <?php if ($isCheckedIn): ?>
                  <a href="?check_out=<?= $o['user_id'] ?>" 
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Check out <?= esc($o['full_name']) ?>?')">
                    <i class="bi bi-box-arrow-right me-1"></i>Check Out
                  </a>
                <?php else: ?>
                  <a href="?check_in=<?= $o['user_id'] ?>" 
                     class="btn btn-success btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Check In
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Today's Presence Log</h5>
        <span class="badge bg-primary"><?= count($presenceLog) ?> records</span>
      </div>

      <?php if(empty($presenceLog)): ?>
        <div class="alert alert-info mb-0">No check-ins recorded for today.</div>
      <?php else: ?>
        <div class="table-responsive scrollable-table-sm">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light sticky-top">
              <tr>
                <th>Official</th>
                <th>Check-In</th>
                <th>Check-Out</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($presenceLog as $p): ?>
                <tr>
                  <td>
                    <strong><?= esc($p['full_name']) ?></strong>
                    <br><small class="text-muted"><?= ucfirst(str_replace('_', ' ', esc($p['role']))) ?></small>
                  </td>
                  <td><?= esc(date('h:i A', strtotime($p['check_in']))) ?></td>
                  <td>
                    <?php if ($p['check_out']): ?>
                      <?= esc(date('h:i A', strtotime($p['check_out']))) ?>
                    <?php else: ?>
                      <span class="badge bg-success">Still Present</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($p['check_out']): ?>
                      <span class="badge bg-secondary">Checked Out</span>
                    <?php else: ?>
                      <span class="badge bg-success">Present</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

