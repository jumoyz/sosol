<?php
// Simple password hash generator for testing
// Run this script to generate proper password hashes for test users

$passwords = [
    'jean@example.com' => 'password123',
    'marie@example.com' => 'password123'
];

echo "Password Hashes for Test Users:\n\n";

foreach ($passwords as $email => $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Hash: $hash\n\n";
    
    // SQL update statement
    echo "UPDATE users SET password_hash = '$hash' WHERE email = '$email';\n\n";
}

echo "Copy and run the UPDATE statements in your MySQL database to fix the test user passwords.\n";
?>
