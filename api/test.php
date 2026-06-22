<?php
require_once "api.php";
require_once "key.php";

$api = new AirportsAPI(AIRPORTS_API_KEY);

$flightId = "6a3b56405ba90667e01cc88d";

$response = $api->getNoFlyList();



echo "<pre>";
print_r($response);
echo "</pre>";
?>