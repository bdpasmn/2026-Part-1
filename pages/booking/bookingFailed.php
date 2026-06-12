<?php
    $message = $_GET['message'] ?? 'Booking could not be completed.';
?>
<html>
    <head>
        <title>Booking Issue</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <header class="h-16 bg-gray-800 flex items-center px-8 border-b border-gray-700">
            <h1 class="text-white font-bold text-xl">BDPA Airports - TO BE REPLACED WITH NAV</h1>
        </header>

        <main class="max-w-7xl mx-auto p-6">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-white">Booking Could Not Be Completed</h1>
                <p class="text-gray-400 mt-2">We were unable to finish your request. Please review the details below and try again.</p>
            </div>

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
                <div class="space-y-8">
                    <div>
                        <h2 class="text-xl font-bold text-white mb-4">Notice</h2>
                        <div class="bg-slate-700 border border-gray-600 rounded-lg p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Message</div>

                            <div class="mt-3 text-lg font-semibold text-blue-300">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-950/30 border border-blue-900 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
                        <p class="text-gray-300">You can go back, adjust your passenger information, or select a different seat and try again.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-between">
                        <a href="searchFlights.php"class="px-6 h-12 flex items-center justify-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600 transition">Back to Flights</a>
                        <a href="javascript:history.back()"class="px-6 h-12 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Try Again</a>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>