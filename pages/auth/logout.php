<?php
    require_once __DIR__ . '/../../components/config.php';
    session_start();
    $_SESSION = []; // Clearing session from memory
    session_destroy(); // Session destruction
    setcookie('remember_me', '', time() - 3600, '/'); // Removing the cookie
    header("Location: " . BASE_URI . "/index.php"); // Redirection
    exit;
?>