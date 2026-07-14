<?php
require 'connection.php';
try {
    $conn->beginTransaction();

    $username = 'testuser2';
    $email = 'test2@test.com';
    $hash = password_hash('password', PASSWORD_BCRYPT);

    $insert = $conn->prepare("INSERT INTO users (username, email, password, role, status, has_paid) VALUES (?, ?, ?, 'member', 'suspended', 0)");
    $insert->execute([$username, $email, $hash]);
    
    $new_user_id = $conn->lastInsertId();

    $post_stmt = $conn->prepare("INSERT INTO milk_postings (user_id, liters, milk_type, asking_price, status) VALUES (?, 150.00, 'Cow', 40.00, 'active')");
    $post_stmt->execute([$new_user_id]);

    $conn->commit();

    require_once 'includes/sms.php';
    sendSMSAlert("PLEASE APROVE SOMEONE WHO HAS CREATED AN ACCOUNT");

    echo "Success: User ID " . $new_user_id;
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
