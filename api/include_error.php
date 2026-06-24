<?php

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function ($e) {
    $code = $e->getCode();

    if (!$code || $code < 100 || $code > 599) {
        $code = 500;
    }

    header("Location: /error.php?code=" . $code);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null) {
        header("Location: /error.php?code=500");
        exit;
    }
});