<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();

// ACTION HANDLING (approve/decline/reschedule)
$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;

if ($action && $id) {
    $filter = $_GET['filter'] ?? 'all';
    $redirectFilter = $filter !== 'all' ? "&filter={$filter}" : '';
    
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE appointments SET status='approved' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_approved', 'appointments', $id);
        header("Location: appointments.php?msg=approved{$redirectFilter}");
        exit;

    } elseif ($action === 'decline') {
        $stmt = $db->prepare("UPDATE appointments SET status='declined' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_declined', 'appointments', $id);
        header("Location: appointments.php?msg=declined{$redirectFilter}");
        exit;

    } elseif ($action === 'reschedule') {
        echo "<script>window.location.href='appointment_action.php?id={$id}';</script>";
        exit;

    } elseif ($action === 'complete') {
        $stmt = $db->prepare("UPDATE appointments SET status='completed' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_completed', 'appointments', $id);
        header("Location: appointments.php?msg=completed{$redirectFilter}");
        exit;
    }
}

// GET APPOINTMENTS
$filter = $_GET['filter'] ?? 'all';
$where = "1";

if ($filter === 'pending') $where = "a.status='pending'";
if ($filter === 'approved') $where = "a.status='approved'";
if ($filter === 'declined') $where = "a.status='declined'";
if ($filter === 'completed') $where = "a.status='completed'";

// Pagination - must be before query execution
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
";
$countResult = $db->query($countSql);
$totalCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $perPage));

$stmt = $db->query("
    SELECT a.*, c.first_name, c.last_name, c.profile_picture
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$appointments = $stmt->fetch_all(MYSQLI_ASSOC);

$page_title = "Manage Appointments";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Appointment Management</h4>

  <?php if (!empty($_GET['msg'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    Appointment <?= esc($_GET['msg']) ?> successfully.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <a href="appointments.php?filter=all" class="btn btn-outline-primary btn-sm">All</a>
      <a href="appointments.php?filter=pending" class="btn btn-outline-warning btn-sm">Pending</a>
      <a href="appointments.php?filter=approved" class="btn btn-outline-success btn-sm">Approved</a>
      <a href="appointments.php?filter=declined" class="btn btn-outline-danger btn-sm">Declined</a>
      <a href="appointments.php?filter=completed" class="btn btn-outline-secondary btn-sm">Completed</a>
    </div>
    <span class="badge bg-primary"><?= number_format($totalCount) ?> total</span>
  </div>

  <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light sticky-top">
        <tr>
          <th>ID</th>
          <th>Citizen</th>
          <th>Service</th>
          <th>Date</th>
          <th>Queue No.</th>
          <th>Status</th>
          <th width="200">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($appointments)): ?>
          <tr>
            <td colspan="7" class="text-center text-muted">No appointments found.</td>
          </tr>
        <?php else: ?>
          <?php foreach($appointments as $a): 
            $citizenPic = !empty($a['profile_picture']) ? $a['profile_picture'] : null;
            $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="20" fill="#6c757d">' . strtoupper(substr($a['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
          ?>
          <tr>
            <td><?= esc($a['appointment_id']) ?></td>
            <td>
              <div class="d-flex align-items-center">
                <img src="<?= $citizenPic ? esc('/elog_barangay/public/' . $citizenPic) : $defaultPic ?>" 
                     alt="Profile" 
                     class="rounded-circle me-2" 
                     style="width: 40px; height: 40px; object-fit: cover;">
                <span><?= esc($a['first_name'].' '.$a['last_name']) ?></span>
              </div>
            </td>
            <td><?= esc($a['service_type']) ?></td>
            <td><?= esc($a['preferred_date']) ?></td>
            <td><?= esc($a['queue_number']) ?></td>
            <td>
              <span class="badge bg-info text-dark text-uppercase"><?= esc($a['status']) ?></span>
            </td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <?php if ($a['status'] === 'pending'): ?>
                  <a class="btn btn-success btn-sm" href="?action=approve&id=<?= esc($a['appointment_id']) ?>&filter=<?= esc($filter) ?>">Approve</a>
                  <a class="btn btn-warning btn-sm" href="?action=reschedule&id=<?= esc($a['appointment_id']) ?>&filter=<?= esc($filter) ?>">Reschedule</a>
                  <a class="btn btn-danger btn-sm" href="?action=decline&id=<?= esc($a['appointment_id']) ?>&filter=<?= esc($filter) ?>">Decline</a>
                  <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>&filter=<?= esc($filter) ?>">Mark Released</a>
                <?php elseif ($a['status'] === 'approved'): ?>
                  <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>&filter=<?= esc($filter) ?>">Mark Released</a>
                <?php else: ?>
                  <small class="text-muted">No actions available</small>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <?php if($totalPages > 1): ?>
    <nav aria-label="Page navigation" class="mt-3">
      <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?filter=<?= esc($filter) ?>&page=<?= $page - 1 ?>">Previous</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">Previous</span>
          </li>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?filter=<?= esc($filter) ?>&page=1">1</a>
          </li>
          <?php if ($startPage > 2): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
        <?php endif; ?>
        
        <?php for($i = $startPage; $i <= $endPage; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?filter=<?= esc($filter) ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
          <?php if ($endPage < $totalPages - 1): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="?filter=<?= esc($filter) ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a>
          </li>
        <?php endif; ?>
        
        <?php if($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="?filter=<?= esc($filter) ?>&page=<?= $page + 1 ?>">Next</a>
          </li>
        <?php else: ?>
          <li class="page-item disabled">
            <span class="page-link">Next</span>
          </li>
        <?php endif; ?>
      </ul>
      <div class="text-center mt-2">
        <small class="text-muted">Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalCount) ?> of <?= number_format($totalCount) ?> appointments</small>
      </div>
    </nav>
  <?php else: ?>
    <div class="text-center mt-3">
      <small class="text-muted">Showing <?= $totalCount ?> appointment<?= $totalCount != 1 ? 's' : '' ?></small>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

