<?php
require_once '../../../api/key.php';
require_once '../../../api/api.php';

header('Content-Type: application/json');

$flightId = trim($_GET['flight_id'] ?? '');
$carryOn  = (int)($_GET['carry_on'] ?? 0);
$checked  = (int)($_GET['checked'] ?? 0);

if (!$flightId) {
    echo json_encode(['error' => 'Flight ID is required.']);
    exit;
}

$api = new AirportsAPI(AIRPORTS_API_KEY);
$flight = $api->getFlightById($flightId);

if (!$flight) {
    echo json_encode(['error' => 'Flight not found.']);
    exit;
}

// Seat price: pick a class — economy is the default seat class
$seatClass = 'economy'; // adjust if you add a seat-class selector
$seatPrice = $flight['seats'][$seatClass]['priceDollars'] ?? 0;

// Baggage price from API's "prices" arrays (cumulative — sum first N elements)
function bagCost(array $bagType, int $count): float {
    if ($count <= 0) return 0;
    $prices = $bagType['prices'] ?? [];
    $max = $bagType['max'] ?? count($prices);
    $count = min($count, $max);
    return array_sum(array_slice($prices, 0, $count));
}

$carryCost   = bagCost($flight['baggage']['carry']   ?? [], $carryOn);
$checkedCost = bagCost($flight['baggage']['checked'] ?? [], $checked);
$bagCostTotal = $carryCost + $checkedCost;

$total = $seatPrice + $bagCostTotal;

echo json_encode([
    'flightNumber' => $flight['flightNumber'] ?? '',
    'airline'      => $flight['airline'] ?? '',
    'destination'  => $flight['departingTo'] ?? $flight['landingAt'] ?? '',
    'seatPrice'    => $seatPrice,
    'bagCost'      => $bagCostTotal,
    'total'        => $total,
]);