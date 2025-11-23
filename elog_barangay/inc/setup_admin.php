<?php
// setup_admin.php

function setupAdmin() {
    // Initial admin configurations
    $adminRoles = [
        'super_admin' => 'Full access to all features',
        'admin' => 'Limited access to manage users and content',
    ];

    // Create default admin user
    $defaultAdmin = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_BCRYPT), // Secure password hashing
        'role' => 'super_admin',
    ];

    // Save roles and default admin to the database or configuration file
    // This is a placeholder for actual database logic
    // Example: saveRolesToDatabase($adminRoles);
    // Example: saveUserToDatabase($defaultAdmin);

    return [
        'roles' => $adminRoles,
        'defaultAdmin' => $defaultAdmin,
    ];
}

// Call the setup function to initialize admin settings
setupAdmin();
?>