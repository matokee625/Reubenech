<?php
// Migration script to add a 'phone' column to the 'users' table.
// Place this file in the admin directory and run it once (e.g., via browser or CLI).

require_once __DIR__ . '/../connection.php'; // Include the PDO $conn instance.

try {
    // Add phone column (VARCHAR 20, nullable) to support contact details.
    $sql = "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER status;";
    $conn->exec($sql);
    echo "Phone column added successfully to 'users' table.";
} catch (PDOException $e) {
    echo "Error adding phone column: " . $e->getMessage();
}
?>
