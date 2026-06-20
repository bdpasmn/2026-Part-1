<?php
require_once __DIR__ . '/../../components/config.php';
session_start();

session_destroy();
setcookie('remember_me', '', time() - 3600, '/');
header("Location: " . BASE_URL . "/index.php");
exit;
?>