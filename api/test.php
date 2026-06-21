<?php
require_once "api.php";
require_once "key.php";

$api = new AirportsAPI(AIRPORTS_API_KEY);

$flightId = "6a3b56405ba90667e01cc88d";

$response = $api->searchFlights(
    ['flight_id' => $flightId],
    null,
    'desc'
);

$flight = $response['flights'][0] ?? null;

if (!$flight) {
    echo "Flight not found";
    exit;
}

echo "<pre>";
print_r($flight);
echo "</pre>";
?>