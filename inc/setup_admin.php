<?php
// inc/setup_admin.php
// Run this file once to create an admin account. DELETE this file after use.
require_once __DIR__ . '/db.php';
$db = db_connect();

// Change these to desired credentials (for production change password immediately)
$username = 'admin';
$password = 'AdminPass123!'; // change now
$full_name = 'System Administrator';
$email = 'admin@barangay.local';
$phone = '09171234567';

$hash = password_hash($password, PASSWORD_DEFAULT);

// make sure $role is a variable (bind_param needs variables passed by reference)
$role = 'admin';

$stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
// use store_result() to allow num_rows on the statement
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo "Admin user already exists. Remove this file NOW.";
    exit;
}
$stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role, contact_number, email) VALUES (?,?,?,?,?,?)");
$stmt->bind_param('ssssss', $username, $hash, $full_name, $role, $phone, $email);
if ($stmt->execute()) {
    echo "Admin created. Username: {$username} Password: {$password} - DELETE this file now!";
} else {
    echo "Error: ".$stmt->error;
}
