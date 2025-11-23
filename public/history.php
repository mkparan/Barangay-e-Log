<?php
$page_title = "Appointment History - Barangay e-Log";
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

$sql = "SELECT a.*, u.full_name AS official_name FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id WHERE $where ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
$bindTypes = $types . 'ii';
$bindParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($bindTypes, ...$bindParams);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Appointment History</h4>
  
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
          </tr>
        </thead>
        <tbody>
          <?php foreach($appointments as $a): ?>
            <tr>
              <td><?= esc($a['service_type']) ?></td>
              <td><?= esc($a['preferred_date']) ?></td>
              <td><?= esc($a['queue_number']) ?></td>
              <td>
                <?php
                $status = strtolower($a['status'] ?? '');
                $badgeClass = 'bg-secondary';
                if ($status === 'completed') $badgeClass = 'bg-success';
                elseif ($status === 'declined') $badgeClass = 'bg-danger';
                elseif ($status === 'approved') $badgeClass = 'bg-info';
                elseif ($status === 'pending') $badgeClass = 'bg-warning';
                ?>
                <span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($a['status']) ?></span>
              </td>
              <td><?= esc($a['official_name'] ?? 'Unassigned') ?></td>
              <td><?= esc(date('M d, Y', strtotime($a['created_at'] ?? $a['preferred_date']))) ?></td>
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

