<?php
require_once __DIR__ . '/connection.php';
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` VARCHAR(50) PRIMARY KEY,
        `value` VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $conn->exec("INSERT IGNORE INTO settings (`key`, `value`) VALUES
        ('volume_unit', 'L'),
        ('currency_symbol', 'Ksh');");

    $count = $conn->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    echo "Settings table ready. Rows: " . $count . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
