<?php
require_once __DIR__ . '/../../api/key.php';
require_once __DIR__ . '/../../api/api.php';
require_once __DIR__ . '/../../database/db.php';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$confirmation = $_GET['confirmation'] ?? null;

$stmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE confirmation_code = ? LIMIT 1');
$stmt->execute([$confirmation]);
$ticketRow = $stmt->fetch();

$flightId = $ticketRow['flight_id'] ?? null;
$ticket = null;
$flight = null;

//  implemented AJAX request for flight status updates
if (isset($_GET['xhr']) && $_GET['xhr'] === 'flight-status') {
    header('Content-Type: application/json');

    if (!$flightId) {
        http_response_code(400);
        echo json_encode(['error' => 'missing flight_id']);
        exit;
    }

    $flightsResponse = $api->searchFlights(
        ['flight_id' => $flightId],
        null,
        'desc'
    );

    $flight = $flightsResponse['flights'][0] ?? null;

    if (!$flight) {
        http_response_code(404);
        echo json_encode(['error' => 'flight not found']);
        exit;
    }

    echo json_encode([
        'departure' => $flight['departFromSender'] ?? null,
        'arrival' => $flight['arriveAtReceiver'] ?? null,
        'gate' => (strtolower($flight['status'] ?? '') === 'past') ? 'N/A' : strtoupper($flight['gate'] ?? 'TBD'),
        'status' => isset($flight['status']) ? ucwords(strtolower($flight['status'])) : 'Unknown'
    ]);
    exit;
}

//ticket deletion handler
if (isset($_GET['xhr']) && $_GET['xhr'] === 'delete-ticket') {
    header('Content-Type: application/json');
    $confirmation = $_GET['confirmation'] ?? null;

    if (!$confirmation) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing confirmation code']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // get flight_id and the specific seat assigned to this ticket
        $stmtGet = $pdo->prepare('SELECT flight_id, seat FROM "Tickets" WHERE confirmation_code = ? LIMIT 1');
        $stmtGet->execute([$confirmation]);
        $ticketData = $stmtGet->fetch();

        if ($ticketData) {
            $fId = $ticketData['flight_id'];
            $seatToRemove = $ticketData['seat'];
            //select seat and remove
            $stmtFlight = $pdo->prepare('UPDATE "Flights" SET taken_seats = (SELECT jsonb_agg(elem) FROM jsonb_array_elements(taken_seats) AS elem WHERE elem::text != :seat) WHERE flight_id = ?');
            $stmtFlight->execute([$seatToRemove, $fId]);
        }

        $stmtUpdate = $pdo->prepare('UPDATE "Tickets" SET status = ? WHERE confirmation_code = ?');
        $stmtUpdate->execute(['Cancelled', $confirmation]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($ticketRow) {
    $flightsResponse = $api->searchFlights(
        ['flight_id' => $flightId],
        null,
        'desc'
    );

    $flight = $flightsResponse['flights'][0] ?? null;

    $passengerName = trim(
        ($ticketRow['name_first'] ?? '') . ' ' .
        ($ticketRow['name_middle'] ?? '') . ' ' .
        ($ticketRow['name_last'] ?? '')
    );
    if ($passengerName === '') {
        $passengerName = 'Unknown Passenger';
    }

    date_default_timezone_set('America/New_York');

    if ($flight) {

        $dest_airport_name = '';
        $dest_airport_code = '';
        $dest_city = '';
        $dest_state = '';
        $dest_country = '';

        $dest_airport_code = $flight['departingTo'] ?? ($flight['landingAt'] ?? '');

        if (!empty($dest_airport_code)) {
            $airportsResp = $api->getAirports();
            if (isset($airportsResp['airports']) && is_array($airportsResp['airports'])) {
                foreach ($airportsResp['airports'] as $ap) {
                    $shortA = $ap['shortName'] ?? ($ap['short_name'] ?? '');
                    if ($shortA !== '' && strcasecmp($shortA, $dest_airport_code) === 0) {
                        $dest_airport_name = $ap['name'] ?? '';
                        $dest_airport_code = $shortA;
                        $dest_city = $ap['city'] ?? '';
                        $dest_state = $ap['state'] ?? '';
                        $dest_country = $ap['country'] ?? '';
                        break;
                    }
                }
            }
        }

        $ticket = [
            'flight_id' => $flight['flight_id'],
            'departure_airport' => $flight['type'] === 'arrival' ? $flight['comingFrom'] : $flight['landingAt'],
            'ticket_id' => $ticketRow['ticket_id'],
            'confirmation_number' => $ticketRow['confirmation_code'] ?? '',
            'flight_type' => ucfirst($flight['type']),
            'airline' => $flight['airline'],
            'seat' => $ticketRow['seat'] ?? 'TBD',
            'flight_number' => $flight['flightNumber'],
            'destination_airport' => $dest_airport_code,
            'destination_airport_name' => $dest_airport_name,
            'destination_airport_code' => $dest_airport_code,
            'destination_city' => $dest_city,
            'destination_state' => $dest_state,
            'destination_country' => $dest_country,
            'departure_time' => $flight['departFromSender']
                ? date('h:i A', $flight['departFromSender'] / 1000)
                : 'TBD',
            'departure_time_raw' => $flight['departFromSender'] ?? null,
            'arrival_time' => $flight['arriveAtReceiver']
                ? date('h:i A', $flight['arriveAtReceiver'] / 1000)
                : 'TBD',
            'arrival_time_raw' => $flight['arriveAtReceiver'] ?? null,
            'passenger_name' => $passengerName,
            'status' => isset($flight['status']) ? ucwords(strtolower($flight['status'])) : 'Unknown',
            'gate' => (strtolower($flight['status'] ?? '') === 'past') ? 'N/A' : strtoupper($flight['gate'] ?? 'TBD')
        ];

        $destinationParts = array_filter([
            $ticket['destination_city'] ?? '',
            $ticket['destination_state'] ?? '',
            $ticket['destination_country'] ?? ''
        ]);
        $ticket['destination_display'] = $destinationParts ? implode(', ', $destinationParts) : '';
    } else {
        echo '<pre style="color:red;background:#1e1e1e;padding:1rem;">';
        echo 'Flight not found for confirmation: ' . htmlspecialchars($confirmation) . "\n\n";
        echo print_r($flightsResponse, true);
        echo '</pre>';
        exit;
    }
}

$status = $ticket ? strtolower($ticket['status']) : '';

$statusClass = match ($status) {
    'past'      => 'bg-slate-700 text-slate-200',
    'scheduled' => 'bg-blue-900 text-blue-300',
    'cancelled' => 'bg-red-900 text-red-300',
    'delayed'   => 'bg-amber-900 text-amber-300',
    'on time'   => 'bg-emerald-900 text-emerald-300',
    'landed'    => 'bg-indigo-900 text-indigo-300',
    'arrived'   => 'bg-teal-900 text-teal-300',
    'boarding'  => 'bg-sky-900 text-sky-300',
    'departed'  => 'bg-violet-900 text-violet-300',
    default     => 'bg-gray-700 text-gray-300'
};

?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flight Ticket</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="icon" href="/bdpa/favicon.ico" type="image/x-icon">
</head>
 
<body class="bg-gray-900 min-h-screen text-white">
<div class="w-full min-h-screen bg-gray-900">
    <?php include __DIR__ . '/../../components/nav.php'; ?>
 
    <main class="w-full p-6">
 
        <?php if (!$ticketRow): ?>
 
            <div class="bg-slate-800 border border-gray-700 rounded-xl p-16 flex flex-col items-center gap-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <p class="text-yellow-400 font-semibold text-2xl">No Ticket Detected</p>
                <p class="text-slate-400 text-base">
                    <?php if ($confirmation): ?>
                        No ticket was found for confirmation code <span class="font-mono text-slate-300"><?= htmlspecialchars($confirmation) ?></span>
                    <?php else: ?>
                        No confirmation code was provided.
                    <?php endif; ?>
                </p>
            </div>
 
        <?php else: ?>
 
            <!-- ticket card -->
            <div class="bg-slate-800 border border-slate-700 rounded-2xl overflow-hidden">
 
                <!-- top half -->
                <div class="p-6 md:p-10">
 
                    <!-- status/headline -->
                    <div class="flex justify-between items-center mb-8">
                        <div class="flex flex-col gap-1">
                            <p class="text-sm tracking-widest text-blue-400 uppercase">BDPA Airports &middot; Ticket View</p>
                            <p id="local-clock" class="text-xs text-slate-500 font-mono"></p>
                        </div>
                        <span id="flight-status" class="px-6 py-2 rounded-full text-xl font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($ticket['status']) ?>
                        </span>
                    </div>
 
                    <!-- route -->
                    <div class="flex items-center gap-6 mb-10">
                        <div>
                            <p class="text-5xl md:text-7xl font-semibold text-white leading-none"><?= htmlspecialchars($ticket['departure_airport']) ?></p>
                            <p class="text-base text-slate-500 mt-2"><?= htmlspecialchars($ticket['departure_airport']) ?></p>
                        </div>
                        <div class="flex-1 flex flex-col items-center gap-3">
                            <div class="w-full h-px bg-slate-700"></div>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="48" height="48" overflow="hidden"><g transform="translate(320, 320) rotate(0) scale(1, 1) translate(-320, -320)"><path fill="#60A5FA" d="M552 264c30.9 0 56 25.1 56 56s-25.1 56-56 56H424.7L265.5 549.6c-6.1 6.6-14.6 10.4-23.6 10.4h-43.7c-10.9 0-18.6-10.7-15.2-21.1L237.3 376h-99.7l-52.8 66c-3 3.8-7.6 6-12.5 6H52.5c-10.4 0-18-9.8-15.5-19.9L64 320L37 211.9c-2.6-10.1 5.1-19.9 15.5-19.9h19.8c4.9 0 9.5 2.2 12.5 6l52.8 66h99.7L183 101.1c-3.4-10.4 4.3-21.1 15.2-21.1h43.7c9 0 17.5 3.8 23.6 10.4L424.7 264z"/></g></svg>
                        </div>
                        <div class="text-right">
                            <p class="text-5xl md:text-7xl font-semibold text-white leading-none"><?= htmlspecialchars($ticket['destination_airport_code']) ?></p>
                            <p class="text-base text-slate-500 mt-2"><?= htmlspecialchars($ticket['destination_display'] ?: $ticket['destination_airport_code']) ?></p>
                        </div>
                    </div>
 
                    <!-- key info pills -->
                    <div class="grid grid-cols-2 md:flex justify-center gap-4">
                        <div class="flex-1 bg-slate-900 rounded-xl px-6 py-6 text-center">
                            <p class="text-xs tracking-widest text-slate-500 uppercase mb-3">Seat</p>
                            <p class="text-4xl font-semibold text-blue-400"><?= htmlspecialchars($ticket['seat']) ?></p>
                        </div>
                        <div class="flex-1 bg-slate-900 rounded-xl px-6 py-6 text-center">
                            <p class="text-xs tracking-widest text-slate-500 uppercase mb-3">Gate</p>
                            <p id="gate" class="text-4xl font-semibold text-blue-400"><?= htmlspecialchars($ticket['gate']) ?></p>
                        </div>
                        <div class="flex-1 bg-slate-900 rounded-xl px-6 py-6 text-center">
                            <p class="text-xs tracking-widest text-slate-500 uppercase mb-3">Departs</p>
                            <p id="departure-time" class="text-2xl font-semibold text-blue-400 mt-1"><?= htmlspecialchars($ticket['departure_time']) ?></p>
                            <p id="departure-date" class="text-[10px] text-slate-600 uppercase tracking-tighter mt-1"></p>
                        </div>
                        <div class="flex-1 bg-slate-900 rounded-xl px-6 py-6 text-center">
                            <p class="text-xs tracking-widest text-slate-500 uppercase mb-3">Arrives</p>
                            <p id="arrival-time" class="text-2xl font-semibold text-blue-400 mt-1"><?= htmlspecialchars($ticket['arrival_time']) ?></p>
                            <p id="arrival-date" class="text-[10px] text-slate-600 uppercase tracking-tighter mt-1"></p>
                        </div>
                    </div>
                </div>
 
                <!-- cool tear line -->
                <div class="flex items-center">
                    <div class="w-6 h-6 rounded-full bg-gray-900 border border-slate-700 -ml-3 flex-shrink-0"></div>
                    <div class="flex-1 border-t-2 border-dashed border-slate-700"></div>
                    <div class="w-6 h-6 rounded-full bg-gray-900 border border-slate-700 -mr-3 flex-shrink-0"></div>
                </div>
 
                <!-- bottom half -->
                <div class="p-6 md:p-10">
 
                    <!-- Passenger -->
                    <p class="text-xs tracking-widest text-slate-500 uppercase mb-2">Passenger</p>
                    <p class="text-2xl font-semibold text-white mb-6"><?= htmlspecialchars($ticket['passenger_name']) ?></p>
 
                    <!-- Detail rows -->
                    <div>
                        <div class="flex justify-between py-3 border-b border-slate-700/50">
                            <span class="text-sm text-slate-500">Airline / Flight</span>
                            <span class="text-sm text-slate-300"><?= htmlspecialchars($ticket['airline']) ?> <?= htmlspecialchars($ticket['flight_number']) ?></span>
                        </div>
                        <div class="flex justify-between items-start py-3 border-b border-slate-700/50">
                            <span class="text-sm text-slate-500">Destination</span>
                            <span class="text-sm text-right">
                                <div class="text-slate-300 font-semibold"><?= htmlspecialchars($ticket['destination_airport_name']) ?></div>
                                <div class="text-slate-500 mt-0.5"><?= htmlspecialchars(implode(', ', array_filter([$ticket['destination_city'] ?? '', $ticket['destination_state'] ?? '', $ticket['destination_country'] ?? '']))) ?></div>
                            </span>
                        </div>
                        <div class="flex justify-between py-3 border-b border-slate-700/50">
                            <span class="text-sm text-slate-500">Ticket ID</span>
                            <span class="text-sm font-mono text-slate-400"><?= htmlspecialchars($ticket['ticket_id']) ?></span>
                        </div>
                        <div class="flex justify-between py-3">
                            <span class="text-sm text-slate-500">Flight type</span>
                            <span class="text-sm text-slate-300"><?= htmlspecialchars($ticket['flight_type']) ?></span>
                        </div>
                    </div>
 
                    <!-- footer -->
                    <div class="flex justify-between items-center mt-8 pt-6 border-t border-slate-700">
                        <div>
                            <p class="text-xs tracking-widest text-slate-500 uppercase mb-2">Confirmation</p>
                            <p class="font-mono text-blue-400 text-2xl tracking-widest"><?= htmlspecialchars($ticket['confirmation_number']) ?></p>
                        </div>
                        
                        <button onclick="confirmDeleteTicket()" 
                                class="px-4 py-2 bg-slate-900 hover:bg-red-900/30 text-slate-500 hover:text-red-400 text-xs rounded-lg transition duration-150 border border-slate-700/50">
                            Delete Ticket
                        </button>

                        <a href="downloadTicket.php?confirmation=<?= urlencode($ticket['confirmation_number']) ?>"  
                               class="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-sm rounded-xl transition duration-150">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Download Ticket
                            </a>
                    </div>
 
                </div>
            </div>
        <?php endif; ?>

 
 
    </main>
</div>
<script>
//clock
function updateLocalClock() {
    const el = document.getElementById('local-clock');
    if (!el) return;

    el.textContent =
        'Local Time: ' +
        new Date().toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
}

//flight format
function formatFlightTime(ms) {
    if (!ms) return { time: 'TBD', date: '' };

    const date = new Date(ms);

    return {
        time: date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        date: date.toLocaleDateString([], {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        })
    };
}

//flight refresh
async function refreshTicketFlightStatus() {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('xhr', 'flight-status');

        const res = await fetch(url.toString(), {
            credentials: 'same-origin'
        });

        if (!res.ok) return;

        const data = await res.json();

        const departureEl = document.getElementById('departure-time');
        const departureDateEl = document.getElementById('departure-date');
        const arrivalEl = document.getElementById('arrival-time');
        const arrivalDateEl = document.getElementById('arrival-date');
        const gateEl = document.getElementById('gate');
        const statusEl = document.getElementById('flight-status');

        if (departureEl) {
            const fmt = formatFlightTime(data.departure);
            departureEl.textContent = fmt.time;
            if (departureDateEl) departureDateEl.textContent = fmt.date;
        }

        if (arrivalEl) {
            const fmt = formatFlightTime(data.arrival);
            arrivalEl.textContent = fmt.time;
            if (arrivalDateEl) arrivalDateEl.textContent = fmt.date;
        }

        if (gateEl) gateEl.textContent = data.gate || 'TBD';
        if (statusEl && data.status) statusEl.textContent = data.status;

    } catch (err) {
        console.error('Flight refresh failed:', err);
    }
}

//ticket deletion
async function confirmDeleteTicket() {
    const confirmation = "<?= $ticket['confirmation_number'] ?? '' ?>";
    if (!confirmation) return;

    if (!confirm("Are you sure you want to delete this ticket? This action cannot be undone.")) {
        return;
    }

    try {
        const url = new URL(window.location.href);
        url.searchParams.set('xhr', 'delete-ticket');

        const res = await fetch(url.toString(), {
            credentials: 'same-origin'
        });

        const data = await res.json();

        if (data.success) {
            window.location.href = 'ticket.php';
        } else {
            alert("Error deleting ticket: " + data.error);
        }

    } catch (err) {
        console.error(err);
        alert("A network error occurred.");
    }
}

//init
<?php if ($ticketRow): ?>
updateLocalClock();
setInterval(updateLocalClock, 1000);

refreshTicketFlightStatus();
setInterval(refreshTicketFlightStatus, 15000);
<?php endif; ?>
</script>
</body>
</html>