<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/functions.php';
$brandColor = '#0033A0'; // GOV BLUE
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<title><?= $page_title ?? 'e-Log Barangay' ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- Bootstrap 5 CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  :root {
    --gov-blue: <?= $brandColor ?>;
  }
  body { background-color: #f4f6f9; }
  [data-bs-theme="dark"] body { background-color: #1a1a1a; }
  .navbar-brand { font-weight: 600; letter-spacing: .2px; }
  .container-box {
    background: #fff;
    padding: 22px;
    border-radius: 8px;
    border: 1px solid rgba(0,0,0,0.04);
  }
  [data-bs-theme="dark"] .container-box {
    background: #2d2d2d;
    border-color: rgba(255,255,255,0.1);
    color: #e0e0e0;
  }
  [data-bs-theme="dark"] .card {
    background: #2d2d2d;
    border-color: rgba(255,255,255,0.1);
    color: #e0e0e0;
  }
  [data-bs-theme="dark"] .card-header {
    background: #2d2d2d !important;
    border-color: rgba(255,255,255,0.1) !important;
    color: #e0e0e0;
  }
  [data-bs-theme="dark"] .list-group-item {
    background: #2d2d2d;
    border-color: rgba(255,255,255,0.1);
    color: #e0e0e0;
  }
  [data-bs-theme="dark"] .bg-light {
    background-color: #2d2d2d !important;
  }
  [data-bs-theme="dark"] .bg-white {
    background-color: #2d2d2d !important;
  }
  [data-bs-theme="dark"] .text-muted {
    color: #a0a0a0 !important;
  }
  [data-bs-theme="dark"] .alert-info {
    background-color: #1e3a5f;
    border-color: #2d5a8f;
    color: #b8d4f0;
  }
  .login-layout {
    min-height: 60vh;
  }
  .mock-icon-card {
    border: 1px dashed rgba(0,0,0,0.12);
    background: linear-gradient(135deg, rgba(0,51,160,0.04), rgba(0,51,160,0.02));
  }
  .mock-icon-circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: rgba(0,51,160,0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 600;
    color: rgba(0,51,160,0.6);
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  @media (max-width: 576px) {
    .mock-icon-circle {
      width: 110px;
      height: 110px;
      font-size: 1rem;
    }
  }
  .btn-primary {
    background: var(--gov-blue);
    border-color: var(--gov-blue);
  }
  .bg-primary-custom { background: var(--gov-blue) !important; }
  .text-primary-custom { color: var(--gov-blue) !important; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary-custom mb-4">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="/elog_barangay/public/index.php">
      <img src="/elog_barangay/public/assets/images/logo.png" alt="Barangay Duangan Logo" class="me-2" style="height: 48px; width: 48px; object-fit: contain;">
      Barangay e-Log
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item"><a class="nav-link" href="/elog_barangay/public/index.php">Home</a></li>
        <?php if (!empty($_SESSION['citizen'])): 
          // Get profile picture
          $citizenPic = null;
          if (!empty($_SESSION['citizen']['citizen_id'])) {
            if (!function_exists('db_connect')) {
              require_once __DIR__ . '/db.php';
            }
            $db = db_connect();
            $picStmt = $db->prepare("SELECT profile_picture FROM citizens WHERE citizen_id = ?");
            $picStmt->bind_param('i', $_SESSION['citizen']['citizen_id']);
            $picStmt->execute();
            $picResult = $picStmt->get_result();
            if ($picRow = $picResult->fetch_assoc()) {
              $citizenPic = $picRow['profile_picture'];
            }
          }
          $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="18" fill="#6c757d">' . strtoupper(substr($_SESSION['citizen']['name'] ?? 'U', 0, 1)) . '</text></svg>');
        ?>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/public/dashboard.php">Dashboard</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="citizenMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= $citizenPic ? esc('/elog_barangay/public/' . $citizenPic) : $defaultPic ?>" 
                   alt="Profile" 
                   class="rounded-circle me-2" 
                   style="width: 32px; height: 32px; object-fit: cover;">
              <span><?= esc($_SESSION['citizen']['name']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="citizenMenu">
              <li><a class="dropdown-item" href="/elog_barangay/public/create_appointment.php"><i class="bi bi-calendar-plus me-2"></i>Create Appointment</a></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/history.php"><i class="bi bi-clock-history me-2"></i>History</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php elseif (!empty($_SESSION['user'])): 
          // Get profile picture
          $adminPic = null;
          if (!empty($_SESSION['user']['user_id'])) {
            if (!function_exists('db_connect')) {
              require_once __DIR__ . '/db.php';
            }
            $db = db_connect();
            $picStmt = $db->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $picStmt->bind_param('i', $_SESSION['user']['user_id']);
            $picStmt->execute();
            $picResult = $picStmt->get_result();
            if ($picRow = $picResult->fetch_assoc()) {
              $adminPic = $picRow['profile_picture'];
            }
          }
          $defaultPic = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#dee2e6"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="18" fill="#6c757d">' . strtoupper(substr($_SESSION['user']['full_name'] ?? 'U', 0, 1)) . '</text></svg>');
        ?>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/admin/index.php">Dashboard</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="<?= $adminPic ? esc('/elog_barangay/public/' . $adminPic) : $defaultPic ?>" 
                   alt="Profile" 
                   class="rounded-circle me-2" 
                   style="width: 32px; height: 32px; object-fit: cover;">
              <span><?= esc($_SESSION['user']['full_name']) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
              <li><a class="dropdown-item" href="/elog_barangay/admin/profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/elog_barangay/admin/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/public/register.php">Register as Citizen</a></li>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/public/login.php">Citizen Login</a></li>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/admin/login.php">Official / Admin Login</a></li>
        <?php endif; ?>
        <li class="nav-item ms-2">
          <button class="btn btn-sm btn-outline-light" type="button" id="themeToggle" title="Toggle dark mode">
            <i id="themeIcon" class="bi bi-moon-fill"></i>
          </button>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mb-5">
