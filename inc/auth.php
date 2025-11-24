<?php
// inc/auth.php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**********************************************
 * ADMIN LOGIN
 **********************************************/
function admin_login($username, $password) {
    $db = db_connect();
    $stmt = $db->prepare("
        SELECT user_id, username, password_hash, full_name, role, is_active
        FROM users 
        WHERE username = ? LIMIT 1
    ");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if (!$row['is_active']) {
            return false;
        }

        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user'] = [
                'user_id' => $row['user_id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'role' => $row['role']
            ];

            audit_log(
                $row['username'],
                $row['user_id'],
                'admin_login',
                'users',
                $row['user_id']
            );

            return true;
        }
    }
    return false;
}


/**********************************************
 * ADMIN LOGOUT
 **********************************************/
function admin_logout() {
    if (isset($_SESSION['user'])) {
        audit_log(
            $_SESSION['user']['username'],
            $_SESSION['user']['user_id'],
            'admin_logout',
            'users',
            $_SESSION['user']['user_id']
        );
    }

    unset($_SESSION['user']);
    session_destroy();
}


/**********************************************
 * REQUIRE ADMIN (Protect admin pages)
 **********************************************/
function require_admin() {
    if (empty($_SESSION['user'])) {
        header('Location: /elog_barangay/admin/login.php?err=auth');
        exit;
    }
}


/**********************************************
 * REQUIRE CITIZEN (Protect citizen pages)
 **********************************************/
function require_citizen() {
    if (empty($_SESSION['citizen'])) {
        header('Location: /elog_barangay/public/login.php?err=auth');
        exit;
    }
    
    // Refresh citizen data to get latest verification status
    $db = db_connect();
    $stmt = $db->prepare("SELECT citizen_id, cin, first_name, last_name, is_active, is_verified FROM citizens WHERE citizen_id = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['citizen']['citizen_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // Check if account is banned
        if (!$row['is_active']) {
            unset($_SESSION['citizen']);
            header('Location: /elog_barangay/public/login.php?err=banned');
            exit;
        }
        // Update session with latest verification status
        $_SESSION['citizen']['is_verified'] = (bool)$row['is_verified'];
    }
}


/**********************************************
 * Redirect Citizen if Already Logged In
 **********************************************/
function redirect_if_citizen_logged_in() {
    if (!empty($_SESSION['citizen'])) {
        header('Location: /elog_barangay/public/dashboard.php');
        exit;
    }
}


/**********************************************
 * Redirect Admin if Already Logged In
 **********************************************/
function redirect_if_admin_logged_in() {
    if (!empty($_SESSION['user'])) {
        header('Location: /elog_barangay/admin/index.php');
        exit;
    }
}


/**********************************************
 * Prevent citizen from accessing admin pages
 **********************************************/
function block_citizen_from_admin() {
    if (!empty($_SESSION['citizen'])) {
        header('Location: /elog_barangay/public/dashboard.php?err=no_access');
        exit;
    }
}


/**********************************************
 * Prevent admin from accessing citizen endpoints
 **********************************************/
function block_admin_from_citizen() {
    if (!empty($_SESSION['user'])) {
        header('Location: /elog_barangay/admin/index.php?err=no_access');
        exit;
    }
}
