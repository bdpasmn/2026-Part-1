<?php
session_start();

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {

    require_once __DIR__ . '/../../api/api.php';
    require_once __DIR__ . '/../../api/key.php';

    $api = new AirportsAPI(AIRPORTS_API_KEY);

    function fetchAllFlights(AirportsAPI $api) {
        $after = null;
        $all = [];

        while (true) {
            $response = $api->getAllFlights($after);
            if (!is_array($response)) break;

            foreach (($response['flights'] ?? []) as $f) {
                $all[] = [
                    "flight_id" => $f['flight_id'] ?? null,
                    "status" => $f['status'] ?? '',
                    "gate" => $f['gate'] ?? ''
                ];
            }

            $after = $response['nextCursor'] ?? null;
            if (!$after) break;
        }

        return $all;
    }

    header('Content-Type: application/json');

    echo json_encode([
        "flights" => fetchAllFlights($api)
    ]);

    exit;
}

require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../api/key.php';

include __DIR__ . '/../../components/nav.php';

set_time_limit(1800);

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];

$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;}

$statusTab = $_GET['status'] ?? 'all';   
$mode   = $_GET['mode'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sort = 'time_asc';

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


$match = [];

if ($statusTab !== 'all') {
    $match['status'] = $statusTab;
}

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


if ($statusTab === 'all' && empty($match['departFromSender'])) {

    $flights = fetchAllFlights($api);

} else {

    $apiResult = $api->searchFlights($match);

    $flights = $apiResult['flights'] ?? [];
}

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

$flights = array_values(array_filter($flights, function ($f) use ($allowedStatuses) {

    $status = strtolower(trim($f['status'] ?? ''));
    $status = preg_replace('/\s+/', ' ', $status);

    return in_array($status, $allowedStatuses, true);
}));
function buildQuery($overrides = []) {
    return http_build_query(array_merge([
        'status' => $GLOBALS['statusTab'],
        'mode'   => $GLOBALS['mode'],
        'search' => $GLOBALS['search'],
        'sort'   => $GLOBALS['sort'],
        'page'   => 1
    ], $overrides));
}
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

$getDate = function ($f) use ($getTime) {

    $time = $getTime($f);

    if (!$time) return "N/A";

    return date("M d, Y g:i A", $time);
};


if (in_array($mode, ['arrival', 'departure'], true)) {

    $flights = array_values(array_filter(
        $flights,
        function ($f) use ($mode) {
            return strtolower(trim($f['type'] ?? '')) === strtolower($mode);
        }
    ));

} 
if ($search !== '') {

    $searchLower = strtolower($search);

    $flights = array_values(array_filter(
        $flights,
        function ($f) use ($mode, $searchLower, $getTime, $getDate, $airportLookup) {

            $flightNumber = strtolower($f['flightNumber'] ?? '');
            $airline      = strtolower($f['airline'] ?? '');

            $comingFrom   = strtolower($f['comingFrom'] ?? '');
            $landingAt    = strtolower($f['landingAt'] ?? '');
            $departingTo  = strtolower($f['departingTo'] ?? '');
            
            $timeRaw       = (string)$getTime($f);
            $timeFormatted = strtolower($getDate($f));
            
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

            switch ($mode) {

                case 'flightNumber':
                    return str_contains($flightNumber, $searchLower);

                case 'airline':
                    return str_contains($airline, $searchLower);

                case 'airport':
                    return (
                        str_contains($comingFrom, $searchLower) ||
                        str_contains($landingAt, $searchLower) ||
                        str_contains($departingTo, $searchLower)
                    );

                case 'city':
                    return str_contains($cityString, $searchLower);
                    case 'time':

                        if (strtotime($searchLower) !== false) {
                            return true;
                        }
                    
                        return (
                            str_contains($timeRaw, $searchLower) ||
                            str_contains($timeFormatted, $searchLower)
                        );

                case 'all':
                default:
                    return (
                        str_contains($flightNumber, $searchLower) ||
                        str_contains($airline, $searchLower) ||
                        str_contains($comingFrom, $searchLower) ||
                        str_contains($landingAt, $searchLower) ||
                        str_contains($departingTo, $searchLower) ||
                        str_contains($cityString, $searchLower) ||
                        str_contains($timeRaw, $searchLower) ||
                        str_contains($timeFormatted, $searchLower)
                    );
            }
        }
    ));
}

usort($flights, function ($a, $b) use ($sort, $getTime) {

    switch ($sort) {

        case 'airline_asc':
            return strcasecmp($a['airline'] ?? '', $b['airline'] ?? '');

        case 'airline_desc':
            return strcasecmp($b['airline'] ?? '', $a['airline'] ?? '');

        case 'gate_asc':
            return strcasecmp($a['gate'] ?? '', $b['gate'] ?? '');

        case 'gate_desc':
            return strcasecmp($b['gate'] ?? '', $a['gate'] ?? '');

        case 'time_asc':
            return $getTime($a) <=> $getTime($b);

        case 'time_desc':
            return $getTime($b) <=> $getTime($a);

        case 'status':
            return strcasecmp($a['status'] ?? '', $b['status'] ?? '');

            case 'city':
                return strcasecmp(
                    $airportLookup[strtolower($a['comingFrom'] ?? '')]['city'] ?? '',
                    $airportLookup[strtolower($b['comingFrom'] ?? '')]['city'] ?? ''
                );

        case 'flightNumber':
            return strcasecmp($a['flightNumber'] ?? '', $b['flightNumber'] ?? '');

        default:
            return $getTime($b) <=> $getTime($a);
    }
});

$totalFlights = count($flights);
$totalPages = max(1, ceil($totalFlights / $perPage));

$page = min($page, $totalPages);

$start = ($page - 1) * $perPage;
$pageFlights = array_slice($flights, $start, $perPage);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Browse Flights</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 min-h-screen text-white">
<div class="w-full min-h-screen bg-gray-900">

    <section class="p-6">
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg">
            <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS✈️</p>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Browse BDPA Flights</h1>
            <p class="text-lg text-gray-300 max-w-2xl">
                Browse through recent and upcoming flights.
            </p>
        </div>
    </section>

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
            <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>All Fields</option>
            <option value="flightNumber" <?= $mode === 'flightNumber' ? 'selected' : '' ?>>Flight Number</option>
            <option value="airline" <?= $mode === 'airline' ? 'selected' : '' ?>>Airline</option>
            <option value="airport" <?= $mode === 'airport' ? 'selected' : '' ?>>Airport (Codes)</option>
            <option value="city" <?= $mode === 'city' ? 'selected' : '' ?>>City</option>
            <option value="time" <?= $mode === 'time' ? 'selected' : '' ?>>Arrival / Departure Time</option>
        </select>

        <button class="h-12 px-6 w-full lg:w-auto bg-blue-600 text-white rounded-lg font-medium
                   transition duration-200
                   hover:bg-blue-700 active:scale-95"
        >
            Search
        </button>

        <select name="sort" class="h-12 w-full sm:w-64 max-w-full bg-gray-700 border border-gray-600 rounded-lg px-4 text-white transition duration-200 focus:outline-none focus:ring-1 focus:ring-white focus:border-white">
            <option value="airline_asc" <?= $sort == 'airline_asc' ? 'selected' : '' ?>>Airline A → Z</option>
            <option value="airline_desc" <?= $sort == 'airline_desc' ? 'selected' : '' ?>>Airline Z → A</option>
            <option value="time_asc" <?= $sort == 'time_asc' ? 'selected' : '' ?>>Time ↑ (Earliest First)</option>
            <option value="time_desc" <?= $sort == 'time_desc' ? 'selected' : '' ?>>Time ↓ (Latest First)</option>
            <option value="gate_asc" <?= $sort == 'gate_asc' ? 'selected' : '' ?>>Gate A → Z</option>
            <option value="gate_desc" <?= $sort == 'gate_desc' ? 'selected' : '' ?>>Gate Z → A</option>
        </select>

        <button type="submit" class="h-12 px-6 w-full lg:w-auto bg-blue-600 text-white rounded-lg font-medium
                   transition duration-200
                   hover:bg-blue-700 active:scale-95"
        >
            Apply Sort
        </button>

    </div>
</form>
</section>

    <section class="p-6 pt-2 pb-0">

    <?php if (count($pageFlights) == 0): ?>

        <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-6 transition-all duration-200 hover:bg-blue-900/25 hover:border-blue-800 hover:shadow-lg">
            <h3 class="text-lg font-semibold text-white mb-2">
                No Flights Found
            </h3>
            <p class="text-gray-300">
                Try adjusting your filters or check back later for new flights.
            </p>
        </div>

    <?php else: ?>

        <div id="flightContainer" class="space-y-4">

<?php foreach ($pageFlights as $f): ?>

    <?php
        $flightTime = $getTime($f);
        $now = time();

        $canBook = (
            ($f['type'] ?? '') == 'departure'
            && ($f['landingAt'] ?? '') == 'SMN'
            && $flightTime > ($now + 86400)
        );
    ?>

    <div class="bg-gray-800 border border-gray-700 rounded-xl p-5
                hover:shadow-lg hover:-translate-y-1 hover:border-blue-500
                transition duration-300">

                <div class="grid grid-cols-1 md:grid-cols-[140px_200px_180px_180px_120px_160px_180px_1fr] gap-5 items-start md:items-center">
            <div>
                <h3 class="font-bold text-lg leading-tight">
                    <?= htmlspecialchars($f['flightNumber'] ?? 'N/A') ?>
                </h3>
                <p class="text-gray-400 text-sm">
                    <?= htmlspecialchars($f['airline'] ?? 'Unknown Airline') ?>
                </p>
            </div>

            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Scheduled</p>
                <p class="text-white text-sm whitespace-nowrap">
                    <?= $getDate($f) ?>
                </p>
            </div>

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

            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Status</p>
                <p class="text-sm text-gray-200 capitalize"
                   data-flight-id="<?= htmlspecialchars($f['flight_id'] ?? '') ?>"
                   data-field="status">
                    <?= htmlspecialchars($f['status'] ?? 'N/A') ?>
                </p>
            </div>

            <div>
                <?php if (($f['type'] ?? '') == 'departure'): ?>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Gate</p>
                    <p class="text-sm text-gray-200 font-semibold"
                       data-flight-id="<?= htmlspecialchars($f['flight_id'] ?? '') ?>"
                       data-field="gate">
                        <?= isset($f['gate']) ? strtoupper(htmlspecialchars($f['gate'])) : '—' ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="text-right justify-self-end">
    <?php if ($canBook): ?>
        <a href="../booking/booking.php?flight_id=<?= urlencode($f['flight_id'] ?? '') ?>"
           class="bg-blue-600 px-4 py-2 rounded-lg text-sm
                  hover:bg-blue-700 active:scale-95 transition duration-200">
            Book
        </a>
    <?php else: ?>

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
                } elseif ($flightTime <= ($now + 86400)) {
                    $reason = 'Booking closes 24 hours before departure.';
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
    <?php if ($totalFlights > 0): ?>
<section class="p-6">
    <div class="flex justify-end items-center gap-4">

        <?php if ($page > 1): ?>
            <a  class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition duration-200"
               href="?page=<?= $page - 1 ?>&status=<?= urlencode($statusTab) ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                Previous
            </a>
        <?php endif; ?>

        <div class="px-4 py-2 bg-gray-800 border border-gray-700 rounded">
            Page <?= $page ?> of <?= $totalPages ?>
        </div>

        <?php if ($page < $totalPages): ?>
            <a  class="px-4 py-2 bg-gray-700 rounded hover:bg-gray-600 transition duration-200"
               href="?page=<?= $page + 1 ?>&status=<?= urlencode($statusTab) ?>&mode=<?= urlencode($mode) ?>&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>">
                Next
            </a>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>
</div>

<script>
    async function updateFlights() {
        try {
            const res = await fetch(window.location.pathname + '?ajax=1');
            const data = await res.json();

            if (!data.flights) return;

            data.flights.forEach(f => {

                if (!f.flight_id) return;

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
            console.error("AJAX update failed:", err);
        }
    }

    updateFlights();
    setInterval(updateFlights, 5000);
</script>
</body>
</html>