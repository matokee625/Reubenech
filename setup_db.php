<?php
// setup_db.php - Run once to create required tables for the MilkProject application.
require_once __DIR__ . '/connection.php';

$queries = [];

// Users table (referenced in register.php, login.php)
$queries[] = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','member') NOT NULL DEFAULT 'member',
    status ENUM('active','suspended','trash') NOT NULL DEFAULT 'suspended',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Milk postings table (used during registration to seed a default posting)
$queries[] = "CREATE TABLE IF NOT EXISTS milk_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    liters DECIMAL(10,2) NOT NULL,
    milk_type VARCHAR(20) NOT NULL,
    asking_price DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Notifications table (admin notifications)
$queries[] = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Password resets table (used by forgot password flow)
$queries[] = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Access logs table (used in login.php for audit)
$queries[] = "CREATE TABLE IF NOT EXISTS access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

foreach ($queries as $sql) {
    $conn->exec($sql);
}

// Ensure a default admin account exists (username: admin, password: admin123)
$adminCheck = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
$adminCheck->execute();
if ($adminCheck->fetchColumn() == 0) {
    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('admin', 'admin@example.com', :pwd, 'admin', 'active')");
    $stmt->execute(['pwd' => $adminPass]);
}

echo "Database tables have been created or already exist.\n";
?>
