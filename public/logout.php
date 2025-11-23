<?php
session_start();
unset($_SESSION['citizen']);
unset($_SESSION['user']); // <-- Protect from mixing
session_destroy();

header('Location: /elog_barangay/public/login.php?loggedout=1');
exit;
