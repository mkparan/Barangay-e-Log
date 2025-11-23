<?php
session_start();
if (isset($_SESSION['user_id'])) {
    // Unset all of the session variables
    $_SESSION = array();

    // Destroy the session.
    session_destroy();

    // Redirect to login page with a logout confirmation message
    header("Location: login.php?message=You have been logged out successfully.");
    exit;
} else {
    // If no session exists, redirect to login page
    header("Location: login.php");
    exit;
}
?>