<?php
session_start();
session_unset();   // Clear data variables
session_destroy(); // Complete destruction of current user session
header("Location: login.html"); // Redirect back to login page
exit();
?>