<?php
$password = 'sawans123';  // Change this to your desired password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
echo $hashed_password;
?>

