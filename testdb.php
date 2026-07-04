<?php
// testdb.php – place this in the root of your project (htdocs)
require 'connection.php';

if ($conn) {
    echo '<h2 style="color:green;">✅ Database connection works!</h2>';
} else {
    echo '<h2 style="color:red;">❌ Could not connect to the database.</h2>';
}
?>
