<?php
require_once __DIR__ . '/../inc/auth.php';

session_start();
unset($_SESSION['user']);
unset($_SESSION['citizen']); // <-- IMPORTANT
session_destroy();

header('Location: /elog_barangay/admin/login.php?loggedout=1');
exit;
