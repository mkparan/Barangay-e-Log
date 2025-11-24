<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/functions.php';
require_once __DIR__ . '/../inc/auth.php';
require_admin();
block_citizen_from_admin();

// Only admin role can access this page
if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /elog_barangay/admin/index.php?err=no_access');
    exit;
}

$db = db_connect();
$errors = [];
$success = '';

// Handle POST - Create/Update official
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'official';
        $contact = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$username || !$password || !$full_name) {
            $errors[] = "Username, password, and full name are required.";
        } else {
            // Check if username exists
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
            $checkStmt->bind_param('s', $username);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                $errors[] = "Username already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, contact_number, email, is_active) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('ssssssi', $username, $hash, $full_name, $role, $contact, $email, $is_active);
                if ($stmt->execute()) {
                    $success = "Official account created successfully.";
                    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_created', 'users', $stmt->insert_id);
                } else {
                    $errors[] = "Error: " . $stmt->error;
                }
            }
        }
    } elseif ($action === 'update') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'official';
        $contact = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (!$user_id || !$full_name) {
            $errors[] = "User ID and full name are required.";
        } else {
            $stmt = $db->prepare("UPDATE users SET full_name=?, role=?, contact_number=?, email=?, is_active=? WHERE user_id=? AND role != 'admin'");
            $stmt->bind_param('ssssii', $full_name, $role, $contact, $email, $is_active, $user_id);
            if ($stmt->execute()) {
                $success = "Official updated successfully.";
                audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_updated', 'users', $user_id);
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
        }
    } elseif ($action === 'reset_password') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        
        if (!$user_id || !$password) {
            $errors[] = "User ID and new password are required.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash=? WHERE user_id=? AND role != 'admin'");
            $stmt->bind_param('si', $hash, $user_id);
            if ($stmt->execute()) {
                $success = "Password reset successfully.";
                audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_password_reset', 'users', $user_id);
            } else {
                $errors[] = "Error: " . $stmt->error;
            }
        }
    }
}

// Handle toggle active status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id=? AND role != 'admin'");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    audit_log($_SESSION['user']['username'], $_SESSION['user']['user_id'], 'official_toggle', 'users', $id);
    header("Location: officials.php?msg=toggled");
    exit;
}

// Get all officials (excluding admin)
$officials = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM appointments WHERE processed_by = u.user_id) as processed_count
    FROM users u 
    WHERE u.role != 'admin' 
    ORDER BY u.full_name
")->fetch_all(MYSQLI_ASSOC);

$roles = ['official', 'secretary', 'sk_official', 'captain'];

$page_title = "Manage Officials";
require_once __DIR__ . '/../inc/header.php';
?>

<div class="container-box mb-4">
  <h4 class="mb-3">Manage Officials</h4>
  <p class="text-muted">Create and manage official accounts. Officials can check in and process appointments.</p>

  <?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <?= esc($success) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if(!empty($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      Status updated successfully.
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php foreach($errors as $e): ?>
    <div class="alert alert-danger"><?= esc($e) ?></div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="container-box h-100">
      <h5 class="mb-3">Create New Official</h5>
      <form method="post">
        <input type="hidden" name="action" value="create">
        
        <div class="mb-3">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="username" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Password <span class="text-danger">*</span></label>
          <input type="password" class="form-control" name="password" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Full Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" name="full_name" required>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select class="form-select" name="role" required>
            <?php foreach($roles as $r): ?>
              <option value="<?= esc($r) ?>"><?= ucfirst(str_replace('_', ' ', esc($r))) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="mb-3">
          <label class="form-label">Contact Number</label>
          <input type="text" class="form-control" name="contact_number">
        </div>
        
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email">
        </div>
        
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="createActive" value="1" checked>
            <label class="form-check-label" for="createActive">
              Active (can log in)
            </label>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-person-plus me-2"></i>Create Official
        </button>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="container-box h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Officials</h5>
        <span class="badge bg-primary"><?= count($officials) ?> officials</span>
      </div>

      <?php if(empty($officials)): ?>
        <div class="alert alert-info mb-0">No officials created yet.</div>
      <?php else: ?>
        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light sticky-top">
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Username</th>
                <th>Status</th>
                <th>Processed</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($officials as $o): ?>
                <tr class="<?= !$o['is_active'] ? 'opacity-50' : '' ?>">
                  <td>
                    <strong><?= esc($o['full_name']) ?></strong>
                    <?php if ($o['email']): ?>
                      <br><small class="text-muted"><?= esc($o['email']) ?></small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', esc($o['role']))) ?></span>
                  </td>
                  <td><?= esc($o['username']) ?></td>
                  <td>
                    <?php if($o['is_active']): ?>
                      <span class="badge bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-danger">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-info"><?= (int)$o['processed_count'] ?></span>
                  </td>
                  <td>
                    <div class="btn-group btn-group-sm">
                      <a href="?toggle=<?= $o['user_id'] ?>" 
                         class="btn btn-<?= $o['is_active'] ? 'warning' : 'success' ?>"
                         title="<?= $o['is_active'] ? 'Disable' : 'Enable' ?>">
                        <i class="bi bi-<?= $o['is_active'] ? 'x-circle' : 'check-circle' ?>"></i>
                      </a>
                      <button type="button" 
                              class="btn btn-primary" 
                              data-bs-toggle="modal" 
                              data-bs-target="#editModal<?= $o['user_id'] ?>"
                              title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                
                <!-- Edit Modal -->
                <div class="modal fade" id="editModal<?= $o['user_id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Official</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form method="post">
                        <div class="modal-body">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="user_id" value="<?= $o['user_id'] ?>">
                          
                          <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?= esc($o['full_name']) ?>" required>
                          </div>
                          
                          <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                              <?php foreach($roles as $r): ?>
                                <option value="<?= esc($r) ?>" <?= $o['role'] === $r ? 'selected' : '' ?>>
                                  <?= ucfirst(str_replace('_', ' ', esc($r))) ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          
                          <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" class="form-control" name="contact_number" value="<?= esc($o['contact_number'] ?? '') ?>">
                          </div>
                          
                          <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?= esc($o['email'] ?? '') ?>">
                          </div>
                          
                          <div class="mb-3">
                            <div class="form-check">
                              <input class="form-check-input" type="checkbox" name="is_active" id="editActive<?= $o['user_id'] ?>" value="1" <?= $o['is_active'] ? 'checked' : '' ?>>
                              <label class="form-check-label" for="editActive<?= $o['user_id'] ?>">
                                Active
                              </label>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                          <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                      </form>
                      
                      <hr class="my-0">
                      
                      <form method="post" class="p-3">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= $o['user_id'] ?>">
                        <div class="mb-2">
                          <label class="form-label">Reset Password</label>
                          <input type="password" class="form-control" name="password" placeholder="New password" required>
                        </div>
                        <button type="submit" class="btn btn-warning btn-sm w-100">Reset Password</button>
                      </form>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../inc/footer.php'; ?>

