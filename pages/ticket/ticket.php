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
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flight Ticket</title>
<!-- ill wait on arjun for the styling but this is a barebones example -->
<style>

</style>
</head>
<body>

<div class="ticket">

    <div class="ticket-header">
        <h1>Flight Ticket</h1>
        <h3><?= htmlspecialchars($ticket['flight_type']) ?></h3>
    </div>

    <div class="ticket-body">

        <div class="row">
            <span class="label">Passenger</span>
            <span class="value">
                <?= htmlspecialchars($ticket['passenger_name']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Airline / Flight</span>
            <span class="value">
                <?= htmlspecialchars($ticket['airline']) ?>
                <?= htmlspecialchars($ticket['flight_number']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Destination</span>
            <span class="value">
                <?= htmlspecialchars($ticket['destination_city']) ?>,
                <?= htmlspecialchars($ticket['destination_state']) ?>,
                <?= htmlspecialchars($ticket['destination_country']) ?>
                (<?= htmlspecialchars($ticket['destination_airport']) ?>)
            </span>
        </div>

        <div class="row">
            <span class="label">Departure Time</span>
            <span class="value" id="departure-time">
                <?= htmlspecialchars($ticket['departure_time']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Arrival Time</span>
            <span class="value" id="arrival-time">
                <?= htmlspecialchars($ticket['arrival_time']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Gate</span>
            <span class="value" id="gate-number">
                <?= htmlspecialchars($ticket['gate']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Confirmation Number</span>
            <span class="value">
                <?= htmlspecialchars($ticket['confirmation_number']) ?>
            </span>
        </div>

        <div class="row">
            <span class="label">Flight Status</span>
            <span class="status" id="flight-status">
                <?= htmlspecialchars($ticket['status']) ?>
            </span>
        </div>

    </div>
</div>
</body>
</html>