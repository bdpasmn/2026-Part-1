<?php
session_start();

require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../api/key.php';

set_time_limit(1800);

$api = new AirportsAPI(AIRPORTS_API_KEY);

// ---------------- INPUTS ----------------
$type   = $_GET['type'] ?? "all";
$search = trim($_GET['search'] ?? "");
$sort   = $_GET['sort'] ?? 'flightNumber';
$page   = max(1, (int)($_GET['page'] ?? 1));

$perPage = 20;

// ---------------- SAFE API CURSOR FETCH ----------------
function fetchAllFlights(AirportsAPI $api) {

    $after = null;
    $all = [];

    while (true) {

        $response = $api->getAllFlights($after);

        if (!is_array($response)) {
            break;
        }

        $flights = $response['flights'] ?? [];
        $after   = $response['nextCursor'] ?? null;

        if (!is_array($flights)) {
            $flights = [];
        }

        foreach ($flights as $f) {
            $all[] = $f;
        }

        if (!$after) {
            break;
        }
    }

    return $all;
}
// ---------------- LOAD ALL FLIGHTS ----------------
$flights = fetchAllFlights($api);

$flights = array_map(function ($f) {

    $f['airline'] = $f['airline'] ?? 'Unknown';
    $f['status']  = $f['status'] ?? 'Unknown';

    $f['city'] = (($f['type'] ?? '') === 'arrival')
        ? ($f['comingFrom'] ?? 'ZZZ')
        : ($f['landingAt'] ?? 'ZZZ');

    return $f;

}, $flights);
// ---------------- REMOVE PAST FLIGHTS ----------------
$flights = array_values(array_filter($flights, function ($f) {
    return strtolower($f['status'] ?? '') !== 'past';
}));
// ---------------- TYPE FILTER ----------------
if ($type !== 'all') {
    $flights = array_values(array_filter($flights, function ($f) use ($type) {
        return ($f['type'] ?? '') === $type;
    }));
}
// ---------------- TIME HELPER ----------------
$getTime = function ($f) {

    $time = $f['departFromSender']
        ?? $f['arriveAtReceiver']
        ?? $f['departureTime']
        ?? $f['arrivalTime']
        ?? null;

    if (!$time) return 0;

    if (!is_numeric($time)) {
        $time = strtotime($time);
    }

    if (is_numeric($time) && $time > 1000000000000) {
        $time = (int)($time / 1000);
    }

    return (int)$time;
};

// ---------------- DATE HELPER ----------------
$getDate = function ($f) use ($getTime) {

    $time = $getTime($f);

    if (!$time) return "N/A";

    return date("M d, Y g:i A", $time);
};

// ---------------- SORT ----------------
usort($flights, function ($a, $b) use ($sort, $getTime) {

    switch ($sort) {

        // Airline
        case 'airline_asc':
            return strcasecmp($a['airline'] ?? '', $b['airline'] ?? '');

        case 'airline_desc':
            return strcasecmp($b['airline'] ?? '', $a['airline'] ?? '');

        // Gate
        case 'gate_asc':
            return strcasecmp(
                $a['gate'] ?? '',
                $b['gate'] ?? ''
            );

        case 'gate_desc':
            return strcasecmp(
                $b['gate'] ?? '',
                $a['gate'] ?? ''
            );

        // Date
        case 'date_asc':
            return $getTime($a) <=> $getTime($b);

        case 'date_desc':
            return $getTime($b) <=> $getTime($a);

        // Existing
        case 'status':
            return strcasecmp($a['status'] ?? '', $b['status'] ?? '');

        case 'city':
            return strcasecmp(
                $a['city'] ?? 'ZZZ',
                $b['city'] ?? 'ZZZ'
            );

        case 'flightNumber':
            return strcasecmp(
                $a['flightNumber'] ?? '',
                $b['flightNumber'] ?? ''
            );

        default:
            return $getTime($b) <=> $getTime($a);
    }
});
// ---------------- ROUTE HELPER ----------------
$getRoute = function ($f) {

    if (($f['type'] ?? '') === 'arrival') {

        $from = $f['comingFrom'] ?? 'Unknown';
        $to   = $f['landingAt'] ?? 'Unknown';

        return trim("{$from} → {$to}");
    }

    if (($f['type'] ?? '') === 'departure') {

        $from = $f['landingAt'] ?? 'Unknown';
        $to   = $f['departingTo'] ?? 'Unknown';

        return trim("{$from} → {$to}");
    }

    return 'N/A';
};
// ---------------- PAGINATION ----------------
$totalFlights = count($flights);
$totalPages = max(1, ceil($totalFlights / $perPage));

$start = ($page - 1) * $perPage;
$pageFlights = array_slice($flights, $start, $perPage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Flight Search Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-300 min-h-screen text-white">
<div class="w-full min-h-screen bg-gray-900">

    <!-- HEADER -->
    <header class="h-16 bg-gray-800 flex items-center px-8 border-b border-gray-700">
        <h1 class="text-white font-bold text-xl">BDPA Airports</h1>
    </header>

    <!-- HERO -->
    <section class="p-6">
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
            <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS</p>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Find Flights</h1>
            <p class="text-lg text-gray-300 max-w-2xl">
                Search available flights and book your next trip with BDPA Airports.
            </p>
        </div>
    </section>

    <!-- SEARCH -->
    <section class="px-6">
        <form method="GET" class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
            <div class="flex flex-col lg:flex-row gap-4">

                <select name="type" class="flex-1 h-12 bg-gray-700 border border-gray-600 rounded-lg px-4">
                    <option value="all" <?= $type=="all"?"selected":"" ?>>All</option>
                    <option value="arrival" <?= $type=="arrival"?"selected":"" ?>>Arrivals</option>
                    <option value="departure" <?= $type=="departure"?"selected":"" ?>>Departures</option>
                </select>

                <button class="h-12 px-8 bg-blue-600 rounded-lg font-medium hover:bg-blue-700">
                    Search
                </button>
                <!-- SORT BUTTONS -->
                    <div class="mt-4 flex flex-wrap gap-3">

                        <!-- Airline -->
                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=airline_asc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Airline A-Z
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=airline_desc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Airline Z-A
                        </a>

                        <!-- Gate -->
                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=gate_asc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Gate A-Z
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=gate_desc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Gate Z-A
                        </a>

                        <!-- Date -->
                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=date_asc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Oldest First
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>&sort=date_desc"
                        class="px-4 py-2 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600">
                            Newest First
                        </a>

                    </div>
            </div>
        </form>
    </section>

    <!-- FLIGHTS -->
    <section class="p-6">
        <h2 class="text-2xl font-bold mb-4">Available Flights</h2>

        <div class="space-y-4">
            <?php foreach ($pageFlights as $f): ?>
                <div class="bg-gray-800 border border-gray-700 rounded-lg p-5">
                    <div class="flex flex-col lg:flex-row lg:justify-between gap-6">

                        <div>
                            <h3 class="font-bold text-lg">
                                <?= $f['flightNumber'] ?? "N/A" ?>
                            </h3>
                            <p class="text-gray-400">
                                <?= $f['airline'] ?? "Unknown Airline" ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-white font-medium">Date</p>
                            <p class="text-gray-400">
                                <?= $getDate($f) ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-white font-medium">Route</p>
                            <p class="text-gray-400">
                                <?= htmlspecialchars($getRoute($f)) ?>
                            </p>
                        </div>
                        <div>
                        <p class="text-white font-medium">Arrival/Departure</p>
                            <p class="text-gray-400">
                                <?= $f['type'] ?? "N/A" ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-white font-medium">Status</p>
                            <p class="text-gray-400">
                                <?= $f['status'] ?? "N/A" ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-white font-medium">Gate</p>
                            <p class="text-gray-400">
                                <?= ($f['type'] ?? '') === 'departure'
                                    ? ($f['gate'] ?? '—')
                                    : '—' ?>
                            </p>
                        </div>

                        <div>
                            <?php if (($f['type'] ?? '') === 'departure'): ?>
                                <button href="" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-700">
                                    Book
                                </button>
                            <?php else: ?>
                                <p class="text-gray-500 text-sm">View only</p>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- PAGINATION -->
    <section class="p-6">
        <div class="flex justify-end items-center gap-4">

            <?php if ($page > 1): ?>
                <a class="px-4 py-2 bg-gray-700 rounded"
                   href="?page=<?= $page - 1 ?>&type=<?= urlencode($type) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                    Previous
                </a>
            <?php endif; ?>

            <div class="px-4 py-2 bg-gray-800 border border-gray-700 rounded">
                Page <?= $page ?> of <?= $totalPages ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a class="px-4 py-2 bg-blue-600 rounded"
                   href="?page=<?= $page + 1 ?>&type=<?= urlencode($type) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                    Next
                </a>
            <?php endif; ?>

        </div>
    </section>

</div>
</body>
</html>