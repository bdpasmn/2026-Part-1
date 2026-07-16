<?php
require_once "api.php";
require_once "key.php";

$api = new AirportsAPI(AIRPORTS_API_KEY);

$response = $api->getFlights();

echo "<pre>";
print_r($response);
echo "</pre>";
?>