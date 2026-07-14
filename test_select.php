<?php
require 'connection.php';
$stmt = $conn->query('SELECT id, username, email FROM users ORDER BY id DESC LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
