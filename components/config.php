<?php
    $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $baseUrl = '/' . $parts[0] . '/' . $parts[1];
    define('BASE_URI', "\smn");
    define('SECRET_KEY', '1fae3f95eaede409e5823685e0390134903daccfee989e690fd904735c602d17');
?>