<?php
require __DIR__ . '/../connection.php';
$stmt = $conn->query('DESCRIBE users');
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo $col['Field'] . "\n";
}
?>
