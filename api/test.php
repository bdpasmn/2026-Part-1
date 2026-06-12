<?php

require_once 'key.php';
require_once 'api.php';

$api = new AirportsAPI(AIRPORTS_API_KEY);

$result = $api->getNoFlyList();

echo "<pre>";


        print_r($result);


echo "</pre>";