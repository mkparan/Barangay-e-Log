<?php
$page_title = "Admin Dashboard";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();
$user = $_SESSION['user'];

// Get filter parameters
$total_filter = $_GET['total_filter'] ?? 'all';
$total_month = $_GET['total_month'] ?? date('m');
$total_year = $_GET['total_year'] ?? date('Y');
$chart_filter = $_GET['chart_filter'] ?? '12months';
$pie_month = $_GET['pie_month'] ?? date('m');
$pie_year = $_GET['pie_year'] ?? date('Y');

// Quick stats - Total appointments with filter
if ($total_filter === 'month') {
    $totalStartDate = $total_year . '-' . $total_month . '-01';
    $totalEndDate = $total_year . '-' . $total_month . '-' . date('t', strtotime($totalStartDate));
    $totalStmt = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $totalStmt->bind_param('ss', $totalStartDate, $totalEndDate);
    $totalStmt->execute();
    $totalAppointments = (int)($totalStmt->get_result()->fetch_assoc()['c'] ?? 0);
} else {
    $totalAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments")->fetch_assoc()['c'] ?? 0);
}
$pendingAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$completedAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='completed'")->fetch_assoc()['c'] ?? 0);
$declinedAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='declined'")->fetch_assoc()['c'] ?? 0);
$cancelledAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='cancelled'")->fetch_assoc()['c'] ?? 0);

$today = date('Y-m-d');
$todayScheduledStmt = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE preferred_date = ?");
$todayScheduledStmt->bind_param('s', $today);
$todayScheduledStmt->execute();
$todayScheduled = (int)($todayScheduledStmt->get_result()->fetch_assoc()['c'] ?? 0);

$startOfMonth = date('Y-m-01');
$processedThisMonthStmt = $db->prepare("SELECT COUNT(*) AS c FROM appointments WHERE status='completed' AND updated_at >= ?");
$processedThisMonthStmt->bind_param('s', $startOfMonth);
$processedThisMonthStmt->execute();
$processedThisMonth = (int)($processedThisMonthStmt->get_result()->fetch_assoc()['c'] ?? 0);

// 1. Official Performance Metrics
$officialPerformanceStmt = $db->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.role,
        COUNT(a.appointment_id) as total_processed,
        AVG(TIMESTAMPDIFF(HOUR, a.created_at, a.updated_at)) as avg_processing_hours
    FROM users u
    LEFT JOIN appointments a ON u.user_id = a.processed_by AND a.status = 'completed'
    WHERE u.role != 'admin' AND u.is_active = 1
    GROUP BY u.user_id, u.full_name, u.role
    ORDER BY total_processed DESC
    LIMIT 5
");
$officialPerformance = $officialPerformanceStmt ? $officialPerformanceStmt->fetch_all(MYSQLI_ASSOC) : [];

// 4. Average Processing Time (overall)
$avgProcessingTimeStmt = $db->query("
    SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours,
        AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as avg_days
    FROM appointments 
    WHERE status = 'completed' AND updated_at IS NOT NULL
");
$avgProcessingTime = $avgProcessingTimeStmt ? $avgProcessingTimeStmt->fetch_assoc() : ['avg_hours' => 0, 'avg_days' => 0];
$avgHours = round($avgProcessingTime['avg_hours'] ?? 0, 1);
$avgDays = round($avgProcessingTime['avg_days'] ?? 0, 1);

// 5. Comparison Charts - Month-over-month and Year-over-year
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));
$currentYear = date('Y');
$lastYear = $currentYear - 1;

// Current month vs last month
$currentMonthCount = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'")->fetch_assoc()['c'] ?? 0);
$lastMonthCount = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'")->fetch_assoc()['c'] ?? 0);
$monthChange = $lastMonthCount > 0 ? round((($currentMonthCount - $lastMonthCount) / $lastMonthCount) * 100, 1) : 0;

// Current year vs last year
$currentYearCount = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(created_at) = $currentYear")->fetch_assoc()['c'] ?? 0);
$lastYearCount = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE YEAR(created_at) = $lastYear")->fetch_assoc()['c'] ?? 0);
$yearChange = $lastYearCount > 0 ? round((($currentYearCount - $lastYearCount) / $lastYearCount) * 100, 1) : 0;

// 9. Dashboard Widgets - Today's summary, upcoming appointments, alerts
$todayStart = date('Y-m-d 00:00:00');
$todayEnd = date('Y-m-d 23:59:59');

// Today's appointments
$todayAppointmentsStmt = $db->prepare("
    SELECT COUNT(*) AS c FROM appointments 
    WHERE DATE(created_at) = CURDATE()
");
$todayAppointmentsStmt->execute();
$todayAppointmentsCount = (int)($todayAppointmentsStmt->get_result()->fetch_assoc()['c'] ?? 0);

// Today's completed
$todayCompletedStmt = $db->prepare("
    SELECT COUNT(*) AS c FROM appointments 
    WHERE status = 'completed' AND DATE(updated_at) = CURDATE()
");
$todayCompletedStmt->execute();
$todayCompletedCount = (int)($todayCompletedStmt->get_result()->fetch_assoc()['c'] ?? 0);

// Upcoming appointments (next 7 days)
$nextWeek = date('Y-m-d', strtotime('+7 days'));
$upcomingStmt = $db->prepare("
    SELECT COUNT(*) AS c FROM appointments 
    WHERE preferred_date >= CURDATE() AND preferred_date <= ? AND status IN ('pending', 'approved')
");
$upcomingStmt->bind_param('s', $nextWeek);
$upcomingStmt->execute();
$upcomingCount = (int)($upcomingStmt->get_result()->fetch_assoc()['c'] ?? 0);

// Upcoming appointments details
$upcomingDetailsStmt = $db->prepare("
    SELECT a.*, c.first_name, c.last_name, c.profile_picture
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE a.preferred_date >= CURDATE() AND a.preferred_date <= ? AND a.status IN ('pending', 'approved')
    ORDER BY a.preferred_date ASC, a.queue_number ASC
    LIMIT 5
");
$upcomingDetailsStmt->bind_param('s', $nextWeek);
$upcomingDetailsStmt->execute();
$upcomingAppointments = $upcomingDetailsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Alerts - High pending count, overdue appointments
$highPendingThreshold = 20;
$alerts = [];

if ($pendingAppointments > $highPendingThreshold) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'exclamation-triangle',
        'message' => "High pending count: {$pendingAppointments} appointments need attention",
        'action' => 'appointments.php?filter=pending'
    ];
}

// Check for appointments pending for more than 3 days
$overdueStmt = $db->query("
    SELECT COUNT(*) AS c FROM appointments 
    WHERE status = 'pending' AND DATEDIFF(CURDATE(), DATE(created_at)) > 3
");
$overdueCount = (int)($overdueStmt->fetch_assoc()['c'] ?? 0);
if ($overdueCount > 0) {
    $alerts[] = [
        'type' => 'danger',
        'icon' => 'clock-history',
        'message' => "{$overdueCount} appointment(s) pending for more than 3 days",
        'action' => 'appointments.php?filter=pending'
    ];
}

// Check for appointments scheduled today
if ($todayScheduled > 0) {
    $todayDate = date('Y-m-d');
    $alerts[] = [
        'type' => 'info',
        'icon' => 'calendar-check',
        'message' => "{$todayScheduled} appointment(s) scheduled for today",
        'action' => 'appointments.php?date_from=' . urlencode($todayDate) . '&date_to=' . urlencode($todayDate)
    ];
}

// Monthly chart data with filter
$monthKeys = [];
$chartLabels = [];
$monthsToShow = 12; // default

if ($chart_filter === '6months') {
    $monthsToShow = 6;
} elseif ($chart_filter === '3months') {
    $monthsToShow = 3;
} elseif ($chart_filter === '12months') {
    $monthsToShow = 12;
}

for ($i = $monthsToShow - 1; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-{$i} months"));
    $monthKeys[] = $key;
    $chartLabels[] = date('M Y', strtotime($key . '-01'));
}
$appointmentsMap = array_fill_keys($monthKeys, 0);
$processedMap = array_fill_keys($monthKeys, 0);
$chartStart = $monthKeys[0] . '-01';

$monthlyApptStmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total FROM appointments WHERE created_at >= ? GROUP BY ym");
$monthlyApptStmt->bind_param('s', $chartStart);
$monthlyApptStmt->execute();
$monthlyApptResult = $monthlyApptStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($monthlyApptResult as $row) {
    if (isset($appointmentsMap[$row['ym']])) {
        $appointmentsMap[$row['ym']] = (int)$row['total'];
    }
}

$monthlyProcessedStmt = $db->prepare("SELECT DATE_FORMAT(updated_at, '%Y-%m') AS ym, COUNT(*) AS total FROM appointments WHERE status='completed' AND updated_at >= ? GROUP BY ym");
$monthlyProcessedStmt->bind_param('s', $chartStart);
$monthlyProcessedStmt->execute();
$monthlyProcessedResult = $monthlyProcessedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($monthlyProcessedResult as $row) {
    if (isset($processedMap[$row['ym']])) {
        $processedMap[$row['ym']] = (int)$row['total'];
    }
}

$chartPayload = [
    'labels' => $chartLabels,
    'appointments' => array_values($appointmentsMap),
    'processed' => array_values($processedMap),
];

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

// Top services
$serviceStmt = $db->query("SELECT service_type, COUNT(*) AS total FROM appointments GROUP BY service_type ORDER BY total DESC LIMIT 5");
$topServices = $serviceStmt ? $serviceStmt->fetch_all(MYSQLI_ASSOC) : [];

// Recent updates
$recentStmt = $db->query("
    SELECT a.appointment_id, a.service_type, a.status, a.updated_at, c.first_name, c.last_name
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    ORDER BY a.updated_at DESC
    LIMIT 5
");
$recentActivity = $recentStmt ? $recentStmt->fetch_all(MYSQLI_ASSOC) : [];

// Pending queue with pagination
$pendingPage = max(1, (int)($_GET['pending_page'] ?? 1));
$pendingPerPage = 10;
$pendingOffset = ($pendingPage - 1) * $pendingPerPage;

// Get total count
$pendingCountStmt = $db->query("SELECT COUNT(*) AS total FROM appointments WHERE status = 'pending'");
$pendingTotalCount = (int)($pendingCountStmt->fetch_assoc()['total'] ?? 0);
$pendingTotalPages = max(1, ceil($pendingTotalCount / $pendingPerPage));

$pendingStmt = $db->prepare("
    SELECT a.*, c.first_name, c.last_name, c.profile_picture
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at ASC
    LIMIT ? OFFSET ?
");
if ($pendingStmt) {
    $pendingStmt->bind_param('ii', $pendingPerPage, $pendingOffset);
    $pendingStmt->execute();
    $pendingAppointmentsRows = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $pendingAppointmentsRows = [];
}
?>

<div class="container-box mb-4">
  <div>
    <h4 class="mb-1">Admin Dashboard</h4>
    <p class="text-muted mb-0">Monitor citizen appointments, document releases, and upcoming schedules at a glance.</p>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-start mb-2">
        <p class="text-muted text-uppercase small mb-0">Total appointments</p>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-funnel"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="?total_filter=all<?= $chart_filter ? '&chart_filter='.$chart_filter : '' ?><?= $pie_month && $pie_year ? '&pie_month='.$pie_month.'&pie_year='.$pie_year : '' ?>">All Time</a></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <form method="get" class="px-3 py-2">
                <input type="hidden" name="total_filter" value="month">
                <?php if($chart_filter): ?><input type="hidden" name="chart_filter" value="<?= esc($chart_filter) ?>"><?php endif; ?>
                <?php if($pie_month && $pie_year): ?>
                  <input type="hidden" name="pie_month" value="<?= esc($pie_month) ?>">
                  <input type="hidden" name="pie_year" value="<?= esc($pie_year) ?>">
                <?php endif; ?>
                <div class="mb-2">
                  <label class="form-label small">Month</label>
                  <select name="total_month" class="form-select form-select-sm">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                      <option value="<?= sprintf('%02d', $m) ?>" <?= $total_month == sprintf('%02d', $m) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                      </option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="mb-2">
                  <label class="form-label small">Year</label>
                  <select name="total_year" class="form-select form-select-sm">
                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                      <option value="<?= $y ?>" <?= $total_year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
      <h3 class="mb-0"><?= number_format($totalAppointments) ?></h3>
      <small class="text-muted">
        <?php if($total_filter === 'month'): ?>
          <?= date('F Y', strtotime($total_year . '-' . $total_month . '-01')) ?>
        <?php else: ?>
          All time
        <?php endif; ?>
      </small>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="container-box h-100">
      <p class="text-muted text-uppercase small mb-1">Pending approvals</p>
      <h3 class="mb-0"><?= number_format($pendingAppointments) ?></h3>
      <small class="text-muted"><?= $todayScheduled ?> scheduled today</small>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="container-box h-100">
      <p class="text-muted text-uppercase small mb-1">Documents released</p>
      <h3 class="mb-0"><?= number_format($completedAppointments) ?></h3>
      <small class="text-muted"><?= $processedThisMonth ?> this month</small>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="container-box h-100">
      <p class="text-muted text-uppercase small mb-1">Declined / cancelled</p>
      <h3 class="mb-0"><?= number_format($declinedAppointments + $cancelledAppointments) ?></h3>
      <small class="text-muted">Needs follow-up</small>
    </div>
  </div>
</div>

<!-- 9. Dashboard Widgets: Today's Summary & Alerts -->
<div class="row g-4 mb-4">
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-primary">
      <p class="text-muted text-uppercase small mb-1">Today's Activity</p>
      <h4 class="mb-0"><?= number_format($todayAppointmentsCount) ?></h4>
      <small class="text-muted"><?= $todayCompletedCount ?> completed today</small>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-info">
      <p class="text-muted text-uppercase small mb-1">Upcoming (7 days)</p>
      <h4 class="mb-0"><?= number_format($upcomingCount) ?></h4>
      <small class="text-muted">Appointments scheduled</small>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-success">
      <p class="text-muted text-uppercase small mb-1">Avg Processing Time</p>
      <h4 class="mb-0">
        <?php if ($avgDays >= 1): ?>
          <?= number_format($avgDays, 1) ?> day(s)
        <?php else: ?>
          <?= number_format($avgHours, 1) ?> hour(s)
        <?php endif; ?>
      </h4>
      <small class="text-muted">From creation to completion</small>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-warning">
      <p class="text-muted text-uppercase small mb-1">Month-over-Month</p>
      <h4 class="mb-0 <?= $monthChange >= 0 ? 'text-success' : 'text-danger' ?>">
        <?= $monthChange >= 0 ? '+' : '' ?><?= number_format($monthChange, 1) ?>%
      </h4>
      <small class="text-muted">vs last month</small>
    </div>
  </div>
</div>

<?php if (!empty($alerts)): ?>
<div class="row g-4 mb-4">
  <div class="col-12">
<div class="container-box">
      <h6 class="mb-3"><i class="bi bi-bell me-2"></i>Alerts & Notifications</h6>
      <div class="row g-2">
        <?php foreach($alerts as $alert): ?>
          <div class="col-md-6 col-lg-4">
            <div class="alert alert-<?= esc($alert['type']) ?> alert-dismissible fade show mb-0" role="alert">
              <i class="bi bi-<?= esc($alert['icon']) ?> me-2"></i>
              <small><?= esc($alert['message']) ?></small>
              <?php if (!empty($alert['action'])): ?>
                <a href="<?= esc($alert['action']) ?>" class="alert-link ms-2">View</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h5 class="mb-1">Monthly appointment activity</h5>
          <small class="text-muted">Includes appointments created vs. documents processed</small>
        </div>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-funnel"></i> Filter
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="?chart_filter=3months<?= $total_filter ? '&total_filter='.$total_filter : '' ?><?= $total_filter === 'month' ? '&total_month='.$total_month.'&total_year='.$total_year : '' ?><?= $pie_month && $pie_year ? '&pie_month='.$pie_month.'&pie_year='.$pie_year : '' ?>">Last 3 Months</a></li>
            <li><a class="dropdown-item" href="?chart_filter=6months<?= $total_filter ? '&total_filter='.$total_filter : '' ?><?= $total_filter === 'month' ? '&total_month='.$total_month.'&total_year='.$total_year : '' ?><?= $pie_month && $pie_year ? '&pie_month='.$pie_month.'&pie_year='.$pie_year : '' ?>">Last 6 Months</a></li>
            <li><a class="dropdown-item" href="?chart_filter=12months<?= $total_filter ? '&total_filter='.$total_filter : '' ?><?= $total_filter === 'month' ? '&total_month='.$total_month.'&total_year='.$total_year : '' ?><?= $pie_month && $pie_year ? '&pie_month='.$pie_month.'&pie_year='.$pie_year : '' ?>">Last 12 Months</a></li>
          </ul>
        </div>
      </div>
      <div class="ratio ratio-16x9">
        <canvas id="appointmentActivityChart" class="w-100 h-100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="container-box h-100 d-flex flex-column">
  <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Services by Month</h5>
      </div>
      
      <div class="mb-3">
        <div class="row g-2 align-items-end">
          <div class="col-5">
            <label class="form-label small text-muted mb-1">Month</label>
            <select id="pieMonth" class="form-select form-select-sm">
              <?php for($m = 1; $m <= 12; $m++): ?>
                <option value="<?= sprintf('%02d', $m) ?>" <?= $pie_month == sprintf('%02d', $m) ? 'selected' : '' ?>>
                  <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-5">
            <label class="form-label small text-muted mb-1">Year</label>
            <select id="pieYear" class="form-select form-select-sm">
              <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                <option value="<?= $y ?>" <?= $pie_year == $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="col-2">
            <button type="button" class="btn btn-sm btn-primary w-100" onclick="updatePieChart()" title="Update Chart">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </div>
        </div>
      </div>
      
      <?php if(empty($pieData)): ?>
        <div class="alert alert-info mb-0 flex-grow-1 d-flex align-items-center justify-content-center">
          <div class="text-center">
            <i class="bi bi-pie-chart fs-3 text-muted mb-2 d-block"></i>
            <small>No appointment data for this period.</small>
          </div>
        </div>
      <?php else: ?>
        <div class="flex-grow-1 d-flex flex-column">
          <div class="ratio ratio-1x1 mb-2">
            <canvas id="pieChart"></canvas>
          </div>
        </div>
      <?php endif; ?>
    </div>
    </div>
  </div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Appointment History</h5>
        <a href="history.php" class="btn btn-link btn-sm">View All</a>
      </div>
      <?php
      // Get recent appointment history (completed, declined, or past appointments)
      $today = date('Y-m-d');
      $historyStmt = $db->prepare("
        SELECT 
          a.*,
          u.full_name AS official_name,
          c.first_name,
          c.last_name,
          c.profile_picture
        FROM appointments a
        LEFT JOIN users u ON a.official_id = u.user_id
        JOIN citizens c ON a.citizen_id = c.citizen_id
        WHERE (a.status IN ('completed', 'declined', 'cancelled') OR a.preferred_date < ?)
        ORDER BY a.created_at DESC
        LIMIT 10
      ");
      $historyStmt->bind_param('s', $today);
      $historyStmt->execute();
      $appointmentHistory = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);
      ?>
      <?php if(empty($appointmentHistory)): ?>
        <div class="alert alert-light border mb-0">No appointment history yet.</div>
      <?php else: ?>
        <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
          <?php foreach($appointmentHistory as $item): 
            $citizenPic = !empty($item['profile_picture']) ? $item['profile_picture'] : null;
            $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="18" fill="#6c757d">' . strtoupper(substr($item['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
            
            $status = strtolower($item['status'] ?? '');
            $badgeClass = 'bg-secondary';
            if ($status === 'completed') $badgeClass = 'bg-success';
            elseif ($status === 'declined') $badgeClass = 'bg-danger';
            elseif ($status === 'cancelled') $badgeClass = 'bg-warning text-dark';
            elseif ($status === 'approved') $badgeClass = 'bg-info';
            elseif ($status === 'pending') $badgeClass = 'bg-warning';
          ?>
            <div class="list-group-item px-0">
              <div class="d-flex align-items-start gap-2">
                <img src="<?= $citizenPic ? esc('/elog_barangay/public/' . $citizenPic) : $defaultPic ?>" 
                     alt="Profile" 
                     class="rounded-circle mt-1" 
                     style="width: 32px; height: 32px; object-fit: cover; flex-shrink: 0;">
                <div class="flex-grow-1">
                  <div class="d-flex justify-content-between align-items-start mb-1">
                    <div>
                      <strong><?= esc($item['service_type']) ?></strong>
                      <div class="text-muted small"><?= esc($item['first_name'].' '.$item['last_name']) ?></div>
                    </div>
                    <span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($item['status']) ?></span>
                  </div>
                  <div class="text-muted small">
                    <div>Date: <?= esc($item['preferred_date']) ?> · Queue #<?= esc($item['queue_number']) ?></div>
                    <?php if ($item['official_name']): ?>
                      <div>Official: <?= esc($item['official_name']) ?></div>
                    <?php endif; ?>
                  </div>
                  <small class="text-muted">Created <?= esc(date('M d, Y h:i A', strtotime($item['created_at']))) ?></small>
                </div>
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
        <h5 class="mb-0">Pending queue</h5>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-warning text-dark"><?= number_format($pendingTotalCount) ?> pending</span>
          <a href="appointments.php?filter=pending" class="btn btn-link btn-sm">View all</a>
        </div>
      </div>
      <?php if(empty($pendingAppointmentsRows)): ?>
        <div class="alert alert-info mb-0">No pending appointments.</div>
  <?php else: ?>
        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
          <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Citizen</th>
                <th>Service</th>
                <th>Date</th>
                <th>Queue</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
        <tbody>
              <?php foreach($pendingAppointmentsRows as $p): 
                $citizenPic = !empty($p['profile_picture']) ? $p['profile_picture'] : null;
                $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="18" fill="#6c757d">' . strtoupper(substr($p['first_name'] ?? 'U', 0, 1)) . '</text></svg>');
              ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <img src="<?= $citizenPic ? esc('/elog_barangay/public/' . $citizenPic) : $defaultPic ?>" 
                           alt="Profile" 
                           class="rounded-circle me-2" 
                           style="width: 32px; height: 32px; object-fit: cover;">
                      <span><?= esc($p['first_name'].' '.$p['last_name']) ?></span>
                    </div>
                  </td>
                  <td><small><?= esc($p['service_type']) ?></small></td>
                  <td><small><?= esc($p['preferred_date']) ?></small></td>
                  <td><span class="badge bg-secondary"><?= esc($p['queue_number']) ?></span></td>
                  <td class="text-end">
                    <div class="d-flex flex-wrap gap-1 justify-content-end">
                      <a href="appointments.php?action=approve&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-success" title="Approve">Approve</a>
                      <a href="appointments.php?action=decline&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-danger" title="Decline">Decline</a>
                      <a href="appointments.php?action=reschedule&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-warning" title="Reschedule">Resched</a>
                      <a href="appointments.php?action=complete&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-outline-success" title="Mark as Released">Mark Released</a>
                    </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
        <?php if($pendingTotalPages > 1): ?>
          <nav aria-label="Pending queue pagination" class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
              <?php if($pendingPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?pending_page=<?= $pendingPage - 1 ?><?= $total_filter ? '&total_filter=' . urlencode($total_filter) : '' ?><?= $total_filter === 'month' ? '&total_month=' . urlencode($total_month) . '&total_year=' . urlencode($total_year) : '' ?><?= $chart_filter ? '&chart_filter=' . urlencode($chart_filter) : '' ?><?= $pie_month ? '&pie_month=' . urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year=' . urlencode($pie_year) : '' ?>">Previous</a>
                </li>
              <?php else: ?>
                <li class="page-item disabled">
                  <span class="page-link">Previous</span>
                </li>
              <?php endif; ?>
              
              <?php
              $startPage = max(1, $pendingPage - 2);
              $endPage = min($pendingTotalPages, $pendingPage + 2);
              
              if ($startPage > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="?pending_page=1<?= $total_filter ? '&total_filter=' . urlencode($total_filter) : '' ?><?= $total_filter === 'month' ? '&total_month=' . urlencode($total_month) . '&total_year=' . urlencode($total_year) : '' ?><?= $chart_filter ? '&chart_filter=' . urlencode($chart_filter) : '' ?><?= $pie_month ? '&pie_month=' . urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year=' . urlencode($pie_year) : '' ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                  <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
              <?php endif; ?>
              
              <?php for($i = $startPage; $i <= $endPage; $i++): ?>
                <li class="page-item <?= $i == $pendingPage ? 'active' : '' ?>">
                  <a class="page-link" href="?pending_page=<?= $i ?><?= $total_filter ? '&total_filter=' . urlencode($total_filter) : '' ?><?= $total_filter === 'month' ? '&total_month=' . urlencode($total_month) . '&total_year=' . urlencode($total_year) : '' ?><?= $chart_filter ? '&chart_filter=' . urlencode($chart_filter) : '' ?><?= $pie_month ? '&pie_month=' . urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year=' . urlencode($pie_year) : '' ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              
              <?php if ($endPage < $pendingTotalPages): ?>
                <?php if ($endPage < $pendingTotalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                  <a class="page-link" href="?pending_page=<?= $pendingTotalPages ?><?= $total_filter ? '&total_filter=' . urlencode($total_filter) : '' ?><?= $total_filter === 'month' ? '&total_month=' . urlencode($total_month) . '&total_year=' . urlencode($total_year) : '' ?><?= $chart_filter ? '&chart_filter=' . urlencode($chart_filter) : '' ?><?= $pie_month ? '&pie_month=' . urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year=' . urlencode($pie_year) : '' ?>"><?= $pendingTotalPages ?></a>
                </li>
              <?php endif; ?>
              
              <?php if($pendingPage < $pendingTotalPages): ?>
                <li class="page-item">
                  <a class="page-link" href="?pending_page=<?= $pendingPage + 1 ?><?= $total_filter ? '&total_filter=' . urlencode($total_filter) : '' ?><?= $total_filter === 'month' ? '&total_month=' . urlencode($total_month) . '&total_year=' . urlencode($total_year) : '' ?><?= $chart_filter ? '&chart_filter=' . urlencode($chart_filter) : '' ?><?= $pie_month ? '&pie_month=' . urlencode($pie_month) : '' ?><?= $pie_year ? '&pie_year=' . urlencode($pie_year) : '' ?>">Next</a>
                </li>
              <?php else: ?>
                <li class="page-item disabled">
                  <span class="page-link">Next</span>
                </li>
              <?php endif; ?>
            </ul>
            <div class="text-center mt-2">
              <small class="text-muted">Showing <?= $pendingOffset + 1 ?>-<?= min($pendingOffset + $pendingPerPage, $pendingTotalCount) ?> of <?= number_format($pendingTotalCount) ?> pending</small>
            </div>
          </nav>
        <?php endif; ?>
  <?php endif; ?>
</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
/* 10. Mobile-Responsive Analytics */
@media (max-width: 768px) {
  .container-box {
    padding: 15px;
  }
  .ratio-16x9 {
    --bs-aspect-ratio: 75%;
  }
  .ratio-1x1 {
    --bs-aspect-ratio: 100%;
  }
  h4, h5 {
    font-size: 1.1rem;
  }
  .list-group-item {
    font-size: 0.9rem;
  }
  .table {
    font-size: 0.85rem;
  }
  .btn-sm {
    padding: 0.2rem 0.4rem;
    font-size: 0.75rem;
  }
}
</style>
<script>
(function() {
  const chartPayload = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const ctx = document.getElementById('appointmentActivityChart');
  if (ctx) {
    new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartPayload.labels,
      datasets: [
        {
          label: 'Appointments created',
          data: chartPayload.appointments,
          borderColor: '#0d6efd',
          backgroundColor: 'rgba(13,110,253,0.2)',
          tension: 0.3,
          fill: true,
          borderWidth: 2
        },
        {
          label: 'Documents processed',
          data: chartPayload.processed,
          borderColor: '#198754',
          backgroundColor: 'rgba(25,135,84,0.2)',
          tension: 0.3,
          fill: true,
          borderWidth: 2
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0
          }
        }
      },
      plugins: {
        legend: {
          display: true,
          position: 'bottom'
        }
      }
    }
    });
  }
  
  // Pie Chart
  const pieCtx = document.getElementById('pieChart');
  if (pieCtx) {
    const pieData = {
      labels: <?= json_encode($pieLabels) ?>,
      datasets: [{
        data: <?= json_encode($pieValues) ?>,
        backgroundColor: <?= json_encode(array_slice($pieColors, 0, count($pieLabels))) ?>
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
            position: 'bottom'
          }
        }
      }
    });
  }
  
  window.updatePieChart = function() {
    const month = document.getElementById('pieMonth').value;
    const year = document.getElementById('pieYear').value;
    const url = new URL(window.location);
    url.searchParams.set('pie_month', month);
    url.searchParams.set('pie_year', year);
    <?php if($total_filter): ?>url.searchParams.set('total_filter', '<?= esc($total_filter) ?>');<?php endif; ?>
    <?php if($total_filter === 'month'): ?>
      url.searchParams.set('total_month', '<?= esc($total_month) ?>');
      url.searchParams.set('total_year', '<?= esc($total_year) ?>');
    <?php endif; ?>
    <?php if($chart_filter): ?>url.searchParams.set('chart_filter', '<?= esc($chart_filter) ?>');<?php endif; ?>
    window.location = url.toString();
  };
})();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
