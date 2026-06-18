<?php
    require_once __DIR__ . '/../../database/db.php';

    header('Content-Type: application/json');

    $flightId = $_GET['flight_id'] ?? null;

    if (!$flightId) {
        echo json_encode(["error" => "Missing flight_id"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT taken_seats FROM \"Flights\" WHERE flight_id = ?");
    $stmt->execute([$flightId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $takenSeats = [];

    if ($row) {
        $takenSeats = json_decode($row['taken_seats'] ?? '[]', true);
        if (!is_array($takenSeats)) {
            $takenSeats = [];
        }
    }

    echo json_encode([
        "takenSeats" => $takenSeats
    ]);
?>