<?php

header('Content-Type: text/html; charset=UTF-8');


$errorCode = isset($_GET['code']) ? htmlspecialchars($_GET['code'], ENT_QUOTES, 'UTF-8') : 'Oh no';


$errorMessage = 'Something went wrong on our side. Please try again later';


$errorMessages = array(
    '400' => 'Your request has a problem. Please check and try again.',
    '401' => 'You need to log in to access this resource.',
    '403' => 'You don’t have permission to view this content.',
    '404' => 'The page you’re looking for isn’t available.',
    '413' => 'Your request is too large.',
    '429' => 'Too many requests. Please wait a moment and try again.',
    'Uh oh...' => 'Something went wrong on our side. Please try again later.',
);


if (is_numeric($errorCode) && $errorCode >= 500 && $errorCode <= 529) {
    $displayMessage = 'Something happened on our side that is outside of our control. Please try again later.';
} else {
    error_log("Received Error Code: " . $errorCode);
    $displayMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $errorMessage;
    error_log("Display Message: " . $displayMessage);
}