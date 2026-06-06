<?php
require 'config.php';

session_unset();
session_destroy();
setcookie('last_login', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
header('Location: login.php');
exit;
