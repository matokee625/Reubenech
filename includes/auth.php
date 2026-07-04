<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../connection.php';

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check if user is an admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

// Require login middleware
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /milkproject/login.php");
        exit();
    }
}

// Require admin middleware
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /milkproject/member/dashboard.php?error=unauthorized");
        exit();
    }
}

// Update last login timestamp
function updateLastLogin($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
}
?>
