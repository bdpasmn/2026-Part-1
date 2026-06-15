<?php
session_start();

require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../api/key.php';
include "./components/nav.php";
set_time_limit(1800);

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];

$airportLookup = [];

foreach ($airports as $airport) {
    $airportLookup[$airport['shortName']] = $airport;
}
// ---------------- INPUTS ----------------
$mode   = $_GET['mode'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$sort      = $_GET['sort'] ?? 'flightNumber';
$page      = max(1, (int)($_GET['page'] ?? 1));

if ($mode === 'all') {
    // no filtering
}

$perPage = 25;

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

$flights = array_map(function ($f) use ($airportLookup) {

    $f['airline'] = $f['airline'] ?? 'Unknown';
    $f['status']  = $f['status'] ?? 'Unknown';

    $airportCode = (($f['type'] ?? '') === 'arrival')
        ? ($f['comingFrom'] ?? 'ZZZ')
        : ($f['landingAt'] ?? 'ZZZ');

    $f['airportCode'] = $airportCode;
    $f['city'] = $airportLookup[$airportCode]['city'] ?? 'Unknown';

    return $f;

}, $flights);
//----------------- REMOVE PAST FLIGHTS ------------

$flights = array_values(array_filter($flights, function ($f) {

    $status = strtolower(trim($f['status'] ?? ''));

    return $status !== 'past';
}));
// ---------------- DROPDOWN FILTER ----------------

if (in_array($mode, ['arrival', 'departure'], true)) {

    $flights = array_values(array_filter(
        $flights,
        function ($f) use ($mode) {
            return strtolower(trim($f['type'] ?? '')) === strtolower($mode);
        }
    ));

} elseif ($search !== '' && $mode !== 'all') {

    $searchLower = strtolower($search);

    $fieldMap = [
        'flightNumber' => 'flightNumber',
        'airline'      => 'airline',
        'status'       => 'status',
        'city'         => 'city',
        'landingAt'    => 'landingAt'
    ];

    if (isset($fieldMap[$mode])) {

        $field = $fieldMap[$mode];

        $flights = array_values(array_filter(
            $flights,
            function ($f) use ($field, $searchLower) {

                $value = strtolower(
                    trim((string)($f[$field] ?? ''))
                );

                return str_contains($value, $searchLower);
            }
        ));
    }
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
                htmlspecialchars($a['city'] ?? 'ZZZ'),
                htmlspecialchars($b['city'] ?? 'ZZZ')
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
            <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap">
                <input
                    type="text"
                    name="search"
                    placeholder="Search..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-80 h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white"
                >

                <select name="mode" class="h-12 bg-gray-700 border border-gray-600 rounded-lg px-4">

                    <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>
                        All Flights
                    </option>

                    <option value="arrival" <?= $mode === 'arrival' ? 'selected' : '' ?>>
                        Arrivals
                    </option>

                    <option value="departure" <?= $mode === 'departure' ? 'selected' : '' ?>>
                        Departures
                    </option>

                    <option value="flightNumber" <?= $mode === 'flightNumber' ? 'selected' : '' ?>>
                        Flight Number
                    </option>

                    <option value="airline" <?= $mode === 'airline' ? 'selected' : '' ?>>
                        Airline
                    </option>

                    <option value="status" <?= $mode === 'status' ? 'selected' : '' ?>>
                        Status
                    </option>

                    <option value="city" <?= $mode === 'city' ? 'selected' : '' ?>>
                        City
                    </option>

                    <option value="landingAt" <?= $mode === 'landingAt' ? 'selected' : '' ?>>
                        Landing At
                    </option>

                </select>

                <button class="h-12 px-8 bg-blue-600 rounded-lg font-medium hover:bg-blue-700">
                    Search
                </button>
                <!-- SORT BUTTONS -->
                    <div class="flex items-center gap-3">

                        <!-- Airline -->
                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=airline_asc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
                            Airline A-Z
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=airline_desc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
                            Airline Z-A
                        </a>

                        <!-- Gate -->
                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=gate_asc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
                            Gate A-Z
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=gate_desc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
                            Gate Z-A
                        </a>

                        <!-- Date -->
                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=date_asc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
                            Oldest First
                        </a>

                        <a href="?search=<?= urlencode($search) ?>&mode=<?= urlencode($mode) ?>&sort=date_desc"
                        class="h-12 px-4 rounded-lg border border-gray-600 bg-gray-700 hover:bg-blue-600 inline-flex items-center justify-center">
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

            <?php
                $flightTime = $getTime($f);
                $now = time();

                $canBook = (
                    ($f['type'] ?? '') === 'departure'
                    && $flightTime > ($now + 86400)
                );
            ?>

            <div class="bg-gray-800 border border-gray-700 rounded-lg p-5">

                <div class="grid grid-cols-9 gap-6 items-start">

                    <!-- Flight -->
                    <div>
                        <h3 class="font-bold text-lg">
                            <?= htmlspecialchars($f['flightNumber'] ?? 'N/A') ?>
                        </h3>
                        <p class="text-gray-400">
                            <?= htmlspecialchars($f['airline'] ?? 'Unknown Airline') ?>
                        </p>
                    </div>

                    <!-- Date -->
                    <div>
                        <p class="font-medium text-white">Date</p>
                        <p class="text-gray-400 whitespace-nowrap">
                            <?= $getDate($f) ?>
                        </p>
                    </div>

                    <!-- City -->
                    <div>
                        <p class="font-medium text-white">City</p>
                        <p class="text-gray-400">
                            <?= htmlspecialchars($f['city'] ?? 'N/A') ?>
                        </p>
                    </div>

                    <!-- Column 4 -->
                    <div>
                        <p class="font-medium text-white">
                            <?= ($f['type'] ?? '') === 'arrival'
                                ? 'Coming From'
                                : 'Landing At' ?>
                        </p>

                        <p class="text-gray-400">
                            <?= ($f['type'] ?? '') === 'arrival'
                                ? htmlspecialchars($f['comingFrom'] ?? '—')
                                : htmlspecialchars($f['landingAt'] ?? '—') ?>
                        </p>
                    </div>

                    <!-- Column 5 -->
                    <div>
                        <p class="font-medium text-white">
                            <?= ($f['type'] ?? '') === 'arrival'
                                ? 'Landing At'
                                : 'Departing To' ?>
                        </p>

                        <p class="text-gray-400">
                            <?= ($f['type'] ?? '') === 'arrival'
                                ? htmlspecialchars($f['landingAt'] ?? '—')
                                : htmlspecialchars($f['departingTo'] ?? '—') ?>
                        </p>
                    </div>

                    <!-- Type -->
                    <div>
                        <p class="font-medium text-white">Arrival/Departure</p>
                        <p class="text-gray-400 capitalize">
                            <?= htmlspecialchars($f['type'] ?? 'N/A') ?>
                        </p>
                    </div>

                    <!-- Status -->
                    <div>
                        <p class="font-medium text-white">Status</p>
                        <p class="text-gray-400 capitalize">
                            <?= htmlspecialchars($f['status'] ?? 'N/A') ?>
                        </p>
                    </div>

                    <!-- Gate -->
                    <div>
                        <p class="font-medium text-white">Gate</p>
                        <p class="text-gray-400">
                            <?= ($f['type'] ?? '') === 'departure'
                                ? htmlspecialchars($f['gate'] ?? '—')
                                : '—' ?>
                        </p>
                    </div>

                    <!-- Action -->
                    <div class="text-right">
                        <?php if (($f['type'] ?? '') === 'departure'): ?>

                            <?php if ($canBook): ?>
                                <a
                                    href="book.php?flight=<?= urlencode($f['flightNumber'] ?? '') ?>"
                                    class="inline-block bg-blue-600 px-4 py-2 rounded hover:bg-blue-700"
                                >
                                    Book
                                </a>

                            <?php elseif ($flightTime > 0 && $flightTime <= $now): ?>
                                <p class="text-gray-500 text-sm">
                                    Flight Departed
                                </p>

                            <?php else: ?>
                                <p class="text-gray-500 text-sm">
                                    Booking Closed
                                </p>
                            <?php endif; ?>

                        <?php else: ?>
                            <p class="text-gray-500 text-sm">
                                View Only
                            </p>
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
                   href="?page=<?= $page - 1 ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                    Previous
                </a>
            <?php endif; ?>

            <div class="px-4 py-2 bg-gray-800 border border-gray-700 rounded">
                Page <?= $page ?> of <?= $totalPages ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a class="px-4 py-2 bg-blue-600 rounded"
                   href="?page=<?= $page + 1 ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                    Next
                </a>
            <?php endif; ?>

        </div>
    </section>

</div>
</body>
</html>