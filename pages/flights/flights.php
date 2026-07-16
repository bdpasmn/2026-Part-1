<?php
// Initialize session
session_start();
// Handle AJAX requests for real-time flight updates
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    ob_start();

    try {
        header('Content-Type: application/json');

        require_once __DIR__ . '/../../api/api.php';
        require_once __DIR__ . '/../../api/key.php';

        $api = new AirportsAPI(AIRPORTS_API_KEY);

        // Use cursor from request if present
        $cursor = $_GET['cursor'] ?? null;

        $response = $api->getFlights(after: $cursor);

        $flights = [];

        // Extract relevant flight fields for response
        foreach (($response['flights'] ?? []) as $f) {
            $flights[] = [
                "flight_id" => $f['flight_id'] ?? null,
                "status"    => $f['status'] ?? '',
                "gate"      => $f['gate'] ?? 'TBD'
            ];
        }

        ob_end_clean();

        echo json_encode([
            "flights" => $flights,
        ]);

        exit;

    } catch (Exception $e) {

        ob_end_clean();
        http_response_code(500);

        echo json_encode([
            "error" => $e->getMessage(),
            "flights" => []
        ]);

        exit;
    }
}

// Page load: fetch and initialize data
require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../api/key.php';
require_once __DIR__ . '/../../database/db.php';

set_time_limit(1800);

$api = new AirportsAPI(AIRPORTS_API_KEY);

// Get airports for lookup table
$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];

$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;
}

// Get filter/sort parameters from query string
$statusTab = $_GET['status'] ?? 'all';   
$mode   = $_GET['mode'] ?? 'flightNumber';
$search = trim($_GET['search'] ?? '');

$sort = 'time_asc';
$role = $_SESSION['role'] ?? null;

// Load user's saved sort preference from database
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $preferences = json_decode($dbUser['sort_preference'] ?? '{}', true);

    $sort = $_GET['sort']
        ?? ($preferences['flight_sort'] ?? 'time_asc');
} else {
    $sort = $_GET['sort'] ?? 'time_asc';
}

$page = max(1, (int)($_GET['page'] ?? 1));

$perPage = 10;

// Get cursor from query string for pagination
$cursor = $_GET['cursor'] ?? null;
$pageNum = max(1, (int)($_GET['page'] ?? 1));

// Build query filters
$match = [];

if ($statusTab !== 'all') {
    $match['status'] = $statusTab;
}

// Filter by date if searching by time (server-side via API)
if ($mode === 'time' && $search !== '') {
    $timestamp = strtotime($search);
    if ($timestamp !== false) {
        $start = strtotime(date('Y-m-d 00:00:00', $timestamp)) * 1000;
        $end   = strtotime(date('Y-m-d 23:59:59', $timestamp)) * 1000;
        $match['departFromSender'] = [
            '$gte' => $start,
            '$lte' => $end
        ];
    }
}

// Fetch flights from API
$flights = [];
$nextCursor = null;
$after = $cursor;

if ($statusTab === 'all' && empty($match)) {
    // Get all flights (no filters)
    $response = $api->getFlights(after: $after);

    if (is_array($response)) {
        $flights = $response['flights'] ?? [];
        if (count($flights) > 0) {
            $nextCursor = $flights[count($flights) - 1]['flight_id'] ?? null;
        }
    } else {
        $flights = [];
        $nextCursor = null;
    }

} else {
    // Apply filters (status and date range via API)
    $apiResult = $api->getFlights(match: !empty($match) ? $match : null, after: $after);

    $flights = $apiResult['flights'] ?? [];
    if (count($flights) > 0) {
        $nextCursor = $flights[count($flights) - 1]['flight_id'] ?? null;
    }
}

// Whitelist allowed flight statuses
$allowedStatuses = [
    'scheduled',
    'cancelled',
    'delayed',
    'on time',
    'landed',
    'arrived',
    'boarding',
    'departed'
];

// Filter to only valid statuses
$flights = array_values(array_filter($flights, function ($f) use ($allowedStatuses) {

    $status = strtolower(trim($f['status'] ?? ''));
    $status = preg_replace('/\s+/', ' ', $status);

    return in_array($status, $allowedStatuses, true);
}));

// Helper function to build query strings with overrides
function buildQuery($overrides = []) {
    return http_build_query(array_merge([
        'status' => $GLOBALS['statusTab'],
        'mode'   => $GLOBALS['mode'],
        'search' => $GLOBALS['search'],
        'sort'   => $GLOBALS['sort'],
        'page'   => 1
    ], $overrides));
}

// Extract time from flight data (handles multiple time fields)
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

// Format time as readable date string
$getDate = function ($f) use ($getTime) {

    $time = $getTime($f);

    if (!$time) return "N/A";

    return date("M d, Y g:i A", $time);
};

// Filter by flight type (arrival/departure)
if (in_array($mode, ['arrival', 'departure'], true)) {

    $flights = array_values(array_filter(
        $flights,
        function ($f) use ($mode) {
            return strtolower(trim($f['type'] ?? '')) === strtolower($mode);
        }
    ));

} 

// Apply search filter (client-side for all non-time modes)
if ($search !== '' && $mode !== 'time') {

    $searchLower = strtolower($search);

    $flights = array_values(array_filter(
        $flights,
        function ($f) use ($mode, $searchLower, $getTime, $getDate, $airportLookup) {

            $flightNumber = strtolower($f['flightNumber'] ?? '');
            $airline      = strtolower($f['airline'] ?? '');

            $comingFrom   = strtolower($f['comingFrom'] ?? '');
            $landingAt    = strtolower($f['landingAt'] ?? '');
            $departingTo  = strtolower($f['departingTo'] ?? '');
            
            // Build searchable city list from airport codes
            $cities = [];
            
            foreach ([$comingFrom, $landingAt, $departingTo] as $code) {
            
                if ($code && isset($airportLookup[$code])) {
            
                    $city = $airportLookup[$code]['city']
                         ?? $airportLookup[$code]['cityName']
                         ?? $airportLookup[$code]['location']
                         ?? '';
            
                    if ($city !== '') {
                        $cities[] = strtolower($city);
                    }
                }
            }
            
            $cityString = implode(' ', $cities);

            // Search based on selected mode
            switch ($mode) {

                case 'flightNumber':
                    return str_contains($flightNumber, $searchLower);

                case 'airline':
                    return str_contains($airline, $searchLower);

                case 'city':
                    return str_contains($cityString, $searchLower);

                default:
                    return str_contains($flightNumber, $searchLower) 
                        || str_contains($airline, $searchLower)
                        || str_contains($cityString, $searchLower);
            }

        }
    ));
}

// Sort flights
usort($flights, function ($a, $b) use ($sort, $getTime) {
    switch ($sort) {
        case 'time_asc':
            return $getTime($a) - $getTime($b);
        case 'time_desc':
            return $getTime($b) - $getTime($a);
        case 'flight_asc':
            return strcmp($a['flightNumber'] ?? '', $b['flightNumber'] ?? '');
        case 'flight_desc':
            return strcmp($b['flightNumber'] ?? '', $a['flightNumber'] ?? '');
        case 'airline_asc':
            return strcmp($a['airline'] ?? '', $b['airline'] ?? '');
        case 'airline_desc':
            return strcmp($b['airline'] ?? '', $a['airline'] ?? '');
        default:
            return $getTime($a) - $getTime($b);
    }
});

// Paginate results
$totalFlights = count($flights);
$perPage = 10;

$offset = 0;
$paginatedFlights = array_slice($flights, $offset, $perPage);

// Determine if there are more results
$hasNextPage = count($flights) > $perPage || $nextCursor !== null;

// Check user role for booking eligibility
$now = time();
$canBook = in_array(strtolower($role), ['user', 'staff']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Flights</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 min-h-screen text-white">
   <?php include __DIR__ . '/../../components/nav.php'; ?>
<div class="w-full min-h-screen bg-gray-900">

    <!-- Hero Section -->
    <section class="p-6">
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
            <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS✈️</p>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Browse Flights</h1>
            <p class="text-lg text-gray-300 max-w-2xl">
                Explore recent and upcoming flights.
            </p>
        </div>
    </section>

    <!-- Status Filter Tabs -->
    <section class="px-6 pt-0">
        <div class="border border-gray-700 rounded-t-xl bg-gray-900 shadow-md overflow-hidden">
            <div class="flex overflow-x-auto md:overflow-visible whitespace-nowrap md:flex-wrap">
                <?php
                $tabs = [
                    'all' => 'All Flights',
                    'scheduled' => 'Scheduled',
                    'cancelled' => 'Cancelled',
                    'delayed'   => 'Delayed',
                    'on time'   => 'On Time',
                    'landed'    => 'Landed',
                    'arrived'   => 'Arrived',
                    'boarding'  => 'Boarding',
                    'departed'  => 'Departed'
                ];
                ?>

                <?php foreach ($tabs as $key => $label): ?>
                    <?php $active = ($statusTab === $key); ?>
                    <a href="?status=<?= urlencode($key) ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>"
                       class="
                       flex-shrink-0
                       relative px-6 py-3 text-sm font-medium whitespace-nowrap
                            border-r border-gray-700
                            transition duration-200 hover:bg-gray-800 hover:text-white
                            flex items-center
                            <?= $active
                                ? 'bg-gray-800 text-white'
                                : 'bg-gray-900 text-gray-400 hover:bg-gray-800 hover:text-white' ?>
                       ">
                        <?= htmlspecialchars($label) ?>
                        <?php if ($active): ?>
                            <span class="absolute left-0 right-0 bottom-0 h-[3px] bg-blue-500"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Search & Filter Form -->
    <section class="px-6">
        <form method="GET" class="bg-gray-800 border border-gray-700 rounded-lg p-4 shadow-md">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusTab) ?>">
            <input type="hidden" name="page" value="1">

            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <input
                    type="text"
                    name="search"
                    placeholder="Search..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="w-full lg:w-80 h-12 border border-gray-600 rounded-lg px-4 bg-gray-700 text-white placeholder-gray-400
                           transition duration-200
                           focus:outline-none focus:ring-1 focus:ring-white focus:border-white"
                >

                <select name="mode"
                    class="h-12 w-full lg:w-64 bg-gray-700 border border-gray-600 rounded-lg px-4 text-white
                           transition duration-200
                           focus:outline-none focus:ring-1 focus:ring-white focus:border-white"
                >
                    <option value="flightNumber" <?= $mode === 'flightNumber' ? 'selected' : '' ?>>Flight Number</option>
                    <option value="airline" <?= $mode === 'airline' ? 'selected' : '' ?>>Airline</option>
                    <option value="city" <?= $mode === 'city' ? 'selected' : '' ?>>City</option>
                    <option value="time" <?= $mode === 'time' ? 'selected' : '' ?>>Arrival / Departure Time</option>
                </select>

                <select name="sort" class="h-12 w-full sm:w-64 max-w-full bg-gray-700 border border-gray-600 rounded-lg px-4 text-white transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
                    <option value="time_asc" <?= $sort == 'time_asc' ? 'selected' : '' ?>>Time ↑ (Earliest)</option>
                    <option value="time_desc" <?= $sort == 'time_desc' ? 'selected' : '' ?>>Time ↓ (Latest)</option>
                    <option value="flight_asc" <?= $sort == 'flight_asc' ? 'selected' : '' ?>>Flight A → Z</option>
                    <option value="flight_desc" <?= $sort == 'flight_desc' ? 'selected' : '' ?>>Flight Z → A</option>
                    <option value="airline_asc" <?= $sort == 'airline_asc' ? 'selected' : '' ?>>Airline A → Z</option>
                    <option value="airline_desc" <?= $sort == 'airline_desc' ? 'selected' : '' ?>>Airline Z → A</option>
                </select>

                <button type="submit" class="h-12 px-6 w-full lg:w-auto bg-blue-600 text-white rounded-lg font-medium
                           transition duration-200
                           hover:bg-blue-700 active:scale-95"
                >
                    Search & Filter
                </button>
            </div>
        </form>
    </section>

    <!-- Flight Results -->
    <section class="p-6 pt-2 pb-0">
        <?php if ($totalFlights === 0): ?>
            <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
                <h3 class="text-lg font-semibold text-white mb-2">
                    No Flights Found
                </h3>
                <p class="text-gray-300">
                    Try adjusting your filters or check back later for new flights.
                </p>
            </div>
        <?php else: ?>

            <!-- Flight Cards -->
            <div id="flightContainer" class="space-y-4 mt-3">
                <?php foreach ($paginatedFlights as $f): ?>
                    <?php
                        // Determine if user can book this flight
                        $flightTime = $getTime($f);
                        $canBook = in_array(strtolower($role), ['user', 'staff'])
                            && ($f['type'] ?? '') == 'departure'
                            && ($f['landingAt'] ?? '') == 'SMN'
                            && $flightTime > ($now + 129600); // 36 hours in seconds
                    ?>

                    <div class="bg-gray-800 border border-gray-700 rounded-xl p-5
                                hover:shadow-lg hover:-translate-y-1 hover:border-blue-500
                                transition duration-300">

                        <div class="grid grid-cols-1 md:grid-cols-[140px_200px_180px_180px_120px_160px_180px_1fr] gap-5 items-start md:items-center">
                            <!-- Flight Number & Airline -->
                            <div>
                                <h3 class="font-bold text-lg leading-tight">
                                    <?= htmlspecialchars($f['flightNumber'] ?? 'N/A') ?>
                                </h3>
                                <p class="text-gray-400 text-sm">
                                    <?= htmlspecialchars($f['airline'] ?? 'Unknown Airline') ?>
                                </p>
                            </div>

                            <!-- Scheduled Time -->
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Scheduled</p>
                                <p class="text-white text-sm whitespace-nowrap">
                                    <?= $getDate($f) ?>
                                </p>
                            </div>

                            <!-- Origin -->
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400 uppercase tracking-wide">
                                    <?= ($f['type'] ?? '') === 'arrival' ? 'Coming From' : 'Departing From' ?>
                                </p>

                                <p class="text-gray-200 text-sm truncate">
                                    <?php
                                        if (($f['type'] ?? '') == 'arrival') {
                                            $code = $f['comingFrom'] ?? '';
                                        } else {
                                            $code = $f['landingAt'] ?? '';
                                        }

                                        $airport = $airportLookup[strtolower($code)] ?? null;
                                        $city = $airport['city'] ?? 'Unknown';

                                        echo htmlspecialchars($city . " (" . strtoupper($code) . ")");
                                    ?>
                                </p>
                            </div>

                            <!-- Destination -->
                            <div class="min-w-0">
                                <p class="text-xs text-gray-400 uppercase tracking-wide">
                                    <?= ($f['type'] ?? '') == 'arrival' ? 'Arriving At' : 'Departing To' ?>
                                </p>

                                <p class="text-gray-200 text-sm truncate">
                                    <?= ($f['type'] ?? '') == 'arrival'
                                        ? htmlspecialchars($f['landingAt'] ?? '—')
                                        : htmlspecialchars($f['departingTo'] ?? '—') ?>
                                </p>
                            </div>

                            <!-- Type -->
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Type</p>
                                <p class="text-sm text-gray-200 capitalize">
                                    <?= htmlspecialchars($f['type'] ?? 'N/A') ?>
                                    <?php 
                                        if ($f['type'] == 'arrival') {
                                            echo '🛬';
                                        } else {
                                            echo '🛫';
                                        }
                                    ?>
                                </p>
                            </div>

                            <!-- Status (live-updated) -->
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wide">Status</p>
                                <p class="text-sm text-gray-200 capitalize"
                                   data-flight-id="<?= htmlspecialchars($f['flight_id'] ?? '') ?>"
                                   data-field="status">
                                    <?= htmlspecialchars($f['status'] ?? 'N/A') ?>
                                </p>
                            </div>

                            <!-- Gate (live-updated) -->
                            <div>
                                <?php if (($f['type'] ?? '') == 'departure'): ?>
                                    <p class="text-xs text-gray-400 uppercase tracking-wide">Gate</p>
                                    <p class="text-sm text-gray-200 font-semibold"
                                       data-flight-id="<?= htmlspecialchars($f['flight_id'] ?? '') ?>"
                                       data-field="gate">
                                        <?= isset($f['gate']) ? strtoupper(htmlspecialchars($f['gate'])) : 'TBD' ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Action Button -->
                            <div class="text-right justify-self-end">
                                <?php if (in_array(strtolower($role), ['admin', 'root'])): ?>
                                    <!-- Admin: Copy Flight ID -->
                                    <div class="flex flex-col items-end gap-2">
                                        <button onclick="copyFlightId(this, '<?= htmlspecialchars($f['flight_id'] ?? '', ENT_QUOTES) ?>')" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm transition duration-200">
                                            Copy Flight ID
                                        </button>
                                    </div>
                                <?php elseif ($canBook): ?>
                                    <!-- User: Book Button -->
                                    <a href="../booking/booking.php?flight_id=<?= urlencode($f['flight_id'] ?? '') ?>"
                                       class="bg-blue-600 px-4 py-2 rounded-lg text-sm
                                              hover:bg-blue-700 active:scale-95 transition duration-200">
                                        Book
                                    </a>
                                <?php else: ?>
                                    <!-- View Only with Reason Tooltip -->
                                    <div class="relative group inline-flex items-center gap-1">
                                        <span class="text-gray-400 text-sm">
                                            View Only
                                        </span>
                                        <span class="text-blue-400 text-sm cursor-help">
                                            ⓘ
                                        </span>

                                        <?php
                                            $reason = '';

                                            if (($f['type'] ?? '') !== 'departure') {
                                                $reason = 'Arrivals cannot be booked.';
                                            } elseif (($f['landingAt'] ?? '') !== 'SMN') {
                                                $reason = 'This flight does not depart from SMN.';
                                            } elseif ($flightTime <= $now) {
                                                $reason = 'This flight has already departed.';
                                            } elseif ($flightTime <= ($now + 129600)) { // 36 hours in seconds
                                                $reason = 'Booking closes 36 hours before departure.';
                                            }
                                        ?>

                                        <div class="absolute right-0 top-full mt-2
                                                    opacity-0 invisible group-hover:opacity-100 group-hover:visible
                                                    transition duration-200 z-50">
                                            <div class="bg-gray-900 border border-gray-700 text-xs text-gray-300 px-3 py-2 rounded shadow-lg whitespace-nowrap">
                                                <?= htmlspecialchars($reason) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Pagination -->
    <?php if ($totalFlights > 0): ?>
        <section class="p-6">
            <div class="flex justify-between items-center gap-4 flex-wrap">
                <div class="text-sm text-gray-400">
                    Page <span class="font-semibold text-white"><?= $pageNum ?></span>
                </div>

                <div class="flex items-center gap-4">
                    <?php if ($cursor): ?>
                        <a class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition duration-200"
                           href="?status=<?= urlencode($statusTab) ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>&page=1">
                            ← First Page
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-800 text-gray-600 rounded cursor-not-allowed">
                            ← First Page
                        </span>
                    <?php endif; ?>

                    <?php if ($nextCursor !== null): ?>
                        <a class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition duration-200"
                           href="?cursor=<?= urlencode($nextCursor) ?>&status=<?= urlencode($statusTab) ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>&page=<?= $pageNum + 1 ?>">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 bg-gray-800 text-gray-600 rounded cursor-not-allowed">
                            Next →
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
    // Poll for real-time status/gate updates every 13 seconds
    async function updateFlights() {
        try {
            const url = window.location.pathname + window.location.search + (window.location.search ? '&' : '?') + 'ajax=1';
            const res = await fetch(url);
            
            if (!res.ok) {
                console.error(`HTTP error! status: ${res.status}`);
                return;
            }
            
            const text = await res.text();
            
            if (!text || text.trim() === '') {
                console.warn("Empty response from server");
                return;
            }
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                console.error("JSON parse error:", parseErr, "Response:", text.substring(0, 200));
                return;
            }

            if (!data.flights || !Array.isArray(data.flights)) {
                console.warn("No flights array in response");
                return;
            }

            // Update each flight's status/gate on page
            data.flights.forEach(f => {
                if (!f.flight_id) return;

                // Update status field
                const statusEl = document.querySelector(
                    `[data-flight-id="${f.flight_id}"][data-field="status"]`
                );

                if (statusEl) {
                    const newStatus = (f.status || 'N/A').toLowerCase();

                    if (statusEl.textContent.trim().toLowerCase() !== newStatus) {
                        statusEl.textContent = newStatus;
                        statusEl.classList.add("text-yellow-400");

                        setTimeout(() => {
                            statusEl.classList.remove("text-yellow-400");
                        }, 800);
                    }
                }

                // Update gate field
                const gateEl = document.querySelector(
                    `[data-flight-id="${f.flight_id}"][data-field="gate"]`
                );

                if (gateEl) {
                    const newGate = f.gate ? f.gate.toUpperCase() : '—';

                    if (gateEl.textContent.trim() !== newGate) {
                        gateEl.textContent = newGate;
                        gateEl.classList.add("text-blue-400");

                        setTimeout(() => {
                            gateEl.classList.remove("text-blue-400");
                        }, 800);
                    }
                }
            });

        } catch (err) {
            console.error("AJAX update failed:", err.message);
        }
    }

    // Copy flight ID to clipboard with feedback
    function copyFlightId(button, flightId) {
        navigator.clipboard.writeText(flightId).then(() => {
            const originalText = button.textContent;
            button.textContent = "Copied!";
            button.classList.add("bg-green-600");

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove("bg-green-600");
            }, 1500);

        }).catch(() => {
            button.textContent = "Failed";

            setTimeout(() => {
                button.textContent = "Copy Flight ID";
            }, 1500);
        });
    }
    
    // Initial update and periodic refresh
    updateFlights();
    setInterval(updateFlights, 13000);
</script>
</body>
</html>