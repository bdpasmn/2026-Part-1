<?php
require_once "api.php";
require_once "key.php";

$api = new AirportsAPI(AIRPORTS_API_KEY);

$flight = $api->getFlightById("6a616790e2522dcb77b129b0");

echo "<pre>";
print_r($flight);
echo "</pre>";

?>