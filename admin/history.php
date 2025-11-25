<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();

// Get filter parameters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_service = $_GET['service'] ?? '';
$filter_status = $_GET['status'] ?? '';
$search_barangay_id = trim($_GET['search_barangay_id'] ?? '');

// Build query with filters
$where = "1";
$params = [];
$types = '';

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

if ($filter_status) {
    $where .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

if ($search_barangay_id) {
    $where .= " AND c.cin LIKE ?";
    $params[] = "%{$search_barangay_id}%";
    $types .= 's';
}

// Pagination - must be before query execution
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
$countSql = "
    SELECT COUNT(*) as total
    FROM appointments a
    LEFT JOIN users u ON a.official_id = u.user_id
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
";

// Execute count query with same parameters
if (!empty($params)) {
    $countStmt = $db->prepare($countSql);
    if ($countStmt) {
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    } else {
        $totalCount = 0;
    }
} else {
    $countResult = $db->query($countSql);
    $totalCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, ceil($totalCount / $perPage));

// Get unique services for filter dropdown
$servicesStmt = $db->query("SELECT DISTINCT service_type FROM appointments ORDER BY service_type");
$availableServices = $servicesStmt ? $servicesStmt->fetch_all(MYSQLI_ASSOC) : [];

// Get unique statuses for filter dropdown
$statusesStmt = $db->query("SELECT DISTINCT status FROM appointments ORDER BY status");
$availableStatuses = $statusesStmt ? $statusesStmt->fetch_all(MYSQLI_ASSOC) : [];

$sql = "
    SELECT 
        a.*,
        u.full_name AS official_name,
        p.full_name AS processed_by_name,
        c.first_name,
        c.last_name,
        c.cin,
        c.profile_picture
    FROM appointments a
    LEFT JOIN users u ON a.official_id = u.user_id
    LEFT JOIN users p ON a.processed_by = p.user_id
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT ? OFFSET ?
";

// Execute main query
if (!empty($params)) {
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $bindTypes = $types . 'ii';
        $bindParams = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($bindTypes, ...$bindParams);
        $stmt->execute();
        $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $appointments = [];
    }
} else {
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $appointments = [];
    }
}

// Chart filters
$chart_date_from = $_GET['chart_date_from'] ?? date('Y-m-01', strtotime('-11 months'));
$chart_date_to = $_GET['chart_date_to'] ?? date('Y-m-t');
$pie_month = $_GET['pie_month'] ?? date('m');
$pie_year = $_GET['pie_year'] ?? date('Y');

// Validate and limit date range (max 90 days for performance)
$startDate = new DateTime($chart_date_from);
$endDate = new DateTime($chart_date_to);
$daysDiff = $startDate->diff($endDate)->days;

if ($daysDiff > 90) {
    // Limit to last 90 days if range is too large
    $chart_date_to = date('Y-m-d');
    $chart_date_from = date('Y-m-d', strtotime('-90 days'));
    $endDate = new DateTime($chart_date_to);
    $startDate = new DateTime($chart_date_from);
}

// Line chart data (appointments by date)
$chartStartDate = $chart_date_from;
$chartEndDate = $chart_date_to;
$chartStmt = $db->prepare("
    SELECT DATE(created_at) AS date, COUNT(*) AS total 
    FROM appointments 
    WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$chartStmt->bind_param('ss', $chartStartDate, $chartEndDate);
$chartStmt->execute();
$chartData = $chartStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Create date range for chart (limit to 90 days max, use daily or weekly grouping)
$chartLabels = [];
$chartValues = [];
$chartDataMap = [];

foreach ($chartData as $row) {
    $chartDataMap[$row['date']] = (int)$row['total'];
}

// If range is large, group by week; otherwise daily
if ($daysDiff > 30) {
    // Group by week for better performance
    $current = clone $startDate;
    $current->modify('monday this week'); // Start from Monday
    while ($current <= $endDate) {
        $weekEnd = clone $current;
        $weekEnd->modify('+6 days');
        if ($weekEnd > $endDate) $weekEnd = clone $endDate;
        
        $weekTotal = 0;
        $checkDate = clone $current;
        while ($checkDate <= $weekEnd) {
            $dateStr = $checkDate->format('Y-m-d');
            $weekTotal += $chartDataMap[$dateStr] ?? 0;
            $checkDate->modify('+1 day');
        }
        
        $chartLabels[] = $current->format('M d') . ' - ' . $weekEnd->format('M d');
        $chartValues[] = $weekTotal;
        $current->modify('+7 days');
    }
} else {
    // Daily grouping
    $current = clone $startDate;
    while ($current <= $endDate) {
        $dateStr = $current->format('Y-m-d');
        $chartLabels[] = $current->format('M d');
        $chartValues[] = $chartDataMap[$dateStr] ?? 0;
        $current->modify('+1 day');
    }
}

// Pie chart data (services by month/year)
$pieStartDate = $pie_year . '-' . $pie_month . '-01';
$pieEndDate = $pie_year . '-' . $pie_month . '-' . date('t', strtotime($pieStartDate));
$pieStmt = $db->prepare("
    SELECT service_type, COUNT(*) AS total 
    FROM appointments 
    WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
    GROUP BY service_type
    ORDER BY total DESC
");
$pieStmt->bind_param('ss', $pieStartDate, $pieEndDate);
$pieStmt->execute();
$pieData = $pieStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pieLabels = [];
$pieValues = [];
$pieColors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#0dcaf0'];
foreach ($pieData as $idx => $row) {
    $pieLabels[] = $row['service_type'];
    $pieValues[] = (int)$row['total'];
}

$page_title = "Appointment History";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Appointment History</h4>
  <p class="text-muted mb-4">View all appointment records and transactions. This serves as the barangay's official appointment log.</p>
  
  <!-- Separate form for Barangay ID search -->
  <form method="get" action="history.php" class="mb-3">
    <div class="card bg-primary text-white">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-md-10">
            <label class="form-label text-white fw-bold mb-2">
              <i class="bi bi-search me-2"></i>Search by Barangay ID
            </label>
            <input type="text" 
                   name="search_barangay_id" 
                   class="form-control" 
                   placeholder="Enter Barangay ID to search appointment history..." 
                   value="<?= esc($search_barangay_id) ?>"
                   autofocus>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-light w-100">
              <i class="bi bi-search"></i> Search
            </button>
          </div>
        </div>
        <?php if ($search_barangay_id): ?>
          <div class="mt-2">
            <a href="history.php?<?= $filter_date_from ? 'date_from='.urlencode($filter_date_from).'&' : '' ?><?= $filter_date_to ? 'date_to='.urlencode($filter_date_to).'&' : '' ?><?= $filter_service ? 'service='.urlencode($filter_service).'&' : '' ?><?= $filter_status ? 'status='.urlencode($filter_status) : '' ?>" class="btn btn-sm btn-outline-light">
              <i class="bi bi-x"></i> Clear Search
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </form>
  
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
    <div class="col-md-3">
      <label class="form-label">Status</label>
      <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <?php foreach($availableStatuses as $st): ?>
          <option value="<?= esc($st['status']) ?>" <?= $filter_status === $st['status'] ? 'selected' : '' ?>>
            <?= esc(ucfirst($st['status'])) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-12">
      <?php if ($search_barangay_id): ?>
        <input type="hidden" name="search_barangay_id" value="<?= esc($search_barangay_id) ?>">
      <?php endif; ?>
      <button type="submit" class="btn btn-primary me-2">Filter</button>
      <a href="history.php<?= $search_barangay_id ? '?search_barangay_id='.urlencode($search_barangay_id) : '' ?>" class="btn btn-outline-secondary">Clear Date/Service Filters</a>
    </div>
  </form>
  
  <?php if ($search_barangay_id): ?>
    <div class="alert alert-info alert-dismissible fade show mb-3">
      <i class="bi bi-info-circle"></i> Showing appointment history for Barangay ID: <strong><?= esc($search_barangay_id) ?></strong>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-lg-4">
      <div class="container-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Appointments Over Time</h5>
        </div>
        <div class="mb-3">
          <label class="form-label small">Date From</label>
          <input type="date" id="chartDateFrom" class="form-control form-control-sm" value="<?= esc($chart_date_from) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small">Date To</label>
          <input type="date" id="chartDateTo" class="form-control form-control-sm" value="<?= esc($chart_date_to) ?>">
        </div>
        <button type="button" class="btn btn-sm btn-primary w-100 mb-3" onclick="updateChart()">Update Chart</button>
        <?php if(empty($chartLabels)): ?>
          <div class="alert alert-info mb-0">No appointment data for the selected date range.</div>
        <?php else: ?>
          <div style="height: 250px;">
            <canvas id="lineChart"></canvas>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="container-box mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Services by Month</h5>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small">Month</label>
            <select id="pieMonth" class="form-select form-select-sm">
              <?php for($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= $pie_month == sprintf('%02d', $m) ? 'selected' : '' ?>>
                  <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small">Year</label>
            <select id="pieYear" class="form-select form-select-sm">
              <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $pie_year == $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-primary w-100 mb-3" onclick="updatePieChart()">Update Chart</button>
        <?php if(empty($pieLabels)): ?>
          <div class="alert alert-info mb-0">No appointment data for this period.</div>
        <?php else: ?>
          <div style="height: 250px;">
            <canvas id="pieChart"></canvas>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="col-lg-8">
      <div class="container-box">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Appointment Records</h5>
          <span class="badge bg-primary"><?= number_format($totalCount) ?> total</span>
        </div>
        
        <?php if(empty($appointments)): ?>
          <div class="alert alert-info">No appointments found matching your filters.</div>
        <?php else: ?>
          <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Citizen</th>
            <th>Barangay ID</th>
            <th>Service</th>
            <th>Date</th>
            <th>Queue</th>
            <th>Status</th>
            <th>Official</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($appointments as $a): 
            $citizenPic = !empty($a['profile_picture']) ? $a['profile_picture'] : null;
            $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="20" fill="#6c757d">' . strtoupper(substr($a['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
            
            $status = strtolower($a['status'] ?? '');
            $badgeClass = 'bg-secondary';
            if ($status === 'completed') $badgeClass = 'bg-success';
            elseif ($status === 'declined') $badgeClass = 'bg-danger';
            elseif ($status === 'cancelled') $badgeClass = 'bg-warning text-dark';
            elseif ($status === 'approved') $badgeClass = 'bg-info';
            elseif ($status === 'pending') $badgeClass = 'bg-warning';
          ?>
            <tr>
              <td><?= esc($a['appointment_id']) ?></td>
              <td>
                <div class="d-flex align-items-center">
                  <img src="<?= $citizenPic ? esc('/elog_barangay/public/' . $citizenPic) : $defaultPic ?>" 
                       alt="Profile" 
                       class="rounded-circle me-2" 
                       style="width: 40px; height: 40px; object-fit: cover;">
                  <div>
                    <div><?= esc($a['first_name'].' '.$a['last_name']) ?></div>
                  </div>
                </div>
              </td>
              <td><strong><?= esc($a['cin']) ?></strong></td>
              <td><?= esc($a['service_type']) ?></td>
              <td><?= esc($a['preferred_date']) ?></td>
              <td><?= esc($a['queue_number']) ?></td>
              <td>
                <span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($a['status']) ?></span>
              </td>
              <td>
                <?= esc($a['official_name'] ?? 'Unassigned') ?>
                <?php if (!empty($a['processed_by_name']) && $a['status'] === 'completed'): ?>
                  <br><small class="text-muted">Processed by: <?= esc($a['processed_by_name']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <small><?= esc(date('M d, Y', strtotime($a['created_at']))) ?></small><br>
                <small class="text-muted"><?= esc(date('h:i A', strtotime($a['created_at']))) ?></small>
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
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?><?= $filter_status ? '&status='.urlencode($filter_status) : '' ?><?= $search_barangay_id ? '&search_barangay_id='.urlencode($search_barangay_id) : '' ?><?= $chart_date_from ? '&chart_date_from='.urlencode($chart_date_from) : '' ?><?= $chart_date_to ? '&chart_date_to='.urlencode($chart_date_to) : '' ?><?= $pie_month ? '&pie_month='.urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year='.urlencode($pie_year) : '' ?>">Previous</a>
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
                    <a class="page-link" href="?page=1<?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?><?= $filter_status ? '&status='.urlencode($filter_status) : '' ?><?= $search_barangay_id ? '&search_barangay_id='.urlencode($search_barangay_id) : '' ?><?= $chart_date_from ? '&chart_date_from='.urlencode($chart_date_from) : '' ?><?= $chart_date_to ? '&chart_date_to='.urlencode($chart_date_to) : '' ?><?= $pie_month ? '&pie_month='.urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year='.urlencode($pie_year) : '' ?>">1</a>
                  </li>
                  <?php if ($startPage > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>
                
                <?php for($i = $startPage; $i <= $endPage; $i++): ?>
                  <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?><?= $filter_status ? '&status='.urlencode($filter_status) : '' ?><?= $search_barangay_id ? '&search_barangay_id='.urlencode($search_barangay_id) : '' ?><?= $chart_date_from ? '&chart_date_from='.urlencode($chart_date_from) : '' ?><?= $chart_date_to ? '&chart_date_to='.urlencode($chart_date_to) : '' ?><?= $pie_month ? '&pie_month='.urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year='.urlencode($pie_year) : '' ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                  <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?= $totalPages ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?><?= $filter_status ? '&status='.urlencode($filter_status) : '' ?><?= $search_barangay_id ? '&search_barangay_id='.urlencode($search_barangay_id) : '' ?><?= $chart_date_from ? '&chart_date_from='.urlencode($chart_date_from) : '' ?><?= $chart_date_to ? '&chart_date_to='.urlencode($chart_date_to) : '' ?><?= $pie_month ? '&pie_month='.urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year='.urlencode($pie_year) : '' ?>"><?= $totalPages ?></a>
                  </li>
                <?php endif; ?>
                
                <?php if($page < $totalPages): ?>
                  <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $filter_date_from ? '&date_from='.urlencode($filter_date_from) : '' ?><?= $filter_date_to ? '&date_to='.urlencode($filter_date_to) : '' ?><?= $filter_service ? '&service='.urlencode($filter_service) : '' ?><?= $filter_status ? '&status='.urlencode($filter_status) : '' ?><?= $search_barangay_id ? '&search_barangay_id='.urlencode($search_barangay_id) : '' ?><?= $chart_date_from ? '&chart_date_from='.urlencode($chart_date_from) : '' ?><?= $chart_date_to ? '&chart_date_to='.urlencode($chart_date_to) : '' ?><?= $pie_month ? '&pie_month='.urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year='.urlencode($pie_year) : '' ?>">Next</a>
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
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  // Line Chart
  const lineCtx = document.getElementById('lineChart');
  if (lineCtx) {
    const lineData = {
      labels: <?= json_encode($chartLabels) ?>,
      datasets: [{
        label: 'Appointments',
        data: <?= json_encode($chartValues) ?>,
        borderColor: '#0d6efd',
        backgroundColor: 'rgba(13,110,253,0.2)',
        tension: 0.3,
        fill: true,
        borderWidth: 2
      }]
    };
    
    new Chart(lineCtx, {
      type: 'line',
      data: lineData,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: { precision: 0 }
          },
          x: {
            ticks: {
              maxRotation: 45,
              minRotation: 45
            }
          }
        },
        plugins: {
          legend: { display: true, position: 'bottom' }
        }
      }
    });
  }
  
  // Pie Chart
  const pieCtx = document.getElementById('pieChart');
  if (pieCtx) {
    const pieLabelsData = <?= json_encode($pieLabels) ?>;
    const pieValuesData = <?= json_encode($pieValues) ?>;
    
    if (pieLabelsData.length > 0 && pieValuesData.length > 0) {
      const pieData = {
        labels: pieLabelsData,
        datasets: [{
          data: pieValuesData,
          backgroundColor: <?= json_encode(array_slice($pieColors, 0, max(count($pieLabels), 1))) ?>
        }]
      };
      
      new Chart(pieCtx, {
        type: 'pie',
        data: pieData,
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { 
              display: true, 
              position: 'bottom',
              labels: {
                boxWidth: 12,
                padding: 8,
                font: { size: 11 }
              }
            }
          }
        }
      });
    } else {
      // Show message if no data
      pieCtx.parentElement.innerHTML = '<div class="alert alert-info mb-0">No appointment data for this period.</div>';
    }
  }
  
  window.updateChart = function() {
    const from = document.getElementById('chartDateFrom').value;
    const to = document.getElementById('chartDateTo').value;
    const url = new URL(window.location);
    url.searchParams.set('chart_date_from', from);
    url.searchParams.set('chart_date_to', to);
    // Preserve other filters
    <?php if($filter_date_from): ?>url.searchParams.set('date_from', '<?= esc($filter_date_from) ?>');<?php endif; ?>
    <?php if($filter_date_to): ?>url.searchParams.set('date_to', '<?= esc($filter_date_to) ?>');<?php endif; ?>
    <?php if($filter_service): ?>url.searchParams.set('service', '<?= esc($filter_service) ?>');<?php endif; ?>
    <?php if($filter_status): ?>url.searchParams.set('status', '<?= esc($filter_status) ?>');<?php endif; ?>
    <?php if($search_barangay_id): ?>url.searchParams.set('search_barangay_id', '<?= esc($search_barangay_id) ?>');<?php endif; ?>
    <?php if($pie_month): ?>url.searchParams.set('pie_month', '<?= esc($pie_month) ?>');<?php endif; ?>
    <?php if($pie_year): ?>url.searchParams.set('pie_year', '<?= esc($pie_year) ?>');<?php endif; ?>
    window.location = url.toString();
  };
  
  window.updatePieChart = function() {
    const month = document.getElementById('pieMonth').value;
    const year = document.getElementById('pieYear').value;
    const url = new URL(window.location);
    url.searchParams.set('pie_month', month);
    url.searchParams.set('pie_year', year);
    // Preserve other filters
    <?php if($filter_date_from): ?>url.searchParams.set('date_from', '<?= esc($filter_date_from) ?>');<?php endif; ?>
    <?php if($filter_date_to): ?>url.searchParams.set('date_to', '<?= esc($filter_date_to) ?>');<?php endif; ?>
    <?php if($filter_service): ?>url.searchParams.set('service', '<?= esc($filter_service) ?>');<?php endif; ?>
    <?php if($filter_status): ?>url.searchParams.set('status', '<?= esc($filter_status) ?>');<?php endif; ?>
    <?php if($search_barangay_id): ?>url.searchParams.set('search_barangay_id', '<?= esc($search_barangay_id) ?>');<?php endif; ?>
    <?php if($chart_date_from): ?>url.searchParams.set('chart_date_from', '<?= esc($chart_date_from) ?>');<?php endif; ?>
    <?php if($chart_date_to): ?>url.searchParams.set('chart_date_to', '<?= esc($chart_date_to) ?>');<?php endif; ?>
    window.location = url.toString();
  };
})();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

