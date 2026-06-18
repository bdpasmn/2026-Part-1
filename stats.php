<?php
    require_once "./database/db.php";

    header('Content-Type: application/json');

    $stmt = $pdo->query('SELECT COUNT(*) FROM "Tickets"');
    $totalTickets = (int)$stmt->fetchColumn();

    $stmt = $pdo->query('SELECT COUNT(*) FROM "Users" WHERE role = \'Customer\'');
    $totalCustomers = (int)$stmt->fetchColumn();

    echo json_encode([
        "tickets" => $totalTickets,
        "customers" => $totalCustomers
    ]);
?>