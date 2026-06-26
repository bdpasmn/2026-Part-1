<?php
    // Start the session and determine what booking error to display.
    session_start();
    
    $role = $_SESSION['role'] ?? null;

    $message = $_GET['message'] ?? 'Booking could not be completed.';

    // Check if the error is related to the selected flight.
    $isFlightNotFound =
    str_contains(strtolower($message), 'flight') &&
    (
        str_contains(strtolower($message), 'not exist') ||
        str_contains(strtolower($message), 'no longer available') ||
        str_contains(strtolower($message), 'not found') ||
        str_contains(strtolower($message), 'no flight was selected') ||
        str_contains(strtolower($message), 'has departed') ||
        str_contains(strtolower($message), 'was cancelled') ||
        str_contains(strtolower($message), '24 hours before departure')
    );

    // Customize the page based on the type of booking error.
    $title = $isFlightNotFound ? 'Selected Flight is Not Available' : 'Booking Could Not Be Completed';
    $subtitle = $isFlightNotFound ? 'The flight you selected is unavailable or no longer exists.' : 'We were unable to finish your request. Please review the details below and try again.';
    $helpText = $isFlightNotFound ? 'This flight may have been removed, cancelled, or the link may be incorrect. Please select another flight.' : 'You can go back, adjust your passenger information, or select a different seat and try again.';
?>
<html>
    <head>
        <title>Booking Issue</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <!-- Navigation bar -->
        <?php include "../../components/nav.php"; ?>

        <main class="max-w-7xl mx-auto p-6">
            <!-- Page heading -->
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($title) . '⚠️' ?></h1>
                <p class="text-gray-400 mt-2"><?= htmlspecialchars($subtitle) ?></p>
            </div>

            <!-- Error details -->
            <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
                <div class="space-y-8">
                    <div>
                        <div class="bg-slate-700 border border-gray-600 rounded-lg p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Message</div>
                            <div class="mt-3 text-lg font-semibold text-blue-300"><?= htmlspecialchars($message) ?></div>
                        </div>
                    </div>

                    <!-- Help text -->
                    <div class="bg-blue-950/30 border border-blue-900 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
                        <p class="text-gray-300"><?= htmlspecialchars($helpText) ?></p>                    
                    </div>

                    <!-- Navigation buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="searchFlights.php" class="px-6 h-12 flex items-center justify-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600 transition">Back to Flights</a>
                        <?php if (!$isFlightNotFound): ?>
                            <a href="javascript:history.back()" class="px-6 h-12 sm:ml-auto flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Try Again</a>
                        <?php else: ?>
                            <a href="../flights/flights.php" class="px-6 h-12 sm:ml-auto flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Browse Other Flights</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>