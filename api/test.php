<?php

require_once 'key.php';
require_once 'api.php';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$airports = $api->getAirports();

$airlines = $api->getAirlines();

$flights = $api->searchFlights(
    ['status' => 'boarding'],
    null,
    'desc'
);
?>