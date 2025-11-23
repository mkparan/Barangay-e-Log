<?php
$page_title = "Manage Appointments";
require_once __DIR__ . '/../inc/header.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
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
    if ($action === 'approve') {
        $stmt = $db->prepare("UPDATE appointments SET status='approved' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_approved', 'appointments', $id);
        header("Location: announcements.php?msg=approved");
        exit;

    } elseif ($action === 'decline') {
        $stmt = $db->prepare("UPDATE appointments SET status='declined' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_declined', 'appointments', $id);
        header("Location: announcements.php?msg=declined");
        exit;

    } elseif ($action === 'reschedule') {
        echo "<script>window.location.href='appointment_action.php?id={$id}';</script>";
        exit;

    } elseif ($action === 'complete') {
        $stmt = $db->prepare("UPDATE appointments SET status='completed' WHERE appointment_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'appointment_completed', 'appointments', $id);
        header("Location: announcements.php?msg=completed");
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

$stmt = $db->query("
    SELECT a.*, c.first_name, c.last_name
    FROM appointments a
    JOIN citizens c ON a.citizen_id = c.citizen_id
    WHERE $where
    ORDER BY a.created_at DESC
");
$appointments = $stmt->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-box">

<h4 class="mb-3">Appointment Management</h4>

<?php if (!empty($_GET['msg'])): ?>
<div class="alert alert-success">
    Appointment <?= esc($_GET['msg']) ?> successfully.
</div>
<?php endif; ?>

<div class="mb-3">
    <a href="announcements.php?filter=all" class="btn btn-outline-primary btn-sm">All</a>
    <a href="announcements.php?filter=pending" class="btn btn-outline-warning btn-sm">Pending</a>
    <a href="announcements.php?filter=approved" class="btn btn-outline-success btn-sm">Approved</a>
    <a href="announcements.php?filter=declined" class="btn btn-outline-danger btn-sm">Declined</a>
    <a href="announcements.php?filter=completed" class="btn btn-outline-secondary btn-sm">Completed</a>
</div>

<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
    <thead class="table-light">
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
        <?php foreach($appointments as $a): ?>
        <tr>
            <td><?= esc($a['appointment_id']) ?></td>
            <td><?= esc($a['first_name'].' '.$a['last_name']) ?></td>
            <td><?= esc($a['service_type']) ?></td>
            <td><?= esc($a['preferred_date']) ?></td>
            <td><?= esc($a['queue_number']) ?></td>
            <td>
                <span class="badge bg-info"><?= esc($a['status']) ?></span>
            </td>
            <td>
                <?php if ($a['status'] === 'pending'): ?>
                    <a class="btn btn-success btn-sm" href="?action=approve&id=<?= esc($a['appointment_id']) ?>">Approve</a>
                    <a class="btn btn-warning btn-sm" href="?action=reschedule&id=<?= esc($a['appointment_id']) ?>">Reschedule</a>
                    <a class="btn btn-danger btn-sm" href="?action=decline&id=<?= esc($a['appointment_id']) ?>">Decline</a>
                    <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>">Mark Released</a>
                <?php elseif ($a['status'] === 'approved'): ?>
                    <a class="btn btn-outline-success btn-sm" href="?action=complete&id=<?= esc($a['appointment_id']) ?>">Mark Released</a>
                <?php else: ?>
                    <small class="text-muted">No actions available</small>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
