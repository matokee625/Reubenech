<?php
// Generate a tiny 1x1 pixel ICO file for browsers that request favicon.ico
// This prevents the 404 console error
header('Content-Type: image/x-icon');
header('Cache-Control: public, max-age=86400');
echo base64_decode(
    'AAABAAEAAQEAAAEAGAAAABYAAABpQ0NBUAAAAQAAAAFITGluAQAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA' .
    'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
);
?>
