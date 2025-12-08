<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

$db = db_connect();
$errors = [];
$success = '';

// Handle POST - Add/Update availability
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['available_date'] ?? '';
    $morningSlots = (int)($_POST['morning_slots'] ?? 25);
    $afternoonSlots = (int)($_POST['afternoon_slots'] ?? 25);
    $morningAvailable = isset($_POST['morning_available']) ? 1 : 0;
    $afternoonAvailable = isset($_POST['afternoon_available']) ? 1 : 0;
    $isAvailable = isset($_POST['is_available']) ? 1 : 0;
    
    if (!$date) {
        $errors[] = "Date is required.";
    } else {
        // Check if date already exists
        $checkStmt = $db->prepare("SELECT availability_id FROM appointment_availability WHERE available_date = ?");
        $checkStmt->bind_param('s', $date);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // Update existing
            $stmt = $db->prepare("UPDATE appointment_availability SET morning_slots=?, afternoon_slots=?, morning_available=?, afternoon_available=?, is_available=?, updated_at=NOW() WHERE availability_id=?");
            $stmt->bind_param('iiiiii', $morningSlots, $afternoonSlots, $morningAvailable, $afternoonAvailable, $isAvailable, $existing['availability_id']);
        } else {
            // Insert new
            $stmt = $db->prepare("INSERT INTO appointment_availability (available_date, morning_slots, afternoon_slots, morning_available, afternoon_available, is_available, created_by) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('siiiiii', $date, $morningSlots, $afternoonSlots, $morningAvailable, $afternoonAvailable, $isAvailable, $_SESSION['user']['user_id']);
        }
        
        if ($stmt->execute()) {
            $success = "Availability updated successfully.";
            audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'availability_updated', 'appointment_availability', $stmt->insert_id ?? $existing['availability_id']);
        } else {
            $errors[] = "Error: " . $stmt->error;
        }
    }
}

// Handle DELETE
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $delStmt = $db->prepare("DELETE FROM appointment_availability WHERE availability_id=?");
    $delStmt->bind_param('i', $id);
    $delStmt->execute();
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'availability_deleted', 'appointment_availability', $id);
    header("Location: appointment_availability.php?msg=deleted");
    exit;
}

// Auto-delete past availability dates
$db->query("DELETE FROM appointment_availability WHERE available_date < CURDATE()");

// Get all availability records (now only >= Today)
$availability = $db->query("
    SELECT a.*, u.full_name as created_by_name
    FROM appointment_availability a
    LEFT JOIN users u ON a.created_by = u.user_id
    ORDER BY a.available_date DESC
")->fetch_all(MYSQLI_ASSOC);

$page_title = "Appointment Availability";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Manage Appointment Availability</h4>
  <p class="text-muted">Set which dates are available for appointments and configure morning/afternoon slots.</p>

  <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?= esc($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if(!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      Availability <?= esc($_GET['msg']) ?> successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="container-box h-100">
      <h5 class="mb-3">Add/Update Availability</h5>
      <form method="post">
        <div class="mb-3">
          <label class="form-label">Date <span class="text-danger">*</span></label>
          <input type="date" class="form-control" name="available_date" min="<?= date('Y-m-d') ?>" required>
          <small class="text-muted">Select the date to configure availability</small>
        </div>
        
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Morning Slots</label>
            <input type="number" class="form-control" name="morning_slots" value="25" min="0" max="100" required>
            <small class="text-muted">Number of morning appointments</small>
          </div>
          <div class="col-md-6">
            <label class="form-label">Afternoon Slots</label>
            <input type="number" class="form-control" name="afternoon_slots" value="25" min="0" max="100" required>
            <small class="text-muted">Number of afternoon appointments</small>
          </div>
        </div>

        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="morning_available" id="morningCheck" value="1" checked>
            <label class="form-check-label" for="morningCheck">
              Morning Available
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="afternoon_available" id="afternoonCheck" value="1" checked>
            <label class="form-check-label" for="afternoonCheck">
              Afternoon Available
            </label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_available" id="availableCheck" value="1" checked>
            <label class="form-check-label" for="availableCheck">
              Date Available (uncheck to disable this date)
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-calendar-check me-2"></i>Save Availability
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Configured Dates</h5>
        <span class="badge bg-primary"><?= count($availability) ?> dates</span>
      </div>

      <?php if(empty($availability)): ?>
        <div class="alert alert-info mb-0">No availability configured yet. Add dates to allow appointments.</div>
      <?php else: ?>
        <div class="table-responsive scrollable-table">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light sticky-top">
              <tr>
                <th>Date</th>
                <th>Morning</th>
                <th>Afternoon</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($availability as $av): ?>
                <tr class="<?= !$av['is_available'] ? 'opacity-50' : '' ?>">
                  <td>
                    <strong><?= esc(date('M d, Y', strtotime($av['available_date']))) ?></strong>
                    <div class="text-muted small"><?= esc(date('l', strtotime($av['available_date']))) ?></div>
                  </td>
                  <td>
                    <?php if($av['morning_available']): ?>
                      <span class="badge bg-info"><?= $av['morning_slots'] ?> slots</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if($av['afternoon_available']): ?>
                      <span class="badge bg-warning text-dark"><?= $av['afternoon_slots'] ?> slots</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if($av['is_available']): ?>
                      <span class="badge bg-success">Available</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Unavailable</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="?delete=<?= $av['availability_id'] ?>" 
                       class="btn btn-sm btn-danger"
                       onclick="return confirm('Delete this availability?')"
                       title="Delete">
                      <i class="bi bi-trash"></i>
                    </a>
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

