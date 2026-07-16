<?php

require_once __DIR__ . '/../api/api.php';
require_once __DIR__ . '/../api/key.php';
require_once __DIR__ . '/../database/db.php';

<<<<<<< HEAD
if (empty($_SESSION['user_id'])) {
    return;
}

$api = new AirportsAPI(AIRPORTS_API_KEY);
?>

<aside class="w-72 bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 text-white border-r border-gray-700 shadow-xl flex flex-col">

    <!-- Header -->
    <div class="p-6 border-b border-gray-700">
        <h2 class="text-2xl font-bold">Welcome 👋</h2>
        <p class="text-sm text-gray-400 mt-1">
            Glad to see you back.
        </p>
    </div>

    <!-- Profile Card -->
    <div class="p-6">
        <div class="bg-gray-800 border border-gray-700 rounded-2xl p-5 shadow-lg">

            <!-- Avatar -->
            <div class="flex justify-center mb-5">
                <div class="w-20 h-20 rounded-full bg-blue-600 flex items-center justify-center text-3xl font-bold shadow-lg">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
            </div>

            <!-- Name -->
            <h3 class="text-xl font-semibold text-center">
                <?= htmlspecialchars($_SESSION['name']) ?>
            </h3>

            <div class="mt-6 space-y-4">

                <!-- Username -->
                <div class="bg-gray-900 rounded-xl p-3 border border-gray-700">
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">
                        Username
                    </p>
                    <p class="text-gray-100 break-all">
                        <?= htmlspecialchars($_SESSION['name']) ?>
                    </p>
                </div>

                <!-- Email -->
                <div class="bg-gray-900 rounded-xl p-3 border border-gray-700">
                    <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">
                        Email
                    </p>
                    <p class="text-gray-100 break-all">
                        <?= htmlspecialchars($_SESSION['email']) ?>
                    </p>
                </div>

            </div>
        </div>
    </div>

</aside>
=======

// AJAX update handler
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {

    header('Content-Type: application/json');

    try {

        $api = new AirportsAPI(AIRPORTS_API_KEY);

        $ticketsStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE user_id = ?');
        $ticketsStmt->execute([$_SESSION['user_id'] ?? null]);

        $tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

        $closestFlight = null;
        $closestDeparture = PHP_INT_MAX;
        $now = time();

        foreach ($tickets as $ticket) {

            $response = $api->getFlights(
                ["flight_id" => $ticket['flight_id']],
                null,
                0,
                'desc'
            );

            $flight = $response['flights'][0] ?? null;

            if (!$flight || empty($flight['departFromSender'])) {
                continue;
            }

            $departure = (int)($flight['departFromSender'] / 1000);

            if ($departure < $now) {
                continue;
            }

            if ($departure < $closestDeparture) {
                $closestDeparture = $departure;
                $closestFlight = $flight;
            }
        }


        echo json_encode([
            "airline" => $closestFlight['airline'] ?? 'Unknown',
            "flightNumber" => $closestFlight['flightNumber'] ?? '-',
            "departure" => !empty($closestFlight['departFromSender'])
                ? date('M j, Y g:i A', $closestFlight['departFromSender'] / 1000)
                : 'TBD',
            "arrival" => !empty($closestFlight['arriveAtReceiver'])
                ? date('M j, Y g:i A', $closestFlight['arriveAtReceiver'] / 1000)
                : 'TBD'
        ]);

    } catch (Exception $e) {

        http_response_code(500);

        echo json_encode([
            "error" => $e->getMessage()
        ]);
    }

    exit;
}



// Normal page load
$api = new AirportsAPI(AIRPORTS_API_KEY);

$stmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id'] ?? null]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);


$ticketsStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE user_id = ?');
$ticketsStmt->execute([$_SESSION['user_id'] ?? null]);

$userTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);


$selectedTicket = null;
$flight = null;

$now = time();
$closestDeparture = PHP_INT_MAX;


foreach ($userTickets as $ticket) {

    $flightId = $ticket['flight_id'];

    $response = $api->getFlights(
        ["flight_id" => $flightId],
        null,0,
        'desc'
    );


    $currentFlight = $response['flights'][0] ?? null;


    if (!$currentFlight || empty($currentFlight['departFromSender'])) {
        continue;
    }


    $departure = (int)($currentFlight['departFromSender'] / 1000);


    if ($departure < $now) {
        continue;
    }


    if ($departure < $closestDeparture) {

        $closestDeparture = $departure;
        $selectedTicket = $ticket;
        $flight = $currentFlight;

    }
}

?>


<aside class="w-64 bg-gray-900 text-white flex flex-col">

<div class="p-6 text-2xl font-bold border-b border-gray-700">
    Ticket Details
</div>


<nav class="flex-1 p-4 space-y-2">


<p class="text-gray-400">
User: <?= htmlspecialchars($dbUser['first_name'] ?? 'Not Signed In') ?>
</p>


<p class="text-gray-400">
Email: <?= htmlspecialchars($dbUser['email'] ?? 'Not Signed In') ?>
</p>


<p class="text-gray-400">
Tickets: <?= htmlspecialchars($selectedTicket['ticket_id'] ?? '-') ?>
</p>


<p class="text-gray-400">
Destination: <?= htmlspecialchars($selectedTicket['destination'] ?? '-') ?>
</p>


<p class="text-gray-400">
Airline:
<span data-field="airline">
<?= htmlspecialchars($flight['airline'] ?? 'Unknown') ?>
</span>
</p>


<p class="text-gray-400">
Flight Number:
<span data-field="flightNumber">
<?= htmlspecialchars($flight['flightNumber'] ?? '-') ?>
</span>
</p>


<p class="text-gray-400">
Departure:
<span data-field="departure">
<?= !empty($flight['departFromSender'])
? date('M j, Y g:i A',$flight['departFromSender']/1000)
: 'TBD'; ?>
</span>
</p>


<p class="text-gray-400">
Arrival:
<span data-field="arrival">
<?= !empty($flight['arriveAtReceiver'])
? date('M j, Y g:i A',$flight['arriveAtReceiver']/1000)
: 'TBD'; ?>
</span>
</p>


</nav>

</aside>



<script>

async function updateSidebarFlight(){

    try {

        const response = await fetch(
            window.location.pathname + "?ajax=1"
        );


        const data = await response.json();


        if(data.error){
            console.error(data.error);
            return;
        }
        document.querySelector('[data-field="first_name"]').textContent =
            data.first_name;


        document.querySelector('[data-field="email"]').textContent =
            data.email;


        document.querySelector('[data-field="ticket_id"]').textContent =
            data.ticket_id;


        document.querySelector('[data-field="destination"]').textContent =
            data.destination;

        document.querySelector('[data-field="airline"]').textContent =
            data.airline;


        document.querySelector('[data-field="flightNumber"]').textContent =
            data.flightNumber;


        document.querySelector('[data-field="departure"]').textContent =
            data.departure;


        document.querySelector('[data-field="arrival"]').textContent =
            data.arrival;


    } catch(error){

        console.error(
            "Sidebar AJAX failed:",
            error
        );

    }

}


// Update immediately
updateSidebarFlight();


// Refresh every 13 seconds
setInterval(updateSidebarFlight,30000);


</script>
>>>>>>> 2e41efb0b1e8764ba611fd8427585c7df88402f7
