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

// Handle cancel appointment action
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
                header('Location: create_appointment.php?cancelled=1');
                exit;
            }
        }
    }
    header('Location: create_appointment.php');
    exit;
}

// Handle POST request FIRST, before any HTML output
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service = $_POST['service_type'] ?? '';
    $preferred_date = $_POST['preferred_date'] ?? null;
    $time_slot = $_POST['time_slot'] ?? null;
    $details = $_POST['details'] ?? '';

    if (!$service || !$preferred_date || !$time_slot) {
        $_SESSION['appointment_errors'] = ["Service, date, and time slot (morning/afternoon) are required."];
        header('Location: create_appointment.php');
        exit;
    }
    
    // Validate that preferred_date is not before today
    $today = date('Y-m-d');
    if ($preferred_date < $today) {
        $_SESSION['appointment_errors'] = ["Appointment date cannot be before today."];
        header('Location: create_appointment.php');
        exit;
    }
    
    // Check if date is available
    $availStmt = $db->prepare("SELECT * FROM appointment_availability WHERE available_date = ? AND is_available = 1");
    $availStmt->bind_param('s', $preferred_date);
    $availStmt->execute();
    $availability = $availStmt->get_result()->fetch_assoc();
    
    if (!$availability) {
        $_SESSION['appointment_errors'] = ["Selected date is not available for appointments."];
        header('Location: create_appointment.php');
        exit;
    }
    
    // Check if selected time slot is available
    if ($time_slot === 'morning' && !$availability['morning_available']) {
        $_SESSION['appointment_errors'] = ["Morning slot is not available for this date."];
        header('Location: create_appointment.php');
        exit;
    }
    
    if ($time_slot === 'afternoon' && !$availability['afternoon_available']) {
        $_SESSION['appointment_errors'] = ["Afternoon slot is not available for this date."];
        header('Location: create_appointment.php');
        exit;
    }
    
    // Count existing appointments for this date and time slot
    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM appointments WHERE preferred_date = ? AND time_slot = ? AND status NOT IN ('declined', 'cancelled')");
    $countStmt->bind_param('ss', $preferred_date, $time_slot);
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $currentCount = (int)($countResult['count'] ?? 0);
    
    // Check if slots are full
    $maxSlots = $time_slot === 'morning' ? $availability['morning_slots'] : $availability['afternoon_slots'];
    if ($currentCount >= $maxSlots) {
        $_SESSION['appointment_errors'] = ["All slots for " . ucfirst($time_slot) . " on this date are full. Please choose another time or date."];
        header('Location: create_appointment.php');
        exit;
    }
    
    // Set time based on slot
    $preferred_start = $time_slot === 'morning' ? '08:00:00' : '13:00:00';
    $preferred_end = $time_slot === 'morning' ? '12:00:00' : '17:00:00';
    
    // Get queue number for this date and time slot
    $qStmt = $db->prepare("SELECT COALESCE(MAX(queue_number),0)+1 AS qnum FROM appointments WHERE preferred_date = ? AND time_slot = ?");
    $qStmt->bind_param('ss', $preferred_date, $time_slot);
    $qStmt->execute();
    $qnum = $qStmt->get_result()->fetch_assoc()['qnum'] ?? 1;

    $ins = $db->prepare("INSERT INTO appointments (citizen_id, service_type, details, preferred_date, preferred_start, preferred_end, time_slot, queue_number) VALUES (?,?,?,?,?,?,?,?)");
    $ins->bind_param('issssssi', $citizen['citizen_id'], $service, $details, $preferred_date, $preferred_start, $preferred_end, $time_slot, $qnum);
    if ($ins->execute()) {
        audit_log($citizen['cin'], null, 'appointment_create', 'appointments', $ins->insert_id);
        header('Location: create_appointment.php?created=1');
        exit;
    } else {
        $_SESSION['appointment_errors'] = ["DB Error: " . $ins->error];
        header('Location: create_appointment.php');
        exit;
    }
}

// Get errors from session if any
if (isset($_SESSION['appointment_errors'])) {
    $errors = $_SESSION['appointment_errors'];
    unset($_SESSION['appointment_errors']);
}

$page_title = "Create Appointment - Barangay e-Log";
require_once __DIR__ . '/../inc/header.php';

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

// Get available dates (all future dates, not just 30 days)
$availableDatesStmt = $db->query("
    SELECT available_date, morning_available, afternoon_available, morning_slots, afternoon_slots, is_available,
           (SELECT COUNT(*) FROM appointments WHERE preferred_date = appointment_availability.available_date AND time_slot = 'morning' AND status NOT IN ('declined', 'cancelled')) as morning_booked,
           (SELECT COUNT(*) FROM appointments WHERE preferred_date = appointment_availability.available_date AND time_slot = 'afternoon' AND status NOT IN ('declined', 'cancelled')) as afternoon_booked
    FROM appointment_availability 
    WHERE available_date >= CURDATE()
    ORDER BY available_date ASC
");
$availableDates = $availableDatesStmt ? $availableDatesStmt->fetch_all(MYSQLI_ASSOC) : [];
$availableDatesArray = [];
foreach ($availableDates as $av) {
    $availableDatesArray[$av['available_date']] = $av;
}

$stmt = $db->prepare("SELECT a.* , u.full_name AS official_name, p.full_name AS processed_by_name FROM appointments a LEFT JOIN users u ON a.official_id = u.user_id LEFT JOIN users p ON a.processed_by = p.user_id WHERE a.citizen_id = ? ORDER BY a.created_at DESC");
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

<?php if (!empty($_GET['cancelled'])): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
  Appointment cancelled successfully!
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
        <div class="mb-3">
          <label class="form-label">Preferred Date <span class="text-danger">*</span></label>
          <input type="text" name="preferred_date" id="preferred_date" class="form-control" placeholder="Select a date" required readonly>
          <small class="text-muted d-block mt-1">
            <i class="bi bi-info-circle me-1"></i>
            Only dates with availability set by admin can be selected. 
            <?php if (count($availableDatesArray) > 0): ?>
              <span id="availableDatesCount"><?= count($availableDatesArray) ?> available date(s) set.</span>
            <?php else: ?>
              <span class="text-warning">No dates available. Please contact the barangay office.</span>
            <?php endif; ?>
          </small>
        </div>
        <div class="mb-3">
          <label class="form-label">Time Slot <span class="text-danger">*</span></label>
          <div class="row g-2">
            <div class="col-6">
              <div class="card border h-100 time-slot-option" data-slot="morning" style="cursor: pointer;">
                <div class="card-body text-center">
                  <input type="radio" name="time_slot" value="morning" id="time_morning" class="form-check-input" required>
                  <label for="time_morning" class="form-check-label w-100" style="cursor: pointer;">
                    <strong class="d-block">Morning</strong>
                    <small class="text-muted">8:00 AM - 12:00 PM</small>
                    <div class="mt-2" id="morning_slots_info"></div>
                  </label>
                </div>
              </div>
            </div>
            <div class="col-6">
              <div class="card border h-100 time-slot-option" data-slot="afternoon" style="cursor: pointer;">
                <div class="card-body text-center">
                  <input type="radio" name="time_slot" value="afternoon" id="time_afternoon" class="form-check-input" required>
                  <label for="time_afternoon" class="form-check-label w-100" style="cursor: pointer;">
                    <strong class="d-block">Afternoon</strong>
                    <small class="text-muted">1:00 PM - 5:00 PM</small>
                    <div class="mt-2" id="afternoon_slots_info"></div>
                  </label>
                </div>
              </div>
            </div>
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
                <th>Queue / Time</th>
                <th>Status</th>
                <th>Official / Processed By</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($currentAppointments as $a): 
                $status = strtolower($a['status'] ?? '');
                $badgeClass = 'bg-secondary';
                if ($status === 'completed') $badgeClass = 'bg-success';
                elseif ($status === 'declined') $badgeClass = 'bg-danger';
                elseif ($status === 'cancelled') $badgeClass = 'bg-warning text-dark';
                elseif ($status === 'approved') $badgeClass = 'bg-info';
                elseif ($status === 'pending') $badgeClass = 'bg-warning';
                $canCancel = in_array($status, ['pending', 'approved']);
              ?>
                <tr>
                  <td><?= esc($a['service_type']) ?></td>
                  <td><?= esc($a['preferred_date']) ?></td>
                  <td>
                    <span class="badge bg-secondary"><?= esc($a['queue_number']) ?></span>
                    <?php if (!empty($a['time_slot'])): ?>
                      <span class="badge bg-info ms-1"><?= ucfirst(esc($a['time_slot'])) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?= $badgeClass ?> text-uppercase"><?= esc($a['status']) ?></span></td>
                  <td>
                    <?= esc($a['official_name'] ?? 'Unassigned') ?>
                    <?php if (!empty($a['processed_by_name']) && $a['status'] === 'completed'): ?>
                      <br><small class="text-muted">Processed by: <?= esc($a['processed_by_name']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($canCancel): ?>
                      <a href="?action=cancel&id=<?= esc($a['appointment_id']) ?>" 
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
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
  
  // Available dates data
  var availableDates = <?= json_encode($availableDatesArray, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var dateInput = document.getElementById('preferred_date');
  var morningRadio = document.getElementById('time_morning');
  var afternoonRadio = document.getElementById('time_afternoon');
  var morningInfo = document.getElementById('morning_slots_info');
  var afternoonInfo = document.getElementById('afternoon_slots_info');
  var timeSlotOptions = document.querySelectorAll('.time-slot-option');
  
  // Get array of available date strings (only dates with is_available = 1)
  var availableDateStrings = [];
  var disabledDates = [];
  
  Object.keys(availableDates).forEach(function(date) {
    var dateInfo = availableDates[date];
    // Only include dates that are available (is_available = 1)
    if (dateInfo.is_available == 1 || dateInfo.is_available == '1') {
      availableDateStrings.push(date);
    } else {
      disabledDates.push(date);
    }
  });
  
  // Initialize Flatpickr with enabled dates only
  if (availableDateStrings.length > 0) {
    var sortedDates = availableDateStrings.sort();
    var minDate = sortedDates[0];
    var maxDate = sortedDates[sortedDates.length - 1];
    
    flatpickr(dateInput, {
      dateFormat: "Y-m-d",
      minDate: minDate,
      maxDate: maxDate,
      enable: availableDateStrings, // Only enable these specific dates
      disableMobile: false,
      onChange: function(selectedDates, dateStr, instance) {
        if (dateStr) {
          dateInput.value = dateStr; // Ensure the value is set
          updateTimeSlots();
        }
      }
    });
  } else {
    // If no dates available, still initialize but disable all
    flatpickr(dateInput, {
      dateFormat: "Y-m-d",
      disable: true
    });
  }
  
  function updateTimeSlots() {
    var selectedDate = dateInput.value;
    if (!selectedDate || !availableDates[selectedDate]) {
      morningRadio.disabled = true;
      afternoonRadio.disabled = true;
      morningInfo.innerHTML = '<small class="text-danger">Date not available</small>';
      afternoonInfo.innerHTML = '<small class="text-danger">Date not available</small>';
      timeSlotOptions.forEach(function(opt) {
        opt.classList.remove('border-primary');
        opt.classList.add('opacity-50');
      });
      return;
    }
    
    var dateInfo = availableDates[selectedDate];
    
    // Check if date is explicitly disabled
    if (dateInfo.is_available == 0 || dateInfo.is_available == '0') {
      morningRadio.disabled = true;
      afternoonRadio.disabled = true;
      morningInfo.innerHTML = '<small class="text-danger">Date disabled</small>';
      afternoonInfo.innerHTML = '<small class="text-danger">Date disabled</small>';
      timeSlotOptions.forEach(function(opt) {
        opt.classList.remove('border-primary');
        opt.classList.add('opacity-50');
      });
      return;
    }
    
    // Morning slot
    if (dateInfo.morning_available == 1 || dateInfo.morning_available == '1') {
      morningRadio.disabled = false;
      var morningAvailable = dateInfo.morning_slots - dateInfo.morning_booked;
      morningInfo.innerHTML = '<small class="text-success">' + morningAvailable + ' slots available</small>';
      document.querySelector('[data-slot="morning"]').classList.remove('opacity-50');
      document.querySelector('[data-slot="morning"]').classList.add('border-primary');
    } else {
      morningRadio.disabled = true;
      morningInfo.innerHTML = '<small class="text-danger">Not available</small>';
      document.querySelector('[data-slot="morning"]').classList.add('opacity-50');
      document.querySelector('[data-slot="morning"]').classList.remove('border-primary');
    }
    
    // Afternoon slot
    if (dateInfo.afternoon_available == 1 || dateInfo.afternoon_available == '1') {
      afternoonRadio.disabled = false;
      var afternoonAvailable = dateInfo.afternoon_slots - dateInfo.afternoon_booked;
      afternoonInfo.innerHTML = '<small class="text-success">' + afternoonAvailable + ' slots available</small>';
      document.querySelector('[data-slot="afternoon"]').classList.remove('opacity-50');
      document.querySelector('[data-slot="afternoon"]').classList.add('border-primary');
    } else {
      afternoonRadio.disabled = true;
      afternoonInfo.innerHTML = '<small class="text-danger">Not available</small>';
      document.querySelector('[data-slot="afternoon"]').classList.add('opacity-50');
      document.querySelector('[data-slot="afternoon"]').classList.remove('border-primary');
    }
  }
  
  // Flatpickr handles the date selection, so we just need to update time slots on change
  // The onChange callback in flatpickr initialization will call updateTimeSlots()
  
  // Make time slot cards clickable
  timeSlotOptions.forEach(function(option) {
    option.addEventListener('click', function() {
      var slot = this.getAttribute('data-slot');
      var radio = document.getElementById('time_' + slot);
      if (!radio.disabled) {
        radio.checked = true;
      }
    });
  });
  
  // Form validation before submit
  var form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function(e) {
      var selectedDate = dateInput.value;
      if (!selectedDate) {
        e.preventDefault();
        alert('Please select a date.');
        return false;
      }
      
      if (!availableDates[selectedDate]) {
        e.preventDefault();
        alert('Selected date is not available. Please choose an available date.');
        dateInput.focus();
        return false;
      }
      
      if (availableDates[selectedDate].is_available == 0) {
        e.preventDefault();
        alert('Selected date has been disabled. Please choose another date.');
        dateInput.focus();
        return false;
      }
      
      var selectedTimeSlot = document.querySelector('input[name="time_slot"]:checked');
      if (!selectedTimeSlot || selectedTimeSlot.disabled) {
        e.preventDefault();
        alert('Please select a valid time slot (morning or afternoon).');
        return false;
      }
    });
  }
  
  // Initial update
  updateTimeSlots();
});
</script>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

