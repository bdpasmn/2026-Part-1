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
    $passengerName = 'The Person who Shall Not be Named';
}

date_default_timezone_set('America/New_York');

if ($flight) {
    $ticket = [
        'flight_id' => $flight['flight_id'],
        'departure_airport' => $flight['comingFrom'],
        'ticket_id' => $ticketRow['ticket_id'],
        'confirmation_number' => $ticketRow['confirmation_code'] ?? '',
        'flight_type' => ucfirst($flight['type']),
        'airline' => $flight['airline'],
        'seat' => $ticketRow['seat'] ?? 'TBD',
        'flight_number' => $flight['flightNumber'],
        'destination_airport' => $flight['landingAt'],
        'departure_time' => $flight['departFromSender']
            ? date('h:i A', $flight['departFromSender'] / 1000)
            : 'TBD',
        'arrival_time' => $flight['arriveAtReceiver']
            ? date('h:i A', $flight['arriveAtReceiver'] / 1000)
            : 'TBD',
        'passenger_name' => $passengerName,
        'gate' => strtoupper($flight['gate'] ?? 'TBD'),
        'status' => isset($flight['status']) ? ucwords(strtolower($flight['status'])) : 'Unknown'
    ];
} else {
    die('Flight not found for confirmation code: ' . htmlspecialchars($confirmationCode));
}

$status = strtolower($ticket['status']);

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
<style>

</style>
</head>
<body class="bg-gray-900 min-h-screen text-white">
    <header class="h-16 bg-gray-800 flex items-center px-8 border-b border-gray-700">
        <h1 class="text-white font-bold text-xl">BDPA Airports - TO BE REPLACED WITH NAV</h1>
    </header>

    <main class="w-full p-6">
        <section class="p-6">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-xl p-10 shadow-lg mb-6">
                <div class="flex flex-col lg:flex-row justify-between items-start gap-6">
                    <div>
                        <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS</p>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-8">Flight Ticket</h1>
                        <h2 class="text-3xl font-bold text-white mb-2">
                            <?= htmlspecialchars($ticket['passenger_name']) ?>
                        </h2>
                    </div>

                    <div class="flex-1 flex flex-col sm:flex-row items-center justify-center gap-8 px-4 py-2">
                        <div class="min-w-[180px] max-w-[220px] text-center">
                            <div class="text-sm uppercase tracking-[0.3em] text-slate-400 mb-2">Seat</div>
                            <div class="text-5xl font-bold text-blue-400 mb-3">
                                <?= htmlspecialchars($ticket['seat']) ?>
                            </div>
                            <div class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-700 px-4 py-2 text-sm uppercase tracking-[0.3em] text-slate-400 mb-4">
                                <span>Gate</span>
                                <span class="font-bold text-blue-400"><?= htmlspecialchars($ticket['gate']) ?></span>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-center gap-2 text-white">
                                <div class="text-left">
                                    <div class="text-[0.65rem] uppercase text-slate-400">From</div>
                                    <div class="text-base font-semibold">
                                        <?= htmlspecialchars($ticket['departure_airport']) ?>
                                    </div>
                                </div>

                                <div class="text-blue-400 text-2xl font-bold">
                                    &rarr;
                                </div>

                                <div class="text-right">
                                    <div class="text-[0.65rem] uppercase text-slate-400">To</div>
                                    <div class="text-base font-semibold">
                                        <?= htmlspecialchars($ticket['destination_airport']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-[240px] text-right flex flex-col items-end gap-10 px-4 py-4">
                        <h2 class="text-3xl font-bold text-white mb-2">
                            <?= htmlspecialchars($ticket['airline']) ?>
                            <?= htmlspecialchars($ticket['flight_number']) ?>
                        </h2>

                        <span class="inline-block px-5 py-3 rounded-full text-lg font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($ticket['status']) ?>
                        </span>
                    </div>
                </div>
        </section>

        <div class="bg-gradient-to-r from-slate-800 to-slate-900 border border-gray-700 rounded-lg p-6">
            <h3 class="text-xl font-bold mb-6 text-white">Ticket Details</h3>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Ticket ID</span>
                    <span class="font-mono text-white"><?= htmlspecialchars($ticket['ticket_id']) ?></span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Confirmation Number</span>
                    <span class="font-mono text-white"><?= htmlspecialchars($ticket['confirmation_number']) ?></span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Passenger</span>
                    <span class="text-white">
                        <?= htmlspecialchars($ticket['passenger_name']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Seat</span>
                    <span class="font-bold text-blue-400 text-lg">
                        <?= htmlspecialchars($ticket['seat']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Airline / Flight</span>
                    <span class="text-white">
                        <?= htmlspecialchars($ticket['airline']) ?>
                        <?= htmlspecialchars($ticket['flight_number']) ?>
                    </span>
                </div>

            <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Departure</span>
                    <span class="text-right text-white">
                        <?= htmlspecialchars($ticket['departure_airport']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Destination</span>
                    <span class="text-right text-white">
                        <?= htmlspecialchars($ticket['destination_airport']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Departure Time</span>
                    <span class="text-white" id="departure-time">
                        <?= htmlspecialchars($ticket['departure_time']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Arrival Time</span>
                    <span class="text-white" id="arrival-time">
                        <?= htmlspecialchars($ticket['arrival_time']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Gate</span>
                    <span class="font-bold text-blue-400 text-lg">
                        <?= htmlspecialchars($ticket['gate']) ?>
                    </span>
                </div>


                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Flight Status</span>
                    <span class="text-right text-white">
                        <span class="inline-block px-2 py-2 rounded-full text-sm font-semibold <?= $statusClass ?>">
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Flight Type</span>
                           <span class="font-mono text-white text-lg">
                            <?= htmlspecialchars($ticket['flight_type']) ?>
                        </span>
                </div>
            </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Flight ID(for development purposes only)</span>
                           <span class="font-mono text-white text-lg">
                            <?= htmlspecialchars($ticket['flight_id']) ?>
                        </span>
                </div>
            </div>
            

            <div class="flex justify-between mt-8 pt-6 border-t border-gray-700">
                <button class="px-6 py-3 bg-gray-800 border border-gray-700 text-white rounded-lg transition duration-200 hover:bg-gray-700">Back</button>
                <button class="px-8 py-3 bg-blue-600 text-white rounded-lg transition duration-200 hover:bg-blue-700 active:scale-95">Download Ticket</button>
            </div>
        </div>
    </main>
</body>
</html>