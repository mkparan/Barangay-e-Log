<?php
// verify_migration.php
// Run this file to verify the migration was successful
require_once __DIR__ . '/inc/db.php';

$db = db_connect();
$errors = [];
$success = [];

// Check if appointment_availability table exists
$result = $db->query("SHOW TABLES LIKE 'appointment_availability'");
if ($result->num_rows > 0) {
    $success[] = "✓ appointment_availability table exists";
} else {
    $errors[] = "✗ appointment_availability table does NOT exist";
}

// Check if processed_by column exists in appointments
$result = $db->query("SHOW COLUMNS FROM appointments LIKE 'processed_by'");
if ($result->num_rows > 0) {
    $success[] = "✓ processed_by column exists in appointments table";
} else {
    $errors[] = "✗ processed_by column does NOT exist in appointments table";
}

// Check if time_slot column exists in appointments
$result = $db->query("SHOW COLUMNS FROM appointments LIKE 'time_slot'");
if ($result->num_rows > 0) {
    $success[] = "✓ time_slot column exists in appointments table";
} else {
    $errors[] = "✗ time_slot column does NOT exist in appointments table";
}

// Display results
echo "<h2>Migration Verification</h2>";
echo "<h3 style='color: green;'>Success:</h3>";
foreach ($success as $msg) {
    echo "<p>$msg</p>";
}

if (!empty($errors)) {
    echo "<h3 style='color: red;'>Errors:</h3>";
    foreach ($errors as $msg) {
        echo "<p>$msg</p>";
    }
    echo "<p><strong>Please run the migration file: database_migration_appointment_availability.sql</strong></p>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✓ All migrations verified successfully!</p>";
}
?>

