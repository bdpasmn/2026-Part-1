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
if (array_key_exists($errorCode, $errorMessages)) {
    $errorMessage = $errorMessages[$errorCode];
}
?> 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?= $errorCode ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-900 min-h-screen flex items-center justify-center p-6">

    <div class="max-w-lg w-full bg-gray-800 border border-gray-700 rounded-2xl shadow-2xl p-8 text-center">

        <div class="mx-auto w-20 h-20 rounded-full bg-red-600/20 flex items-center justify-center mb-6">
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-10 w-10 text-red-500"
                 fill="none"
                 viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
            </svg>
        </div>

        <h1 class="text-5xl font-bold text-white mb-2">
            <?= $errorCode ?>
        </h1>

        <h2 class="text-2xl font-semibold text-white mb-4">
            Something Went Wrong
        </h2>

        <p class="text-gray-300 mb-8">
            <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="javascript:history.back()"
               class="px-6 py-3 rounded-lg bg-gray-700 hover:bg-gray-600 text-white font-medium transition">
                Go Back
            </a>

            <a href="/index.php"
               class="px-6 py-3 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium transition">
                Return Home
            </a>
        </div>

    </div>

</body>
</html>