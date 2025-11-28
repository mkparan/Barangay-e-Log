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

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id > 0) {
        if ($action === 'verify') {
            $stmt = $db->prepare("UPDATE citizens SET is_verified = 1 WHERE citizen_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'citizen_verified', 'citizens', $id);
            $success = "Citizen account verified successfully.";
        } elseif ($action === 'unverify') {
            $stmt = $db->prepare("UPDATE citizens SET is_verified = 0 WHERE citizen_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'citizen_unverified', 'citizens', $id);
            $success = "Citizen account unverified.";
        } elseif ($action === 'ban') {
            $stmt = $db->prepare("UPDATE citizens SET is_active = 0 WHERE citizen_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'citizen_banned', 'citizens', $id);
            $success = "Citizen account banned.";
        } elseif ($action === 'unban') {
            $stmt = $db->prepare("UPDATE citizens SET is_active = 1 WHERE citizen_id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'citizen_unbanned', 'citizens', $id);
            $success = "Citizen account unbanned.";
        }
        
        if ($success) {
            header("Location: citizens.php?msg=" . urlencode($success) . ($_GET['search'] ? '&search=' . urlencode($_GET['search']) : ''));
            exit;
        }
    }
}

// Search functionality
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10; // Maximum 10 items per page
$offset = ($page - 1) * $perPage;

// Build query
$where = "1=1";
$params = [];
$types = '';

if ($search) {
    $where .= " AND (cin LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = 'sssss';
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM citizens WHERE $where";
$countStmt = $db->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalCount = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $perPage));

// Get citizens
$sql = "SELECT c.*, 
        (SELECT COUNT(*) FROM appointments WHERE citizen_id = c.citizen_id) as total_appointments
        FROM citizens c 
        WHERE $where 
        ORDER BY c.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $db->prepare($sql);
if (!empty($params)) {
    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$citizens = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$totalCitizens = (int)($db->query("SELECT COUNT(*) AS c FROM citizens")->fetch_assoc()['c'] ?? 0);
$verifiedCitizens = (int)($db->query("SELECT COUNT(*) AS c FROM citizens WHERE is_verified = 1")->fetch_assoc()['c'] ?? 0);
$unverifiedCitizens = (int)($db->query("SELECT COUNT(*) AS c FROM citizens WHERE is_verified = 0")->fetch_assoc()['c'] ?? 0);
$bannedCitizens = (int)($db->query("SELECT COUNT(*) AS c FROM citizens WHERE is_active = 0")->fetch_assoc()['c'] ?? 0);

$page_title = "Manage Citizens";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Manage Citizens</h4>
  <p class="text-muted">View, verify, and manage citizen accounts. Search by Barangay ID, name, email, or contact number.</p>

  <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?= esc($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if(!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?= esc($_GET['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>
</div>

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-primary">
      <p class="text-muted text-uppercase small mb-1">Total Citizens</p>
      <h3 class="mb-0"><?= number_format($totalCitizens) ?></h3>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-success">
      <p class="text-muted text-uppercase small mb-1">Verified</p>
      <h3 class="mb-0"><?= number_format($verifiedCitizens) ?></h3>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-warning">
      <p class="text-muted text-uppercase small mb-1">Pending Verification</p>
      <h3 class="mb-0"><?= number_format($unverifiedCitizens) ?></h3>
    </div>
  </div>
  <div class="col-md-6 col-lg-3">
    <div class="container-box h-100 border-start border-4 border-danger">
      <p class="text-muted text-uppercase small mb-1">Banned</p>
      <h3 class="mb-0"><?= number_format($bannedCitizens) ?></h3>
    </div>
  </div>
</div>

<!-- Search and Filters -->
<div class="container-box mb-4">
  <form method="get" class="row g-3">
    <div class="col-md-10">
      <label class="form-label">Search by Barangay ID, Name, Email, or Contact</label>
      <input type="text" class="form-control" name="search" value="<?= esc($search) ?>" placeholder="Enter Barangay ID, name, email, or contact number...">
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-search me-2"></i>Search
      </button>
    </div>
  </form>
  <?php if($search): ?>
    <div class="mt-2">
      <a href="citizens.php" class="btn btn-sm btn-outline-secondary">Clear Search</a>
      <span class="text-muted ms-2"><?= number_format($totalCount) ?> result(s) found</span>
    </div>
  <?php endif; ?>
</div>

<!-- Citizens Table -->
<div class="container-box">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Citizen Accounts</h5>
    <span class="badge bg-primary"><?= number_format($totalCount) ?> total</span>
  </div>

  <?php if(empty($citizens)): ?>
    <div class="alert alert-info mb-0">No citizens found.</div>
  <?php else: ?>
    <div class="table-responsive scrollable-table">
      <table class="table table-sm table-hover align-middle">
        <thead class="table-light sticky-top">
          <tr>
            <th>Barangay ID</th>
            <th>Name</th>
            <th>Contact</th>
            <th>Email</th>
            <th>Status</th>
            <th>Appointments</th>
            <th>Registered</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($citizens as $c): ?>
            <tr class="<?= !$c['is_active'] ? 'opacity-50' : '' ?>">
              <td>
                <strong><?= esc($c['cin']) ?></strong>
              </td>
              <td>
                <strong><?= esc($c['first_name'] . ' ' . ($c['middle_name'] ? $c['middle_name'] . ' ' : '') . $c['last_name']) ?></strong>
                <?php if ($c['gender']): ?>
                  <br><small class="text-muted"><?= esc($c['gender']) ?></small>
                <?php endif; ?>
              </td>
              <td>
                <?= esc($c['contact_number'] ?? '—') ?>
              </td>
              <td>
                <?= esc($c['email'] ?? '—') ?>
              </td>
              <td>
                <?php if (!$c['is_active']): ?>
                  <span class="badge bg-danger">Banned</span>
                <?php elseif (!$c['is_verified']): ?>
                  <span class="badge bg-warning text-dark">Unverified</span>
                <?php else: ?>
                  <span class="badge bg-success">Verified</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-info"><?= number_format($c['total_appointments']) ?></span>
              </td>
              <td>
                <small><?= esc(date('M d, Y', strtotime($c['created_at']))) ?></small>
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  <?php if (!$c['is_verified']): ?>
                    <a href="?action=verify&id=<?= $c['citizen_id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="btn btn-success"
                       title="Verify Account"
                       onclick="return confirm('Verify this citizen account?')">
                      <i class="bi bi-check-circle"></i>
                    </a>
                  <?php else: ?>
                    <a href="?action=unverify&id=<?= $c['citizen_id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="btn btn-warning"
                       title="Unverify Account"
                       onclick="return confirm('Unverify this citizen account? They will not be able to book appointments.')">
                      <i class="bi bi-x-circle"></i>
                    </a>
                  <?php endif; ?>
                  
                  <?php if ($c['is_active']): ?>
                    <a href="?action=ban&id=<?= $c['citizen_id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="btn btn-danger"
                       title="Ban Account"
                       onclick="return confirm('Ban this citizen account? They will not be able to login.')">
                      <i class="bi bi-ban"></i>
                    </a>
                  <?php else: ?>
                    <a href="?action=unban&id=<?= $c['citizen_id'] ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                       class="btn btn-success"
                       title="Unban Account"
                       onclick="return confirm('Unban this citizen account?')">
                      <i class="bi bi-check-circle"></i>
                    </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php if($totalPages > 1): ?>
      <nav aria-label="Citizens pagination" class="mt-3">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <?php if($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Previous</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Previous</span></li>
          <?php endif; ?>
          
          <?php
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          
          if ($startPage > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=1<?= $search ? '&search=' . urlencode($search) : '' ?>">1</a></li>
            <?php if ($startPage > 2): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
          <?php endif; ?>
          
          <?php for($i = $startPage; $i <= $endPage; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
          
          <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
              <li class="page-item disabled"><span class="page-link">...</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?><?= $search ? '&search=' . urlencode($search) : '' ?>"><?= $totalPages ?></a></li>
          <?php endif; ?>
          
          <?php if($page < $totalPages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">Next</a>
            </li>
          <?php else: ?>
            <li class="page-item disabled"><span class="page-link">Next</span></li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

