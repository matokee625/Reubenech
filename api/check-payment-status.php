<?php
require_once '../includes/auth.php';
require_once '../connection.php';
requireLogin();
header('Content-Type: application/json');

try {
    $stmt = $conn->prepare("SELECT has_paid FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $has_paid = (int)$stmt->fetchColumn();

    echo json_encode([
        'Success' => true,
        'Paid' => ($has_paid === 1),
        'Status' => $has_paid // 0 = unpaid, 1 = paid, 2 = pending
    ]);
} catch (Exception $e) {
    echo json_encode([
        'Success' => false,
        'Message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
