<?php
$password = 'ylekara16';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>
