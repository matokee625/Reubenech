<?php
require 'connection.php';
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $insert = $conn->prepare("INSERT INTO users (username, email, password, role, status, has_paid) VALUES ('testuser', 'test@test.com', 'pass', 'member', 'suspended', 0)");
    $insert->execute();
    echo 'Success';
} catch(PDOException $e) {
    echo $e->getMessage();
}
