<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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
        <?php if (!empty($_SESSION['citizen'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="citizenMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Menu
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="citizenMenu">
              <li><a class="dropdown-item" href="/elog_barangay/public/dashboard.php"><i class="bi bi-house-door me-2"></i>Dashboard</a></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/create_appointment.php"><i class="bi bi-calendar-plus me-2"></i>Create Appointment</a></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/history.php"><i class="bi bi-clock-history me-2"></i>History</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
              <li><a class="dropdown-item" href="/elog_barangay/public/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
          </li>
        <?php elseif (!empty($_SESSION['user'])): ?>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/admin/index.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link" href="/elog_barangay/admin/logout.php">Logout</a></li>
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
