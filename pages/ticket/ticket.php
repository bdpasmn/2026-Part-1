<?php
// placeholder
$ticket = [
    'ticket_id' => 12345,
    'flight_type' => 'Departure',
    'airline' => 'Delta Airlines',
    'flight_number' => 'DL452',
    'destination_city' => 'Indianapolis',
    'destination_state' => 'Indiana',
    'destination_country' => 'United States',
    'destination_airport' => 'IND',
    'departure_time' => '2026-06-15 08:30 AM',
    'arrival_time' => '2026-06-15 11:45 AM',
    'passenger_name' => 'John Doe',
    'gate' => 'B12',
    'confirmation_number' => 'ABC123',
    'status' => 'On Time'
];

$status = strtolower($ticket['status']);

$statusClass = match ($status) {
    'on time'  => 'bg-green-900 text-green-300',
    'delayed'  => 'bg-yellow-900 text-yellow-300',
    'cancelled', 'canceled' => 'bg-red-900 text-red-300',
    default    => 'bg-gray-700 text-gray-300'
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
                <div class="flex justify-between items-start">
                    <div>
                        <p class="tracking-[0.25em] text-sm text-blue-300 mb-4">BDPA AIRPORTS</p>
                        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Flight Ticket</h1>
                        <h2 class="text-2xl font-bold text-white mb-2">
                            <?= htmlspecialchars($ticket['passenger_name']) ?>
                        </h2>
                    </div>

                    <div class="text-right flex flex-col items-end gap-3">
                        <h2 class="text-2xl font-bold text-white mb-2">
                            <?= htmlspecialchars($ticket['airline']) ?>
                            <?= htmlspecialchars($ticket['flight_number']) ?>
                        </h2>

                        <span class="inline-block px-4 py-2 rounded-full text-lg font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($ticket['status']) ?>
                        </span>

                        <span class="text-2xl font-bold text-blue-400">
                            Gate <?= htmlspecialchars($ticket['gate']) ?>
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
                    <span class="font-medium text-gray-300">Passenger</span>
                    <span class="text-white">
                        <?= htmlspecialchars($ticket['passenger_name']) ?>
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
                    <span class="font-medium text-gray-300">Destination</span>
                    <span class="text-right text-white">
                        <?= htmlspecialchars($ticket['destination_city']) ?>,
                        <?= htmlspecialchars($ticket['destination_state']) ?>
                        (<?= htmlspecialchars($ticket['destination_airport']) ?>)
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
                    <span class="font-bold text-blue-400 text-lg" id="gate-number">
                        <?= htmlspecialchars($ticket['gate']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Confirmation Number</span>
                    <span class="font-mono text-white text-lg">
                        <?= htmlspecialchars($ticket['confirmation_number']) ?>
                    </span>
                </div>

                <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                    <span class="font-medium text-gray-300">Flight Status</span>
                    <span class="text-right text-white">
                        <?= htmlspecialchars($ticket['destination_city']) ?>,
                        <?= htmlspecialchars($ticket['destination_state']) ?>
                        (<?= htmlspecialchars($ticket['destination_airport']) ?>)
                    </span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="font-medium text-gray-300">Flight Type</span>
                        <span class="inline-block px-2 py-2 rounded-full text-sm font-semibold <?= $statusClass ?>">
                            <?= htmlspecialchars($ticket['status']) ?>
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