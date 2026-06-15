<?php
    $confirmationCode = $_GET['confirmation'] ?? '';
?>
<html>
    <head>
        <title>Booking Confirmation</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <?php include "../../components/nav.php"; ?>
        
        <main class="max-w-7xl mx-auto p-6">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-lg p-6 mb-6">
                <h1 class="text-3xl font-bold text-white">Booking Confirmed</h1>
                <p class="text-gray-400 mt-2">Your flight has been booked successfully. You can view your ticket at any time using the confirmation code below.</p>
            </div>

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
                <div class="space-y-8">
                    <div>
                        <h2 class="text-xl font-bold text-white mb-4">Booking Details</h2>
                        <div class="bg-slate-700 border border-gray-600 rounded-lg p-6 transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-500">
                            <div class="text-gray-400 text-sm uppercase tracking-wide">Confirmation Code</div>
                            <div class="mt-3 text-4xl font-bold text-blue-400 tracking-wider">
                                <?= htmlspecialchars($confirmationCode) ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-950/40 border border-blue-900 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
                        <p class="text-gray-300"> Please save this confirmation code. It can be used to retrieve your ticket and booking information later.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-between">
                        <a href="searchFlights.php" class="px-6 h-12 flex items-center justify-center bg-gray-700 hover:bg-gray-600 text-white rounded-lg border border-gray-600 transition">Book Another Flight</a>
                        <a href="../ticket/ticket.php?confirmation=<?= urlencode($confirmationCode) ?>"class="px-6 h-12 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">View Ticket</a>
                    </div>
                </div>
            </div>
        </main>
    </body>
</html>