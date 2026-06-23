<?php
    session_start();

    require_once "../../api/api.php";
    require_once "../../api/key.php";

    $api = new AirportsAPI(AIRPORTS_API_KEY);
    
    $role = $_SESSION['role'] ?? null;

    if ($role == 'Admin' || $role == 'Root') {
        
        if (in_array($role, ['Admin', 'Root'])) {
            header("Location: ../dashboard/{$role}/{$role}.php");
        }
        
        exit;
    }


    $destination = trim($_GET['destination'] ?? '');
    $date = $_GET['date'] ?? '';

    $dateMissing = empty(trim($date));

    if (!isset($_SESSION['airports'])) {
        $airportResult = $api->getAirports();
        $_SESSION['airports'] = $airportResult['airports'] ?? [];
    }

    $airports = $_SESSION['airports'];

    $match = [
        "type" => "departure",
        "landingAt" => "SMN"
    ];

    if (!empty($date)) {
        $start = strtotime($date . " 00:00:00") * 1000;
        $end   = strtotime($date . " 23:59:59") * 1000;

        $match["departFromSender"] = ['$gte' => $start, '$lte' => $end];
    }

    $apiResult = $api->searchFlights($match);
    $batch = $apiResult['flights'] ?? [];

    if (!empty($destination)) {
        $matchingCodes = [];
        $search = strtolower($destination);

        foreach ($airports as $airport) {
            $name = strtolower($airport['name'] ?? '');
            $shortName = strtolower($airport['shortName'] ?? '');
            $city = strtolower($airport['city'] ?? '');
            $state = strtolower($airport['state'] ?? '');
            $country = strtolower($airport['country'] ?? '');

            if (str_contains($name, $search) || str_contains($shortName, $search) || str_contains($city, $search) || str_contains($state, $search) || str_contains($country, $search)) {
                $matchingCodes[] = $airport['shortName'];
            }
        }

        $batch = array_filter($batch, function ($flight) use ($matchingCodes)
            {return in_array($flight['departingTo'] ?? '', $matchingCodes);}
        );

        $batch = array_values($batch);
    }
    
    $flightsBeforeBookingRules = count($batch);
    $now = round(microtime(true) * 1000);

    $twentyFourHours = 24 * 60 * 60 * 1000;
    $batch = array_filter($batch, function ($flight) use ($now, $twentyFourHours) 
        {
            if (($flight['status'] ?? '') !== 'scheduled') {
                return false;
            }

            $arrivalTime = $flight['arriveAtReceiver'] ?? 0;
            return $arrivalTime > ($now + $twentyFourHours);
        }
    );

    $batch = array_values($batch);
    $flightsAfterBookingRules = count($batch);

    $failedTimeRequirement = ($flightsBeforeBookingRules > 0 && $flightsAfterBookingRules == 0);

    usort($batch, function ($a, $b) {
        return ($a['departFromSender'] ?? 0) <=> ($b['departFromSender'] ?? 0);
    });

    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = 10;

    $totalFlights = count($batch);
    $noBookableFlights = ($totalFlights == 0);

    $totalPages = max(1, ceil($totalFlights / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    $flights = array_slice($batch, $offset, $perPage);
?>
<html>
    <head>
        <title>Flight Search Results</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 min-h-screen text-white">
        <div class="w-full min-h-screen bg-gray-900">
            <?php include "../../components/nav.php"; ?>

            <section class="p-6">
                <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
                    <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS✈️</p>

                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Flight Search Results</h1>
                    <p class="text-lg text-gray-300">Showing results for: 
                        <span class="text-blue-300">
                            <?= htmlspecialchars($destination ?: "All destinations") ?>
                        </span>
                    </p>
                    <p class="text-gray-400 mt-2">Date: <?= htmlspecialchars($date ?: "Any date") ?></p>
                </div>
            </section>

            <section class="px-6">
                <form action="./flightResults.php" method="GET">
                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
                        <div class="flex flex-col lg:flex-row gap-4">
                            <div class="flex-1 relative">
                                <input type="text" name="destination" value="<?= htmlspecialchars($destination) ?>" placeholder="Enter destination" class="w-full h-12 border border-gray-600 rounded-lg pl-10 pr-12 bg-gray-700 text-white placeholder-gray-400 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>

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

                            <div class="flex-1">
                                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="w-full h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                            </div>

                            <button type="submit" class="h-12 px-8 bg-blue-600 text-white rounded-lg hover:bg-blue-700 active:scale-95">Search Flights</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="p-6 pt-4">
                <h2 class="text-2xl font-bold mb-4">🛫 Available Flights</h2>
                
                <div class="space-y-4">
                    <?php if (empty($flights)): ?>
                        <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
                            <?php if ($dateMissing): ?>
                                Please select a date to search for flights.
                            <?php elseif ($failedTimeRequirement): ?>
                                Flights must be scheduled and depart more than 24 hours from now.
                            <?php else: ?>
                                No flights are available that meet your search requirements.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                        
                    <?php foreach ($flights as $flight): ?>
                        <?php $timestamp = $flight['departFromReceiver'] ?? null; ?>

                        <div class="bg-gray-800 border border-gray-700 rounded-lg p-5 hover:shadow-lg hover:-translate-y-1 hover:border-blue-500 transition duration-300">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                                <div>
                                    <h3 class="font-bold text-lg text-white"><?= htmlspecialchars($flight['airline'] ?? 'Unknown Airline') ?></h3>
                                    <p class="text-gray-400"><?= htmlspecialchars($flight['departingTo'] ?? '') ?> Airport</p>
                                </div>

                                <div>
                                    <p class="font-medium text-white">Flight #</p>
                                    <p class="text-gray-400"><?= htmlspecialchars($flight['flightNumber'] ?? '') ?></p>
                                </div>

                                <div>
                                    <p class="font-medium text-white">Departure</p>
                                    <p class="text-gray-400"><?= $timestamp ? date("D, M j Y g:i A", $timestamp / 1000) : "N/A" ?></p>
                                </div>

                                <div>
                                    <p class="font-medium text-white">Price</p>
                                    <p class="text-gray-400">$<?= htmlspecialchars($flight['seatPrice'] ?? '0') ?></p>
                                </div>

                                <a class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 active:scale-95" href="./booking.php?flight_id=<?= urlencode($flight['flight_id']) ?>">Book</a>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="flex justify-end mt-8">
                            <div class="flex items-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?destination=<?= urlencode($destination) ?>&date=<?= urlencode($date) ?>&page=<?= $page - 1 ?>" class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600">Previous</a>
                                <?php endif; ?>

                                <span class="px-4 py-2 bg-gray-800 border border-gray-700 rounded">
                                    Page <?= $page ?> of <?= $totalPages ?>
                                </span>

                                <?php if ($page < $totalPages): ?>
                                    <a href="?destination=<?= urlencode($destination) ?>&date=<?= urlencode($date) ?>&page=<?= $page + 1 ?>" class="px-4 py-2 bg-blue-600 rounded hover:bg-blue-700">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </body>
</html>