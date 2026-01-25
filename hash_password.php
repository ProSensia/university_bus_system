<?php
// Simple password hasher for testing
// Usage: Copy the hash output and update the database

echo "=== STUDENT ACCOUNT ===\n";
$student_password = 'test123';
$student_hash = password_hash($student_password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Email: test@student.edu\n";
echo "Password: " . $student_password . "\n";
echo "Hash: " . $student_hash . "\n";
echo "SQL Command:\n";
echo "UPDATE users SET password = '" . $student_hash . "' WHERE email = 'test@student.edu';\n";
echo "\n\n";

echo "=== ADMIN ACCOUNT ===\n";
$admin_password = 'admin123';
$admin_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Email: admin@university.edu\n";
echo "Password: " . $admin_password . "\n";
echo "Hash: " . $admin_hash . "\n";
echo "SQL Command:\n";
echo "UPDATE users SET password = '" . $admin_hash . "' WHERE email = 'admin@university.edu';\n";
?>
