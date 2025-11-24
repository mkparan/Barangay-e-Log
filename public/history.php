<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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

// Handle cancel appointment action FIRST, before any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $appointmentId = (int)$_GET['id'];
    
    // Verify the appointment belongs to this citizen
    $verifyStmt = $db->prepare("SELECT appointment_id, status FROM appointments WHERE appointment_id = ? AND citizen_id = ?");
    $verifyStmt->bind_param('ii', $appointmentId, $citizen['citizen_id']);
    $verifyStmt->execute();
    $appointment = $verifyStmt->get_result()->fetch_assoc();
    
    if ($appointment) {
        $status = strtolower($appointment['status'] ?? '');
        // Only allow cancellation if pending or approved (not completed/declined)
        if (in_array($status, ['pending', 'approved'])) {
            $cancelStmt = $db->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ? AND citizen_id = ?");
            $cancelStmt->bind_param('ii', $appointmentId, $citizen['citizen_id']);
            if ($cancelStmt->execute()) {
                audit_log($citizen['cin'], null, 'appointment_cancelled', 'appointments', $appointmentId);
                $_SESSION['appointment_cancelled'] = true;
                // Preserve filters in redirect
                $redirectUrl = 'history.php?cancelled=1';
                if (!empty($_GET['date_from'])) $redirectUrl .= '&date_from=' . urlencode($_GET['date_from']);
                if (!empty($_GET['date_to'])) $redirectUrl .= '&date_to=' . urlencode($_GET['date_to']);
                if (!empty($_GET['service'])) $redirectUrl .= '&service=' . urlencode($_GET['service']);
                if (!empty($_GET['page'])) $redirectUrl .= '&page=' . urlencode($_GET['page']);
                header('Location: ' . $redirectUrl);
                exit;
            }
        }
    }
    // Preserve filters in redirect
    $redirectUrl = 'history.php';
    if (!empty($_GET['date_from'])) $redirectUrl .= '?date_from=' . urlencode($_GET['date_from']);
    if (!empty($_GET['date_to'])) $redirectUrl .= '&date_to=' . urlencode($_GET['date_to']);
    if (!empty($_GET['service'])) $redirectUrl .= '&service=' . urlencode($_GET['service']);
    header('Location: ' . $redirectUrl);
    exit;
}

$page_title = "Appointment History - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';

// Get filter parameters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_service = $_GET['service'] ?? '';

// Build query with filters
$where = "a.citizen_id = ?";
$params = [$citizen['citizen_id']];
$types = 'i';

if ($filter_date_from) {
    $where .= " AND a.preferred_date >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if ($filter_date_to) {
    $where .= " AND a.preferred_date <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

if ($filter_service) {
    $where .= " AND a.service_type = ?";
    $params[] = $filter_service;
    $types .= 's';
}

// Pagination - must be before query execution
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id WHERE $where";
$countStmt = $db->prepare($countSql);
$countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalCount = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $perPage));

// Get unique services for filter dropdown
$servicesStmt = $db->prepare("SELECT DISTINCT service_type FROM appointments WHERE citizen_id = ? ORDER BY service_type");
$servicesStmt->bind_param('i', $citizen['citizen_id']);
$servicesStmt->execute();
$availableServices = $servicesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT a.*, u.full_name AS official_name, p.full_name AS processed_by_name FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id LEFT JOIN users p ON a.processed_by = p.user_id WHERE $where ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Appointment History</h4>
  
  <?php if (!empty($_GET['cancelled'])): ?>
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    Appointment cancelled successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
  <?php endif; ?>
  
  <form method="get" class="row g-3 mb-4">
    <div class="col-md-3">
      <label class="form-label">Date From</label>
      <input type="date" name="date_from" class="form-control" value="<?= esc($filter_date_from) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Date To</label>
      <input type="date" name="date_to" class="form-control" value="<?= esc($filter_date_to) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">Service Type</label>
      <select name="service" class="form-select">
        <option value="">All Services</option>
        <?php foreach($availableServices as $s): ?>
          <option value="<?= esc($s['service_type']) ?>" <?= $filter_service === $s['service_type'] ? 'selected' : '' ?>>
            <?= esc($s['service_type']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100 me-2">Filter</button>
      <a href="history.php" class="btn btn-outline-secondary">Clear</a>
    </div>
  </form>

  <?php if(empty($appointments)): ?>
    <div class="alert alert-info">No appointments found matching your filters.</div>
  <?php else: ?>
    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light sticky-top">
          <tr>
            <th>Service</th>
            <th>Date</th>
            <th>Queue</th>
            <th>Status</th>
            <th>Official</th>
            <th>Created</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($appointments as $a): 
            $status = strtolower($a['status'] ?? '');
            $badgeClass = 'bg-secondary';
            if ($status === 'completed') $badgeClass = 'bg-success';
            elseif ($status === 'declined') $badgeClass = 'bg-danger';
            elseif ($status === 'cancelled') $badgeClass = 'bg-warning text-dark';
            elseif ($status === 'approved') $badgeClass = 'bg-info';
            elseif ($status === 'pending') $badgeClass = 'bg-warning';
            $canCancel = in_array($status, ['pending', 'approved']);
            
            // Build cancel URL with filters preserved
            $cancelUrl = '?action=cancel&id=' . esc($a['appointment_id']);
            if ($filter_date_from) $cancelUrl .= '&date_from=' . urlencode($filter_date_from);
            if ($filter_date_to) $cancelUrl .= '&date_to=' . urlencode($filter_date_to);
            if ($filter_service) $cancelUrl .= '&service=' . urlencode($filter_service);
            if ($page > 1) $cancelUrl .= '&page=' . $page;
          ?>
            <tr>
              <td><?= esc($a['service_type']) ?></td>
              <td><?= esc($a['preferred_date']) ?></td>
              <td><span class="badge bg-secondary"><?= esc($a['queue_number']) ?></span></td>
              <td>
                <span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($a['status']) ?></span>
              </td>
              <td>
                <?= esc($a['official_name'] ?? 'Unassigned') ?>
                <?php if (!empty($a['processed_by_name']) && $a['status'] === 'completed'): ?>
                  <br><small class="text-muted">Processed by: <?= esc($a['processed_by_name']) ?></small>
                <?php endif; ?>
              </td>
              <td><?= esc(date('M d, Y', strtotime($a['created_at'] ?? $a['preferred_date']))) ?></td>
              <td>
                <?php if ($canCancel): ?>
                  <a href="<?= $cancelUrl ?>" 
                     class="btn btn-sm btn-outline-danger" 
                     onclick="return confirm('Are you sure you want to cancel this appointment?');"
                     title="Cancel Appointment">
                    Cancel
                  </a>
                <?php else: ?>
                  <small class="text-muted">-</small>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php if($totalPages > 1): ?>
      <nav aria-label="Page navigation" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <?php if($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?>">Previous</a>
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
              <a class="page-link" href="?page=1<?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?>">1</a>
            </li>
            <?php if ($startPage > 2): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
          <?php endif; ?>
          
          <?php for($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          
          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $totalPages ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?>"><?= $totalPages ?></a>
            </li>
          <?php endif; ?>
          
          <?php if($page < $totalPages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?>">Next</a>
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
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

