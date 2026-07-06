<?php
session_start();

require_once __DIR__ . '/../api/api.php';
require_once __DIR__ . '/../api/key.php';
require_once __DIR__ . '/../database/db.php';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$stmt = $pdo->prepare('SELECT * FROM "Users" WHERE user_id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id'] ?? null]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Load this customer's tickets
$ticketsStmt = $pdo->prepare('SELECT * FROM "Tickets" WHERE user_id = ?');
$ticketsStmt->execute([$_SESSION['user_id'] ?? null]);
$userTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$flight = null;
$selectedTicket = null;

$now = time();
$closestDeparture = PHP_INT_MAX;

foreach ($userTickets as $ticket) {

    $flightId = $ticket['flight_id'];

    $response = $api->searchFlights(
        ["flight_id" => $flightId],
        null,0
        "desc"
    );

    $currentFlight = $response['flights'][0] ?? null;

    if (!$currentFlight || empty($currentFlight['departFromSender'])) {
        continue;
    }

    // API timestamps are in milliseconds
    $departure = (int)($currentFlight['departFromSender'] / 1000);

    // Ignore flights that have already departed
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

<html>
<head>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

<div class="flex h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-gray-900 text-white flex flex-col">
    <div class="p-6 text-2xl font-bold border-b border-gray-700">
      Ticket Details
    </div>

    <nav class="flex-1 p-4 space-y-2">
       <p class="text-gray-400">User: <?php echo htmlspecialchars($dbUser['first_name']  ?? 'Not Signed In'); ?></p>
       <p class="text-gray-400">Email: <?php echo htmlspecialchars($dbUser['email']  ?? 'Not Signed In'); ?></p>
       <p class="text-gray-400">Tickets: <?php echo htmlspecialchars($selectedTicket['ticket_id']  ?? '-'); ?></p>
       <p class="text-gray-400">Destination: <?php echo htmlspecialchars($selectedTicket['destination'] ?? '-'); ?></p>
       <p class="text-gray-400">Airline: <?= htmlspecialchars($flight['airline'] ?? 'Unknown') ?></p>
       <p class="text-gray-400">Flight Number: <?= htmlspecialchars($flight['flightNumber'] ?? '-') ?></p>
       <p class="text-gray-400">Departure: <?= !empty($flight['departFromSender'])? date('M j, Y g:i A', $flight['departFromSender'] / 1000): 'TBD'; ?></p>
       <p class="text-gray-400">Arrival: <?= !empty($flight['arriveAtReceiver'])? date('M j, Y g:i A', $flight['arriveAtReceiver'] / 1000): 'TBD'; ?></p>
    </nav>
  </aside>

</div>

</body>
</html>
