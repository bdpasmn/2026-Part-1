<?php

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

require_once '../../../api/key.php';
require_once '../../../api/api.php';

$flightId = trim($_GET['flight_id'] ?? '');
$carryOn  = (int)($_GET['carry_on'] ?? 0);
$checked  = (int)($_GET['checked'] ?? 0);

if ($flightId === '') {
    echo json_encode(['error' => 'Flight ID is required.']);
    exit;
}

$api = new AirportsAPI(AIRPORTS_API_KEY);
$flight = $api->getFlightById($flightId);

if (!$flight) {
    echo json_encode(['error' => 'Flight ID not found.']);
    exit;
}

function flightDestination(array $f, array $airportLookup): string {

    $code =
        $f['departingTo']
        ?? $f['landingAt']
        ?? $f['destination']
        ?? $f['arrivalCode']
        ?? $f['arrival']
        ?? '';

    if (!$code) {
        return '—';
    }

    $airport = $airportLookup[strtolower($code)] ?? null;

    if ($airport) {
        $city = $airport['city']
             ?? $airport['cityName']
             ?? $airport['location']
             ?? '';

        if ($city !== '') {
            return $city . ' (' . strtoupper($code) . ')';
        }
    }

    return strtoupper($code);
}

$airportResults = $api->getAirports();
$airports = $airportResults['airports'] ?? [];
$airportLookup = [];
foreach ($airports as $airport) {
    $airportLookup[strtolower($airport['shortName'])] = $airport;
}

$destination  = flightDestination($flight, $airportLookup);
$flightNumber = $flight['flightNumber'] ?? $flight['flight_number'] ?? '—';
$airline      = $flight['airline'] ?? '—';

$seatPrice = 0.0;
foreach (['seatPrice', 'price', 'baseFare', 'fare'] as $key) {
    if (isset($flight[$key]) && is_numeric($flight[$key])) {
        $seatPrice = (float)$flight[$key];
        break;
    }
}

$bagCost = 0.0;

if ($carryOn >= 2) {
    $bagCost += 30.0;
}

$checkedFeeTable = [
    0 => 0.0,
    1 => 0.0,
    2 => 50.0,
    3 => 150.0,
    4 => 250.0,
    5 => 350.0,
];
if (isset($checkedFeeTable[$checked])) {
    $bagCost += $checkedFeeTable[$checked];
} elseif ($checked > 5) {
    $bagCost += 50.0 + (($checked - 2) * 100.0);
}

$total = $seatPrice + $bagCost;

echo json_encode([
    'flightNumber' => $flightNumber,
    'airline'      => $airline,
    'destination'  => $destination,
    'seatPrice'    => round($seatPrice, 2),
    'bagCost'      => round($bagCost, 2),
    'total'        => round($total, 2),
]);
exit;