<?php
// setup_database.php - Initialize required MySQL tables for MilkProject
// Run this script once via browser (http://localhost/milkproject/setup_database.php) or CLI (php setup_database.php)

require_once __DIR__ . '/connection.php';

$queries = [];

// Users table (basic fields for authentication and admin)
$queries[] = "CREATE TABLE IF NOT EXISTS users (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    username VARCHAR(50) NOT NULL UNIQUE,\n    email VARCHAR(100) NOT NULL UNIQUE,\n    password VARCHAR(255) NOT NULL,\n    role ENUM('admin','member') NOT NULL DEFAULT 'member',\n    status ENUM('active','suspended','trash') NOT NULL DEFAULT 'active',\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n    last_login DATETIME NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Password resets table (token storage)
$queries[] = "CREATE TABLE IF NOT EXISTS password_resets (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    user_id INT NOT NULL,\n    token VARCHAR(64) NOT NULL UNIQUE,\n    expires_at DATETIME NOT NULL,\n    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    CONSTRAINT fk_user_reset FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// Access logs table (login activity)
$queries[] = "CREATE TABLE IF NOT EXISTS access_logs (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    user_id INT NOT NULL,\n    username VARCHAR(50) NOT NULL,\n    action VARCHAR(20) NOT NULL,\n    ip_address VARCHAR(45) NOT NULL,\n    user_agent TEXT NOT NULL,\n    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n    CONSTRAINT fk_user_log FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

foreach ($queries as $sql) {
    try {
        $conn->exec($sql);
        echo "Successfully executed: " . strtok($sql, "(") . "\n";
    } catch (PDOException $e) {
        echo "Error creating table: " . $e->getMessage() . "\n";
    }
}

// Optionally, create a default admin account if none exists
$adminCheck = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($adminCheck == 0) {
    $defaultAdminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (:u, :e, :p, 'admin')");
    $stmt->execute([
        ':u' => 'admin',
        ':e' => 'admin@example.com',
        ':p' => $defaultAdminPass
    ]);
    echo "Created default admin user (admin / Admin@123). Please change the password after first login.\n";
}
?>
