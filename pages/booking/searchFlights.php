<?php
    // Starting session and including the API
    session_start();
    require_once "../../api/api.php";
    require_once "../../api/key.php";

    // Initialize API client
    $api = new AirportsAPI(AIRPORTS_API_KEY);

    // Check user role from session
    $role = $_SESSION['role'] ?? null;

    // Redirect admins/root users to dashboard
    if ($role == 'Admin' || $role == 'Root' || $role == 'Attendant') {
        if (in_array($role, ['Admin', 'Root', 'Attendant'])) {
            $roleLower = strtolower($role);
            header("Location: ./pages/dashboard/{$roleLower}/{$roleLower}.php");
        }
        exit;
    }

    // Fetch all airports from API
    $airportResult = $api->getAirports();
    $airports = $airportResult['airports'] ?? [];

    // Remove home airport (SMN) from list
    $airports = array_filter($airports, function ($airport) {
        return ($airport['shortName'] ?? '') !== 'SMN';
    });

    // Re-index array after filtering
    $airports = array_values($airports);

    // Shuffle for randomness
    shuffle($airports);

    // Pick 4 featured airports
    $featuredAirports = array_slice($airports, 0, 4);
?>
<html>
    <head>
        <title>Flight Search</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <!-- Navbar -->
            <?php include __DIR__ . '/../../components/nav.php'; ?>

        <div class="flex">

        <?php include __DIR__ . '/../../components/sidebar.php'; ?>

        <main class="flex-1 min-h-screen bg-gray-900">
            <!-- Hero section -->
            <section class="p-6">
                <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS✈️</p>
                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Flight Search</h1>
                    <p class="text-lg text-gray-300 max-w-2xl">Search available flights and book your next trip with BDPA Airports.</p>
                </div>
            </section>

            <!-- Search form -->
            <section class="px-6">
                <form action="./flightResults.php" method="GET">
                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                        <div class="flex flex-col lg:flex-row gap-4">
                            <!-- Destination input -->
                            <div class="flex-1 relative">
                                <input type="text" name="destination" placeholder="Enter destination" class="w-full h-12 border border-gray-600 rounded-lg pl-10 pr-12 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>

                                <!-- Search help tooltip -->
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 group cursor-help">
                                    <span class="text-gray-400 hover:text-white">ⓘ</span>
                                    <div class="absolute right-0 mt-2 w-72 bg-gray-900 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition duration-200 z-50">
                                        Search by:
                                        <ul class="list-disc pl-5 mt-2 space-y-1">
                                            <li>Airport code (NOV, TWN, DCA)</li>
                                            <li>Airport name</li>
                                            <li>City (Chicago, Dulles)</li>
                                            <li>State abbreviation (MN, IL, NC)</li>
                                            <li>Country abbreviation (USA)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Date input -->
                            <div class="flex-1">
                                <input type="date" name="date" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                            </div>

                            <!-- Submit button -->
                            <button type="submit" class="h-12 px-8 bg-blue-600 text-white rounded-lg font-medium transition duration-200 hover:bg-blue-700 hover:shadow-md active:scale-95">
                                Search Flights
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Featured airports -->
            <section class="p-6 pt-4">
                <h2 class="text-2xl font-bold text-white mb-4">Popular Destinations📍</h2>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    <?php foreach ($featuredAirports as $airport): ?>
                        <div class="bg-gray-800 border border-gray-700 rounded-lg overflow-hidden transition duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-blue-500">
                            <div class="p-6 flex flex-col h-full">
                                <!-- Airport name + code -->
                                <h3 class="font-bold text-xl text-white leading-tight">
                                    <?= htmlspecialchars(str_replace('BDPA ', '', $airport['name'])) ?>
                                    -
                                    <span class="text-xl font-bold text-blue-400 tracking-wide">
                                        <?= htmlspecialchars($airport['shortName']) ?>
                                    </span>
                                </h3>

                                <!-- Location -->
                                <p class="text-gray-400 mt-2 mb-4">
                                    <?= htmlspecialchars($airport['city']) ?>,
                                    <?= htmlspecialchars($airport['state']) ?>
                                </p>

                                <!-- Search button -->
                                <div class="mt-auto">
                                    <a href="./flightResults.php?destination=<?= urlencode($airport['shortName']) ?>" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg transition duration-200">Search Flights →</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        
    </body>
</html>