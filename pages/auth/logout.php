<?php
    require_once __DIR__ . '/../../components/config.php';
    session_start();
    $_SESSION = []; //clearing session from memory
    session_destroy(); //session destruction
    setcookie('remember_me', '', time() - 3600, '/'); //removing remember me cookie
    header("Location: " . BASE_URI . "/index.php"); //and finally, redirection
    exit;
?>