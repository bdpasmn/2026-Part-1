<?php
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'Customer';
    unset($_SESSION['user_id']);
    unset($_SESSION['role']);

    require_once "./database/db.php";

    $stmt = $pdo->query('SELECT COUNT(*) FROM "Tickets"');
    $totalTickets = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM \"Users\" WHERE role = 'Customer'");
    $totalCustomers = $stmt->fetchColumn();

    $role = $_SESSION['role'] ?? 'Guest';
?>
<html>
    <head>
        <title>BDPA Airports</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>

    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <?php include "./components/nav.php"; ?>

            <section class="p-6">
                <div class="relative overflow-hidden rounded-xl border border-gray-700 min-h-[500px]">
                    <img src="https://i.ytimg.com/vi/qH07aMO-ENk/maxresdefault.jpg" class="absolute inset-0 w-full h-full object-cover">

                    <div class="absolute inset-0 bg-black/70"></div>
                    <div class="relative z-10 flex items-center min-h-[500px] p-10 lg:p-16">
                        <div class="max-w-4xl">
                            <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS</p>
                            <h1 class="text-5xl md:text-7xl font-bold leading-tight mb-6">Your Journey Starts Here</h1>
                            <p class="text-xl text-gray-300 max-w-3xl mb-8">Search flights, book tickets, and track real-time arrivals, departures, gate changes, and flight status update from the official BDPA Airports portal.</p>

                            <div class="flex flex-wrap gap-4">
                                <a href="index.php" class="px-8 py-4 bg-blue-600 rounded-lg font-medium hover:bg-blue-700 hover:shadow-md transition">
                                    Explore BDPA Airports
                                </a>

                                <a href="./pages/flights/flights.php" class="px-8 py-4 bg-gray-700 border border-gray-600 rounded-lg font-medium hover:bg-gray-600 transition">
                                    Browse Flights
                                </a>

                                <?php if ($role == 'guest' || $role == 'Customer'): ?>
                                    <a href="./pages/booking/searchFlights.php" class="px-8 py-4 bg-gray-700 border border-gray-600 rounded-lg font-medium hover:bg-gray-600 transition">
                                        Book Your Next Trip
                                    </a>
                                <?php endif; ?>

                                <?php if ($role == 'guest'): ?>
                                    <a href="./pages/ticket/viewTicket.php" class="px-8 py-4 bg-gray-700 border border-gray-600 rounded-lg font-medium hover:bg-gray-600 transition">
                                        View Your Tickets
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="px-6">
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500 transition duration-300">
                        <div class="text-4xl mb-4">✈️</div>
                        <h3 class="text-2xl font-bold mb-3">Flights</h3>
                        <p class="text-gray-400">Browse arrivals and departures with sorting, searching, and live updates.</p>
                    </div>

                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500 transition duration-300">
                        <div class="text-4xl mb-4">🎟️</div>
                        <h3 class="text-2xl font-bold mb-3">Booking</h3>
                        <p class="text-gray-400">Purchase tickets and select seats for upcoming flights.</p>
                    </div>

                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500 transition duration-300">
                        <div class="text-4xl mb-4">📄</div>
                        <h3 class="text-2xl font-bold mb-3">Ticket View</h3>
                        <p class="text-gray-400">View your tickets using your confirmation code.</p>
                    </div>

                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500 transition duration-300">
                        <div class="text-4xl mb-4">👤</div>
                        <h3 class="text-2xl font-bold mb-3">Dashboard</h3>
                        <p class="text-gray-400">Manage trips, saved cards, account settings, and history.</p>
                    </div>
                </div>
            </section>

            <section class="p-6">
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="xl:col-span-2 bg-gray-800 border border-gray-700 rounded-lg p-8">
                        <p class="tracking-[0.25em] text-sm text-blue-300 mb-3">SAFE, COMFORTABLE, AND RELIABLE AIR TRAVEL</p>
                        <h2 class="text-4xl font-bold mb-6">Welcome to BDPA AIRPORTS</h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed text-lg">
                            <p>BDPA Airports provides a secure, reliable web portal for guests, customers, and administrators alike to view and book flights departing from your local BDPA airport.</p>
                            <p>Search through flights, purchase tickets, manage travel, track live flight updates, and access personalized dashboards through one secure website.</p>
                            <p>Whether you're planning your next flight or booking a trip for someone else, designed for convenience, BDPA Airports provides a flawless travel experience. Your journey starts here...</p>
                        </div>
                    </div>

                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-8">
                        <h3 class="text-2xl font-bold mb-6">Other Stats</h3>
                        <div>
                            <div id="ticketCount" class="text-4xl font-bold text-blue-400">0</div>
                            <div class="text-gray-400">Flights Booked</div>
                        </div>

                        <div>
                            <div class="text-4xl font-bold text-blue-400">12+</div>
                            <div class="text-gray-400">Airports Served</div>
                        </div>

                        <div>
                            <div id="customerCount" class="text-4xl font-bold text-blue-400">0</div>
                            <div class="text-gray-400">Registered Customers</div>
                        </div>
                    </div>
                </div>
            </section>
            
        </div>

        <script>
            async function loadStats() {
                try {
                    const res = await fetch('./stats.php');
                    const data = await res.json();

                    document.getElementById('ticketCount').innerText = data.tickets.toLocaleString();
                    document.getElementById('customerCount').innerText = data.customers.toLocaleString();

                } catch (err) {
                    console.error("Failed to load stats:", err);
                }
            }

            loadStats();
            setInterval(loadStats, 5000);
        </script>
    </body>
</html>