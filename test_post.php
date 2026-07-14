<?php
$ch = curl_init('http://localhost/milkproject/register.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'username' => 'user3',
    'email' => 'user3@test.com',
    'password' => 'password123',
    'confirm_password' => 'password123'
]);
$response = curl_exec($ch);
curl_close($ch);

if (strpos($response, 'Registration successful!') !== false) {
    echo "Success!";
} else {
    echo "Failed. Response:\n";
    // echo strip_tags($response);
}
