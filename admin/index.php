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

// Quick stats
$totalAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments")->fetch_assoc()['c'] ?? 0);
$pendingAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$completedAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='completed'")->fetch_assoc()['c'] ?? 0);
$declinedAppointments = (int)($db->query("SELECT COUNT(*) AS c FROM appointments WHERE status='declined'")->fetch_assoc()['c'] ?? 0);

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

// Monthly chart data (last 12 months)
$monthKeys = [];
$chartLabels = [];
for ($i = 11; $i >= 0; $i--) {
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

// Pending queue
$pendingStmt = $db->prepare("
    SELECT a.*, c.first_name, c.last_name, c.profile_picture
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE a.status = 'pending'
    ORDER BY a.created_at ASC
    LIMIT 12
");
$pendingStmt->execute();
$pendingAppointmentsRows = $pendingStmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box mb-4">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
    <div>
      <h4 class="mb-1">Admin Dashboard</h4>
      <p class="text-muted mb-0">Monitor citizen appointments, document releases, and upcoming schedules at a glance.</p>
    </div>
    <div class="text-md-end">
      <div class="small text-muted mb-1">Signed in as <?= esc($user['full_name']) ?></div>
      <a href="logout.php" class="btn btn-outline-secondary btn-sm">Logout</a>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mt-4">
    <a href="announcements.php" class="btn btn-primary btn-sm">Manage Appointments</a>
    <a href="appointments.php" class="btn btn-outline-primary btn-sm">Citizen Announcements</a>
    <a href="duty_roster.php" class="btn btn-outline-primary btn-sm">Duty Roster</a>
    <a href="presence.php" class="btn btn-outline-primary btn-sm">Presence</a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="container-box h-100">
      <p class="text-muted text-uppercase small mb-1">Total appointments</p>
      <h3 class="mb-0"><?= number_format($totalAppointments) ?></h3>
      <small class="text-muted">All time</small>
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
      <h3 class="mb-0"><?= number_format($declinedAppointments) ?></h3>
      <small class="text-muted">Needs follow-up</small>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
          <h5 class="mb-1">Monthly appointment activity</h5>
          <small class="text-muted">Includes appointments created vs. documents processed</small>
        </div>
      </div>
      <div class="ratio ratio-16x9">
        <canvas id="appointmentActivityChart" class="w-100 h-100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="container-box h-100">
      <h5 class="mb-3">Top requested services</h5>
      <?php if(empty($topServices)): ?>
        <div class="alert alert-info mb-0">No appointment data yet.</div>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach($topServices as $service): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
              <span><?= esc($service['service_type']) ?></span>
              <span class="badge bg-primary rounded-pill"><?= number_format($service['total']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Recent updates</h5>
        <small class="text-muted">Latest status changes</small>
      </div>
      <?php if(empty($recentActivity)): ?>
        <div class="alert alert-light border mb-0">No recent activity recorded.</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
          <?php foreach($recentActivity as $item): ?>
            <div class="list-group-item px-0">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <strong>#<?= esc($item['appointment_id']) ?> · <?= esc($item['service_type']) ?></strong>
                  <div class="text-muted small"><?= esc($item['first_name'].' '.$item['last_name']) ?></div>
                </div>
                <span class="badge bg-secondary text-uppercase"><?= esc($item['status']) ?></span>
              </div>
              <small class="text-muted">Updated <?= esc(date('M d, Y h:i A', strtotime($item['updated_at']))) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h5 class="mb-0">Pending queue</h5>
        <a href="announcements.php" class="btn btn-link btn-sm">View all</a>
      </div>
      <?php if(empty($pendingAppointmentsRows)): ?>
        <div class="alert alert-info mb-0">No pending appointments.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
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
                  <td><?= esc($p['service_type']) ?></td>
                  <td><?= esc($p['preferred_date']) ?></td>
                  <td><?= esc($p['queue_number']) ?></td>
                  <td class="text-end">
                    <a href="announcements.php?action=approve&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-outline-success me-1">Approve</a>
                    <a href="announcements.php?action=decline&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-outline-danger me-1">Decline</a>
                    <a href="announcements.php?action=reschedule&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-outline-warning me-1">Resched</a>
                    <a href="announcements.php?action=complete&id=<?= esc($p['appointment_id']) ?>" class="btn btn-sm btn-success">Mark Released</a>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(function() {
  const chartPayload = <?= json_encode($chartPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const ctx = document.getElementById('appointmentActivityChart');
  if (!ctx) return;
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
})();
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
