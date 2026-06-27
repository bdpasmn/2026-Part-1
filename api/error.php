<?php 

header('Content-Type: text/html; charset=UTF-8');

$errorCode = isset($_GET['code']) ? htmlspecialchars($_GET['code'], ENT_QUOTES, 'UTF-8') : 'Uh oh...';

$validErrorCodes = array(
    '400', '401', '403', '404', '409', '413', '422', '429', 
    '500', '502', '503', '504', '999', 'Uh oh...'
);

if (!in_array($errorCode, $validErrorCodes) && !is_numeric($errorCode)) {
    header('Location: index.php');
    exit();
}

if (is_numeric($errorCode)) {
    $numericCode = intval($errorCode);
    if ($numericCode < 400 || ($numericCode > 599)) {
        header('Location: index.php');
        exit();
    }
}


$errorMessage = 'Something went wrong with BDPA Airports. Please try again later';

$errorMessages = array(
    '401' => 'You need to authenticate to access BDPA Airports resources.',
    '403' => 'You don\'t have permission to perform this BDPA Airports action.',
    '404' => 'The BDPA Airports resource you\'re looking for isn\'t available.',
    '409' => 'There\'s a conflict with your BDPA Airports request. The resource may already exist.',
    '413' => 'Your BDPA Airports request is too large.',
    '422' => 'Your BDPA Airports request data is invalid. Please check your input.',
    '429' => 'Too many BPDA Airports requests. Please wait a moment and try again.',
    '500' => 'BDPA Airports server error. Please try again later.',
    '502' => 'BDPA Airports service is temporarily unavailable.',
    '503' => 'BDPA Airports service is temporarily down for maintenance.',
    '504' => 'BDPA Airports request timed out. Please try again.',
    '999' =>  'Something is wrong with our page come back later',
    'Uh oh...' => 'Something went wrong with BDPA Airports. Please try again later.',
);

if (is_numeric($errorCode) && $errorCode >= 500 && $errorCode <= 529) {
    $displayMessage = 'Something happened on the BDPA Airports server that is outside of our control. Please try again later.';
} else {
    error_log("BDPA Airports API Error Code: " . $errorCode);
    $displayMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : $errorMessage;
    error_log("BDPA Airports Display Message: " . $displayMessage);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uh Oh - Error</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .error-icon-filter {
            filter: brightness(0) invert(1);
        }
        .text-shadow {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        .text-shadow-sm {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen font-sans">
    <div class="min-h-screen flex items-center">
        <div class="max-w-4xl w-full px-5">

        <div class="flex items-center justify-start flex-row mb-5 ml-8">
                <div class="mr-5">
                    <img src="https://static.thenounproject.com/png/1648939-200.png"
                         alt="Question Mark Error Icon"
                         class="w-28 h-auto error-icon-filter lg:w-32 md:w-25 sm:w-20">
                </div>

                <h1 class="text-8xl font-bold text-white m-0 text-shadow lg:text-9xl md:text-7xl sm:text-5xl">
                    <?php echo $errorCode; ?>
                </h1>
            </div>

            <p class="text-2xl my-5 text-white ml-12 text-shadow-sm leading-relaxed lg:text-2xl md:text-xl sm:text-lg sm:ml-5">
                <?php echo $displayMessage; ?>
            </p>

            <?php if (is_numeric($errorCode)): ?>
            <div class="bg-gray-800 p-4 rounded-xl ml-12 mt-5 border border-gray-700 shadow-lg sm:ml-5">
                <p class="my-1 text-gray-300 text-base">
                    <strong class="text-white">What happened:</strong>
                </p>
  
                <?php if ($errorCode == '400'): ?>
                        <p class="my-1 text-gray-300 text-base">• Your request couldn't be processed because it contains invalid information.</p>
                        <p class="my-1 text-gray-300 text-base">• Please review your input and try again later.</p>

                    <?php elseif ($errorCode == '401'): ?>
                        <p class="my-1 text-gray-300 text-base">• You must be signed in to access this resource.</p>
                        <p class="my-1 text-gray-300 text-base">• Please sign in again and try again later.</p>

                    <?php elseif ($errorCode == '403'): ?>
                        <p class="my-1 text-gray-300 text-base">• You don't have permission to perform this action.</p>
                        <p class="my-1 text-gray-300 text-base">• Contact an administrator if you believe this is an error, or try again later.</p>

                    <?php elseif ($errorCode == '404'): ?>
                        <p class="my-1 text-gray-300 text-base">• The page or resource you're looking for couldn't be found.</p>
                        <p class="my-1 text-gray-300 text-base">• It may have been moved or deleted. Please try again later.</p>

                    <?php elseif ($errorCode == '409'): ?>
                        <p class="my-1 text-gray-300 text-base">• Your request conflicts with existing data.</p>
                        <p class="my-1 text-gray-300 text-base">• Please try again later or refresh the page before trying again.</p>

                    <?php elseif ($errorCode == '413'): ?>
                        <p class="my-1 text-gray-300 text-base">• Your request is too large to be processed.</p>
                        <p class="my-1 text-gray-300 text-base">• Please reduce the size of your request and try again later.</p>

                    <?php elseif ($errorCode == '422'): ?>
                        <p class="my-1 text-gray-300 text-base">• Some of the information you submitted is invalid.</p>
                        <p class="my-1 text-gray-300 text-base">• Please review your input and try again later.</p>

                    <?php elseif ($errorCode == '429'): ?>
                        <p class="my-1 text-gray-300 text-base">• Too many requests have been made in a short period of time.</p>
                        <p class="my-1 text-gray-300 text-base">• Please wait a few moments and try again later.</p>

                    <?php elseif ($errorCode == '999'): ?>
                        <p class="my-1 text-gray-300 text-base">• An unexpected error has occurred.</p>
                        <p class="my-1 text-gray-300 text-base">• Please try again later.</p>

                    <?php elseif ($errorCode >= 500): ?>
                        <p class="my-1 text-gray-300 text-base">• BDPA Airports is currently experiencing a temporary server issue.</p>
                        <p class="my-1 text-gray-300 text-base">• Your data is safe. Please try again later.</p>
                    <?php endif; ?>
                </p>
                <p class="my-1 mt-0 text-gray-300 text-base">
                    • <a href="/index.php"
                        class="text-blue-400 hover:text-blue-300 underline">
                        Try returning to home?
                    </a>
                </p>
            <?php endif; ?>
        </div>

    </div>

    <style>
        @media (max-width: 500px) {
            .flex-row {
                flex-direction: column !important;
                text-align: center !important;
            }

            .mr-5 {
                margin-right: 0 !important;
                margin-bottom: 0.625rem !important;
            }

            .ml-12,
            .sm\:ml-5 {
                margin-left: 0 !important;
                text-align: center !important;
            }
        }
    </style>
</body>
</html>