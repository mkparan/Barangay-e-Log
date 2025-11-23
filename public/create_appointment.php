<?php
$page_title = "Create Appointment - Barangay e-Log";
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

$services = [
  'Barangay Clearance','Certificate of Residency','Certificate of Indigency','Purok Clearance','Barangay Clearance Recommendation',
  'Certificate of No Issuance','Barangay Business Permit','Complaint Blotter','Settlement/Mediation Certification','Cedula','Certificate of Tribal Membership'
];

$service_requirements = [
    'Barangay Clearance' => [
        'Valid government-issued ID',
        'Proof of residency (utility bill, lease, or barangay certificate)',
        'Community Tax Certificate (Cedula)'
    ],
    'Certificate of Residency' => [
        'Valid ID showing current address',
        'Barangay ID or previous certificate',
        'Recent proof of billing'
    ],
    'Certificate of Indigency' => [
        'Valid ID',
        'Barangay ID (if available)',
        'Personal letter stating purpose of request'
    ],
    'Purok Clearance' => [
        'Valid ID',
        'Proof of residence within the purok',
        'Endorsement from purok leader'
    ],
    'Barangay Clearance Recommendation' => [
        'Valid ID',
        'Proof of residency',
        'Letter requesting recommendation'
    ],
    'Certificate of No Issuance' => [
        'Valid ID',
        'Residence certificate or barangay ID',
        'Reason for requesting the certificate'
    ],
    'Barangay Business Permit' => [
        'DTI / SEC registration (if applicable)',
        'Lease contract or tax declaration of business address',
        'Community Tax Certificate (Cedula)'
    ],
    'Complaint Blotter' => [
        'Valid ID of complainant',
        'Detailed written statement of incident',
        'Any supporting evidence or witnesses'
    ],
    'Settlement/Mediation Certification' => [
        'Valid IDs of parties involved',
        'Written summary of dispute',
        'Desired resolution statement'
    ],
    'Cedula' => [
        'Valid ID',
        'Amount due for CTC (cash payment)',
        'Proof of income (if applicable)'
    ],
    'Certificate of Tribal Membership' => [
        'Valid ID',
        'Certification from tribal chieftain/elder',
        'Two community witnesses (if required)'
    ]
];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service_type'] ?? '';
    $preferred_date = $_POST['preferred_date'] ?? null;
    $preferred_start = $_POST['preferred_start'] ?? null;
    $preferred_end = $_POST['preferred_end'] ?? null;
    $details = $_POST['details'] ?? '';

    if (!$service || !$preferred_date) $errors[] = "Service and preferred date are required.";
    else {
        $stmt2 = $db->prepare("SELECT dr.official_id FROM duty_roster dr WHERE dr.duty_date = ? AND dr.start_time <= ? AND dr.end_time >= ? LIMIT 1");
        $start = $preferred_start ?: '00:00:00';
        $end = $preferred_end ?: '23:59:59';
        $stmt2->bind_param('sss', $preferred_date, $start, $end);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $official_id = null;
        if ($row = $res2->fetch_assoc()) $official_id = $row['official_id'];

        $qStmt = $db->prepare("SELECT COALESCE(MAX(queue_number),0)+1 AS qnum FROM appointments WHERE preferred_date = ?");
        $qStmt->bind_param('s', $preferred_date);
        $qStmt->execute();
        $qnum = $qStmt->get_result()->fetch_assoc()['qnum'] ?? 1;

        $ins = $db->prepare("INSERT INTO appointments (citizen_id, official_id, service_type, details, preferred_date, preferred_start, preferred_end, queue_number) VALUES (?,?,?,?,?,?,?,?)");
        $ins->bind_param('iisssssi', $citizen['citizen_id'], $official_id, $service, $details, $preferred_date, $preferred_start, $preferred_end, $qnum);
        if ($ins->execute()) {
            audit_log($citizen['cin'], null, 'appointment_create', 'appointments', $ins->insert_id);
            header('Location: create_appointment.php?created=1');
            exit;
        } else {
            $errors[] = "DB Error: " . $ins->error;
        }
    }
}

$stmt = $db->prepare("SELECT a.* , u.full_name AS official_name FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id WHERE a.citizen_id = ? ORDER BY a.created_at DESC");
$stmt->bind_param('i', $citizen['citizen_id']);
$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$currentAppointments = [];
foreach ($appointments as $appt) {
    $status = strtolower($appt['status'] ?? '');
    $isClosed = in_array($status, ['completed', 'declined', 'cancelled']);
    $isPast = !empty($appt['preferred_date']) && $appt['preferred_date'] < $today;
    if (!($isClosed || $isPast)) {
        $currentAppointments[] = $appt;
    }
}
?>

<?php if (!empty($_GET['created'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  Appointment created successfully!
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <h4 class="mb-3">Create Appointment</h4>
      <?php foreach($errors as $e): ?><div class="alert alert-danger"><?= esc($e) ?></div><?php endforeach; ?>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Service</label>
          <select name="service_type" class="form-select" required>
            <option value="">-- choose --</option>
            <?php foreach($services as $s): ?>
              <option value="<?= esc($s) ?>"><?= esc($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="requirementsPanel" class="alert alert-info d-none">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong class="mb-0">Bring these requirements</strong>
            <small class="text-muted" id="requirementsServiceLabel"></small>
          </div>
          <ul class="mb-2" id="requirementsList"></ul>
          <p class="mb-0 small text-muted">Have these ready when you arrive at the barangay office.</p>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label">Preferred date</label>
            <input type="date" name="preferred_date" class="form-control" required>
          </div>
          <div class="col-3">
            <label class="form-label">Start</label>
            <input type="time" name="preferred_start" class="form-control">
          </div>
          <div class="col-3">
            <label class="form-label">End</label>
            <input type="time" name="preferred_end" class="form-control">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Details (optional)</label>
          <textarea name="details" class="form-control" rows="3" placeholder="Any additional information to help us prepare"></textarea>
        </div>
        <div class="d-grid">
          <button class="btn btn-primary">Book Appointment</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="container-box h-100">
      <h4 class="mb-3">Current Appointments</h4>
      <?php if(empty($currentAppointments)): ?>
        <div class="alert alert-info">You have no pending or upcoming appointments.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead>
              <tr>
                <th>Service</th>
                <th>Date</th>
                <th>Queue</th>
                <th>Status</th>
                <th>Official</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($currentAppointments as $a): ?>
                <tr>
                  <td><?= esc($a['service_type']) ?></td>
                  <td><?= esc($a['preferred_date']) ?></td>
                  <td><?= esc($a['queue_number']) ?></td>
                  <td><span class="badge bg-info text-dark text-uppercase"><?= esc($a['status']) ?></span></td>
                  <td><?= esc($a['official_name'] ?? 'Unassigned') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var serviceSelect = document.querySelector('select[name="service_type"]');
  var panel = document.getElementById('requirementsPanel');
  var list = document.getElementById('requirementsList');
  var label = document.getElementById('requirementsServiceLabel');
  if (!serviceSelect || !panel) return;
  var requirements = <?= json_encode($service_requirements, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  function renderRequirements() {
    var value = serviceSelect.value;
    if (!value || !requirements[value]) {
      panel.classList.add('d-none');
      list.innerHTML = '';
      label.textContent = '';
      return;
    }

    list.innerHTML = '';
    requirements[value].forEach(function(item) {
      var li = document.createElement('li');
      li.textContent = item;
      list.appendChild(li);
    });
    label.textContent = value;
    panel.classList.remove('d-none');
  }

  serviceSelect.addEventListener('change', renderRequirements);
  renderRequirements();
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

