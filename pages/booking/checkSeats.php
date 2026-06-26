<?php
    // Connect to the database and return a JSON response.
    require_once __DIR__ . '/../../database/db.php';

    header('Content-Type: application/json');

    // Get the requested flight ID.
    $flightId = $_GET['flight_id'] ?? null;

    if (!$flightId) {
        echo json_encode(["error" => "Missing flight_id"]);
        exit;
    }

    // Retrieve the flight's taken seats.
    $stmt = $pdo->prepare("SELECT taken_seats FROM \"Flights\" WHERE flight_id = ?");
    $stmt->execute([$flightId]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $takenSeats = [];

    // Decode the stored seat list.
    if ($row) {
        $takenSeats = json_decode($row['taken_seats'] ?? '[]', true);
        if (!is_array($takenSeats)) {
            $takenSeats = [];
        }
    }

    // Return the taken seats as JSON.
    echo json_encode([
        "takenSeats" => $takenSeats
    ]);
?>