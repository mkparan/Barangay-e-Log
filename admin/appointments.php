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
    $serviceFilter = $_GET['service'] ?? '';
    $searchBarangayId = trim($_GET['search_barangay_id'] ?? '');
    $dateFilter = $_GET['date_filter'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $redirectFilter = $filter !== 'all' ? "&filter={$filter}" : '';
    $redirectService = $serviceFilter ? "&service=" . urlencode($serviceFilter) : '';
    $redirectSearch = $searchBarangayId ? "&search_barangay_id=" . urlencode($searchBarangayId) : '';
    $redirectDateFilter = $dateFilter ? "&date_filter=" . urlencode($dateFilter) : '';
    $redirectDateFrom = $dateFrom ? "&date_from=" . urlencode($dateFrom) : '';
    $redirectDateTo = $dateTo ? "&date_to=" . urlencode($dateTo) : '';
    
    if ($action === 'approve') {
        // Assign the appointment to the official who approves it
        $stmt = $db->prepare("UPDATE appointments SET status='approved', official_id=? WHERE appointment_id=?");
        $stmt->bind_param('ii', $_SESSION['user']['user_id'], $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_approved', 'appointments', $id);
        header("Location: appointments.php?msg=approved{$redirectFilter}{$redirectService}{$redirectSearch}{$redirectDateFilter}{$redirectDateFrom}{$redirectDateTo}&page=1");
        exit;

    } elseif ($action === 'decline') {
        $stmt = $db->prepare("UPDATE appointments SET status='declined' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_declined', 'appointments', $id);
        header("Location: appointments.php?msg=declined{$redirectFilter}{$redirectService}{$redirectSearch}{$redirectDateFilter}{$redirectDateFrom}{$redirectDateTo}&page=1");
        exit;

    } elseif ($action === 'reschedule') {
        echo "<script>window.location.href='appointment_action.php?id={$id}';</script>";
        exit;

    } elseif ($action === 'complete') {
        $stmt = $db->prepare("UPDATE appointments SET status='completed', processed_by=? WHERE appointment_id=?");
        $stmt->bind_param('ii', $_SESSION['user']['user_id'], $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_completed', 'appointments', $id);
        header("Location: appointments.php?msg=completed{$redirectFilter}{$redirectService}{$redirectSearch}{$redirectDateFilter}{$redirectDateFrom}{$redirectDateTo}&page=1");
        exit;
    }
}

// GET APPOINTMENTS
$filter = $_GET['filter'] ?? 'all';
$serviceFilter = $_GET['service'] ?? '';
$searchBarangayId = trim($_GET['search_barangay_id'] ?? '');
$dateFilter = $_GET['date_filter'] ?? ''; // today, upcoming, past
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$today = date('Y-m-d');
// Default: only show unprocessed appointments (pending, approved, rescheduled)
// Exclude completed, declined, and cancelled - these should only appear in history
$where = "a.status IN ('pending', 'approved', 'rescheduled')";

if ($filter === 'pending') $where = "a.status='pending'";
if ($filter === 'approved') $where = "a.status='approved'";
if ($filter === 'rescheduled') $where = "a.status='rescheduled'";
// Note: declined, completed, and cancelled are not available as filters here
// They can only be viewed in history
if ($filter === 'all') {
    // Even "all" should exclude completed/declined/cancelled
    $where = "a.status IN ('pending', 'approved', 'rescheduled')";
}

if ($serviceFilter) {
    $where .= " AND a.service_type = ?";
}

// Date filtering
if ($dateFilter === 'today') {
    $where .= " AND a.preferred_date = ?";
} elseif ($dateFilter === 'upcoming') {
    $where .= " AND a.preferred_date > ?";
} elseif ($dateFilter === 'past') {
    $where .= " AND a.preferred_date < ?";
} elseif ($dateFrom && $dateTo) {
    $where .= " AND a.preferred_date >= ? AND a.preferred_date <= ?";
} elseif ($dateFrom) {
    $where .= " AND a.preferred_date >= ?";
} elseif ($dateTo) {
    $where .= " AND a.preferred_date <= ?";
}

// Barangay ID search
if ($searchBarangayId) {
    $where .= " AND c.cin LIKE ?";
}

// Pagination - must be before query execution
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get unique services for filter dropdown
$servicesStmt = $db->query("SELECT DISTINCT service_type FROM appointments ORDER BY service_type");
$availableServices = $servicesStmt ? $servicesStmt->fetch_all(MYSQLI_ASSOC) : [];

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
";

// Build count parameters (order must match WHERE clause)
$countParams = [];
$countTypes = '';

// Add service filter FIRST (matches WHERE clause order)
if ($serviceFilter) {
    $countParams[] = $serviceFilter;
    $countTypes .= 's';
}

// Add date filter parameters SECOND (matches WHERE clause order)
if ($dateFilter === 'today') {
    $countParams[] = $today;
    $countTypes .= 's';
} elseif ($dateFilter === 'upcoming') {
    $countParams[] = $today;
    $countTypes .= 's';
} elseif ($dateFilter === 'past') {
    $countParams[] = $today;
    $countTypes .= 's';
} elseif ($dateFrom && $dateTo) {
    $countParams[] = $dateFrom;
    $countParams[] = $dateTo;
    $countTypes .= 'ss';
} elseif ($dateFrom) {
    $countParams[] = $dateFrom;
    $countTypes .= 's';
} elseif ($dateTo) {
    $countParams[] = $dateTo;
    $countTypes .= 's';
}

// Add Barangay ID search THIRD (matches WHERE clause order)
if ($searchBarangayId) {
    $countParams[] = "%{$searchBarangayId}%";
    $countTypes .= 's';
}

// Execute count query
if (!empty($countParams)) {
    $countStmt = $db->prepare($countSql);
    $countStmt->bind_param($countTypes, ...$countParams);
    $countStmt->execute();
    $totalCount = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
} else {
    $countResult = $db->query($countSql);
    $totalCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, ceil($totalCount / $perPage));

// Build parameters array for binding (order must match WHERE clause)
$bindParams = [];
$bindTypes = '';

// Add service filter FIRST (matches WHERE clause order)
if ($serviceFilter) {
    $bindParams[] = $serviceFilter;
    $bindTypes .= 's';
}

// Add date filter parameters SECOND (matches WHERE clause order)
if ($dateFilter === 'today') {
    $bindParams[] = $today;
    $bindTypes .= 's';
} elseif ($dateFilter === 'upcoming') {
    $bindParams[] = $today;
    $bindTypes .= 's';
} elseif ($dateFilter === 'past') {
    $bindParams[] = $today;
    $bindTypes .= 's';
} elseif ($dateFrom && $dateTo) {
    $bindParams[] = $dateFrom;
    $bindParams[] = $dateTo;
    $bindTypes .= 'ss';
} elseif ($dateFrom) {
    $bindParams[] = $dateFrom;
    $bindTypes .= 's';
} elseif ($dateTo) {
    $bindParams[] = $dateTo;
    $bindTypes .= 's';
}

// Add Barangay ID search THIRD (matches WHERE clause order)
if ($searchBarangayId) {
    $bindParams[] = "%{$searchBarangayId}%";
    $bindTypes .= 's';
}

// Build SQL - use placeholders for LIMIT/OFFSET if we have any filters
if (!empty($bindParams)) {
    $sql = "
        SELECT a.*, c.first_name, c.last_name, c.cin, c.profile_picture, p.full_name AS processed_by_name
        FROM appointments a
        JOIN citizens c ON a.citizen_id = c.citizen_id
        LEFT JOIN users p ON a.processed_by = p.user_id
        WHERE $where
        ORDER BY a.created_at DESC
        LIMIT ? OFFSET ?
    ";
    // Add pagination parameters
    $bindParams[] = $perPage;
    $bindParams[] = $offset;
    $bindTypes .= 'ii';
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // No filters - use direct query
    $sql = "
        SELECT a.*, c.first_name, c.last_name, c.cin, c.profile_picture, p.full_name AS processed_by_name
        FROM appointments a
        JOIN citizens c ON a.citizen_id = c.citizen_id
        LEFT JOIN users p ON a.processed_by = p.user_id
        WHERE $where
        ORDER BY a.created_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $db->query($sql);
    $appointments = $stmt->fetch_all(MYSQLI_ASSOC);
}

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

  <!-- Search by Barangay ID with Date Filters -->
  <div class="card bg-primary text-white mb-3">
    <div class="card-body">
      <form method="get" action="appointments.php">
        <input type="hidden" name="filter" value="<?= esc($filter) ?>">
        <input type="hidden" name="service" value="<?= esc($serviceFilter) ?>">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label text-white fw-bold mb-2">
              <i class="bi bi-search me-2"></i>Search by Barangay ID
            </label>
            <input type="text" 
                   name="search_barangay_id" 
                   class="form-control" 
                   placeholder="Enter Barangay ID..." 
                   value="<?= esc($searchBarangayId) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label text-white fw-bold mb-2">Date Filter</label>
            <select name="date_filter" class="form-select">
              <option value="">All Dates</option>
              <option value="today" <?= $dateFilter === 'today' ? 'selected' : '' ?>>Today</option>
              <option value="upcoming" <?= $dateFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
              <option value="past" <?= $dateFilter === 'past' ? 'selected' : '' ?>>Past</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label text-white fw-bold mb-2">Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?= esc($dateFrom) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label text-white fw-bold mb-2">Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?= esc($dateTo) ?>">
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-light w-100">
              <i class="bi bi-search"></i>
            </button>
          </div>
        </div>
        <?php if ($searchBarangayId || $dateFilter || $dateFrom || $dateTo): ?>
          <div class="mt-2">
            <a href="appointments.php?filter=<?= esc($filter) ?><?= $serviceFilter ? '&service=' . urlencode($serviceFilter) : '' ?>&page=1" class="btn btn-sm btn-outline-light">
              <i class="bi bi-x"></i> Clear Search
            </a>
          </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <?php if ($searchBarangayId || $dateFilter || $dateFrom || $dateTo): ?>
    <div class="alert alert-info alert-dismissible fade show mb-3">
      <i class="bi bi-info-circle"></i> 
      <?php if ($searchBarangayId): ?>
        Showing results for Barangay ID: <strong><?= esc($searchBarangayId) ?></strong>
      <?php endif; ?>
      <?php if ($dateFilter === 'today'): ?>
        | Filter: <strong>Today</strong>
      <?php elseif ($dateFilter === 'upcoming'): ?>
        | Filter: <strong>Upcoming</strong>
      <?php elseif ($dateFilter === 'past'): ?>
        | Filter: <strong>Past</strong>
      <?php elseif ($dateFrom || $dateTo): ?>
        | Date Range: <strong><?= $dateFrom ? esc($dateFrom) : 'Any' ?> to <?= $dateTo ? esc($dateTo) : 'Any' ?></strong>
      <?php endif; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex flex-wrap gap-2">
      <?php
      $filterParams = '';
      if ($serviceFilter) $filterParams .= '&service=' . urlencode($serviceFilter);
      if ($searchBarangayId) $filterParams .= '&search_barangay_id=' . urlencode($searchBarangayId);
      if ($dateFilter) $filterParams .= '&date_filter=' . urlencode($dateFilter);
      if ($dateFrom) $filterParams .= '&date_from=' . urlencode($dateFrom);
      if ($dateTo) $filterParams .= '&date_to=' . urlencode($dateTo);
      ?>
      <a href="appointments.php?filter=all<?= $filterParams ?>&page=1" class="btn btn-outline-primary btn-sm">All</a>
      <a href="appointments.php?filter=pending<?= $filterParams ?>&page=1" class="btn btn-outline-warning btn-sm">Pending</a>
      <a href="appointments.php?filter=approved<?= $filterParams ?>&page=1" class="btn btn-outline-success btn-sm">Approved</a>
      <a href="appointments.php?filter=declined<?= $filterParams ?>&page=1" class="btn btn-outline-danger btn-sm">Declined</a>
      <a href="appointments.php?filter=cancelled<?= $filterParams ?>&page=1" class="btn btn-outline-warning btn-sm">Cancelled</a>
      <a href="appointments.php?filter=completed<?= $filterParams ?>&page=1" class="btn btn-outline-secondary btn-sm">Completed</a>
    </div>
    <div class="d-flex align-items-center gap-2">
      <select class="form-select form-select-sm w-auto" onchange="window.location.href='appointments.php?filter=<?= esc($filter) ?>&service=' + encodeURIComponent(this.value) + '<?= $searchBarangayId ? '&search_barangay_id=' . urlencode($searchBarangayId) : '' ?><?= $dateFilter ? '&date_filter=' . urlencode($dateFilter) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>&page=1'">
        <option value="">All Services</option>
        <?php foreach($availableServices as $s): ?>
          <option value="<?= esc($s['service_type']) ?>" <?= $serviceFilter === $s['service_type'] ? 'selected' : '' ?>>
            <?= esc($s['service_type']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="badge bg-primary"><?= number_format($totalCount) ?> total</span>
    </div>
  </div>

    <div class="table-responsive scrollable-table">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light sticky-top">
        <tr>
          <th>ID</th>
          <th>Citizen</th>
          <th>Barangay ID</th>
          <th>Service</th>
          <th>Date</th>
          <th>Queue / Time</th>
          <th>Status</th>
          <th>Processed By</th>
          <th width="200">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($appointments)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted">
              <?php if ($searchBarangayId || $dateFilter || $dateFrom || $dateTo): ?>
                No appointments found matching your search criteria.
              <?php else: ?>
                No appointments found.
              <?php endif; ?>
            </td>
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
                     class="rounded-circle me-2 profile-picture-md">
                <span><?= esc($a['first_name'].' '.$a['last_name']) ?></span>
              </div>
            </td>
            <td><strong><?= esc($a['cin']) ?></strong></td>
            <td><?= esc($a['service_type']) ?></td>
            <td><?= esc($a['preferred_date']) ?></td>
            <td>
              <span class="badge bg-secondary"><?= esc($a['queue_number']) ?></span>
              <?php if (!empty($a['time_slot'])): ?>
                <span class="badge bg-info ms-1"><?= ucfirst(esc($a['time_slot'])) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php
              $status = strtolower($a['status'] ?? '');
              $badgeClass = 'bg-secondary';
              if ($status === 'completed') $badgeClass = 'bg-success';
              elseif ($status === 'declined') $badgeClass = 'bg-danger';
              elseif ($status === 'cancelled') $badgeClass = 'bg-warning text-dark';
              elseif ($status === 'approved') $badgeClass = 'bg-info text-dark';
              elseif ($status === 'pending') $badgeClass = 'bg-warning text-dark';
              ?>
              <span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($a['status']) ?></span>
            </td>
            <td>
              <?php if (!empty($a['processed_by_name']) && $a['status'] === 'completed'): ?>
                <small><?= esc($a['processed_by_name']) ?></small>
              <?php else: ?>
                <small class="text-muted">—</small>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex flex-wrap gap-1">
                <?php 
                  $actionParams = "filter=" . esc($filter);
                  if ($serviceFilter) {
                    $actionParams .= "&service=" . urlencode($serviceFilter);
                  }
                  if ($searchBarangayId) {
                    $actionParams .= "&search_barangay_id=" . urlencode($searchBarangayId);
                  }
                  if ($dateFilter) {
                    $actionParams .= "&date_filter=" . urlencode($dateFilter);
                  }
                  if ($dateFrom) {
                    $actionParams .= "&date_from=" . urlencode($dateFrom);
                  }
                  if ($dateTo) {
                    $actionParams .= "&date_to=" . urlencode($dateTo);
                  }
                ?>
                <?php if ($a['status'] === 'pending'): ?>
                  <a class="btn btn-success btn-sm" href="?action=approve&id=<?= esc($a['appointment_id']) ?>&<?= $actionParams ?>">Approve</a>
                  <a class="btn btn-warning btn-sm" href="?action=reschedule&id=<?= esc($a['appointment_id']) ?>&<?= $actionParams ?>">Reschedule</a>
                  <a class="btn btn-danger btn-sm" href="?action=decline&id=<?= esc($a['appointment_id']) ?>&<?= $actionParams ?>">Decline</a>
                  <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>&<?= $actionParams ?>">Mark Released</a>
                <?php elseif ($a['status'] === 'approved'): ?>
                  <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>&<?= $actionParams ?>">Mark Released</a>
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
        <?php 
          $paginationParams = "filter=" . esc($filter);
          if ($serviceFilter) {
            $paginationParams .= "&service=" . urlencode($serviceFilter);
          }
          if ($searchBarangayId) {
            $paginationParams .= "&search_barangay_id=" . urlencode($searchBarangayId);
          }
          if ($dateFilter) {
            $paginationParams .= "&date_filter=" . urlencode($dateFilter);
          }
          if ($dateFrom) {
            $paginationParams .= "&date_from=" . urlencode($dateFrom);
          }
          if ($dateTo) {
            $paginationParams .= "&date_to=" . urlencode($dateTo);
          }
        ?>
        <?php if($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $paginationParams ?>&page=<?= $page - 1 ?>">Previous</a>
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
            <a class="page-link" href="?<?= $paginationParams ?>&page=1">1</a>
          </li>
          <?php if ($startPage > 2): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
        <?php endif; ?>
        
        <?php for($i = $startPage; $i <= $endPage; $i++): ?>
          <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= $paginationParams ?>&page=<?= $i ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
          <?php if ($endPage < $totalPages - 1): ?>
            <li class="page-item disabled"><span class="page-link">...</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $paginationParams ?>&page=<?= $totalPages ?>"><?= $totalPages ?></a>
          </li>
        <?php endif; ?>
        
        <?php if($page < $totalPages): ?>
          <li class="page-item">
            <a class="page-link" href="?<?= $paginationParams ?>&page=<?= $page + 1 ?>">Next</a>
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

