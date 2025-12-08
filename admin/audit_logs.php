<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

// Only allow admin role to view audit logs (optional strict check)
if ($_SESSION['user']['role'] !== 'admin' && $_SESSION['user']['role'] !== 'captain') {
    // Optional: redirect if you want to restrict this page strictly to top admins
    // header('Location: dashboard.php');
    // exit;
}

$db = db_connect();

// Filter Parameters
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_action = $_GET['action'] ?? '';
$search = trim($_GET['search'] ?? '');

// Build Query
$where = "1";
$params = [];
$types = '';

if ($filter_date_from) {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= 's';
}

if ($filter_date_to) {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= 's';
}

if ($filter_action) {
    $where .= " AND action = ?";
    $params[] = $filter_action;
    $types .= 's';
}

if ($search) {
    $where .= " AND (user_identifier LIKE ? OR entity LIKE ? OR ip_address LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Count Total
$countSql = "SELECT COUNT(*) as total FROM audit_logs WHERE $where";
if (!empty($params)) {
    $stmt = $db->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $totalCount = (int)($stmt->get_result()->fetch_assoc()['total'] ?? 0);
} else {
    $totalCount = (int)($db->query($countSql)->fetch_assoc()['total'] ?? 0);
}
$totalPages = max(1, ceil($totalCount / $perPage));

// Get Unique Actions for Filter
$actionsStmt = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$availableActions = $actionsStmt ? $actionsStmt->fetch_all(MYSQLI_ASSOC) : [];

// Fetch Logs with User Details and Entity Details
$sql = "SELECT l.*, 
               u.full_name as official_name, u.role as official_role,
               c.first_name as c_fname, c.last_name as c_lname, c.cin as c_cin,
               a.service_type as a_service, a.preferred_date as a_date,
               tu.full_name as tu_fname, tu.role as tu_role
        FROM audit_logs l 
        LEFT JOIN users u ON l.user_id = u.user_id 
        LEFT JOIN citizens c ON (l.entity = 'citizens' AND l.entity_id = c.citizen_id)
        LEFT JOIN appointments a ON (l.entity = 'appointments' AND l.entity_id = a.appointment_id)
        LEFT JOIN users tu ON (l.entity = 'users' AND l.entity_id = tu.user_id)
        WHERE $where 
        ORDER BY l.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
if (!empty($params)) {
    $bindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($types . 'ii', ...$bindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = "Audit Logs";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
    <h4 class="mb-3">Audit Logs</h4>
    <p class="text-muted mb-4">View system activity and security logs.</p>

    <!-- filters -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="User, IP, or Entity..." value="<?= esc($search) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Action</label>
            <select name="action" class="form-select">
                <option value="">All Actions</option>
                <?php foreach ($availableActions as $a): ?>
                    <option value="<?= esc($a['action']) ?>" <?= $filter_action === $a['action'] ? 'selected' : '' ?>>
                        <?= esc($a['action']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= esc($filter_date_from) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= esc($filter_date_to) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">Filter</button>
            <a href="audit_logs.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Timestamp</th>
                    <th>User / Actor</th>
                    <th>Action</th>
                    <th>Details / Entity</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No logs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            // Inline Entity Lookup (Optimized)
                            $details = "";
                            $entityId = intval($log['entity_id']);
                            
                            if ($log['entity'] === 'citizens') {
                                if ($log['c_fname']) {
                                    $details = "Citizen: <strong>" . esc($log['c_fname'] . ' ' . $log['c_lname']) . "</strong><br><small class='text-muted'>" . esc($log['c_cin']) . "</small>";
                                } else {
                                    $details = "<span class='text-muted fst-italic'>Citizen (Deleted/ID #$entityId)</span>";
                                }
                            } elseif ($log['entity'] === 'appointments') {
                                if ($log['a_service']) {
                                    $details = "Appt: <strong>" . esc($log['a_service']) . "</strong><br><small class='text-muted'>" . esc($log['a_date']) . "</small>";
                                } else {
                                    $details = "<span class='text-muted fst-italic'>Appointment (Deleted/ID #$entityId)</span>";
                                }
                            } elseif ($log['entity'] === 'users') {
                                if ($log['tu_fname']) {
                                    $details = "User: <strong>" . esc($log['tu_fname']) . "</strong><br><small class='text-muted'>" . esc($log['tu_role']) . "</small>";
                                } else {
                                    $details = "<span class='text-muted fst-italic'>User (Deleted/ID #$entityId)</span>";
                                }
                            } else {
                                $details = esc($log['entity']) . " #" . $entityId;
                            }
                        ?>
                        <tr>
                            <td>
                                <small class="fw-bold"><?= esc(date('M d, Y', strtotime($log['created_at']))) ?></small><br>
                                <small class="text-muted"><?= esc(date('h:i:s A', strtotime($log['created_at']))) ?></small>
                            </td>
                            <td>
                                <?php if ($log['official_name']): ?>
                                    <div class="fw-bold"><?= esc($log['official_name']) ?></div>
                                    <small class="text-muted"><?= esc($log['official_role']) ?> (<?= esc($log['user_identifier']) ?>)</small>
                                <?php else: ?>
                                    <div><?= esc($log['user_identifier']) ?></div>
                                    <small class="text-muted">System/Unknown</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= esc($log['action']) ?></span>
                            </td>
                            <td>
                                <div><?= $details ?></div>
                            </td>
                            <td class="text-muted small"><?= esc($log['ip_address']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>">Previous</a>
                </li>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&action=<?= urlencode($filter_action) ?>&date_from=<?= urlencode($filter_date_from) ?>&date_to=<?= urlencode($filter_date_to) ?>">Next</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>
