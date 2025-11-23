<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function login($username, $password) {
    // Placeholder for actual authentication logic
    // Validate username and password against the database
    // If valid, set session variables
    $_SESSION['user_id'] = $username; // Example of setting user_id
}

function logout() {
    session_destroy();
    header("Location: ../public/login.php?logout=success");
    exit();
}

function checkUserRole($role) {
    // Placeholder for checking user roles
    // Return true if the user has the specified role
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}
?>