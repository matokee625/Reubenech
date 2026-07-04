<?php
require_once 'connection.php';

echo "Testing Database Connection...\n";
echo "------------------------------\n";

try {
    $stmt = $conn->query("SELECT * FROM users");
    $users = $stmt->fetchAll();
    
    echo "Found " . count($users) . " users in the database:\n";
    foreach ($users as $user) {
        echo "- Username: {$user->username} | Role: {$user->role} | Status: {$user->status}\n";
    }
    
    echo "------------------------------\n";
    echo "Database creation and data insertion was SUCCESSFUL!\n";
    
} catch (PDOException $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
?>
