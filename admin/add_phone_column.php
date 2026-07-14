<?php
require_once __DIR__ . '/../includes/auth.php'; // loads $conn via connection.php

try {
    // Check if the 'phone' column exists in the users table
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
    $exists = $stmt->fetch();
    if (!$exists) {
        $conn->exec("ALTER TABLE users ADD phone VARCHAR(20) NULL DEFAULT NULL");
        echo "Phone column added successfully.\n";
    } else {
        echo "Phone column already exists.\n";
    }
} catch (PDOException $e) {
    echo "Error adding phone column: " . $e->getMessage();
}
?>
