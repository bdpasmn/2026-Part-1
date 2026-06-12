<?php
require_once 'db.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS connection_test (
        id SERIAL PRIMARY KEY,
        message TEXT,
        connected_at TIMESTAMPTZ DEFAULT NOW()
    )
");

$stmt = $pdo->prepare("INSERT INTO connection_test (message) VALUES (?) RETURNING *");
$stmt->execute(['Connected from PHP on XAMPP!']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);


echo "<h2>You did it!!!! Row inserted:</h2>";
echo "<pre>";
print_r($row);
echo "</pre>";
?>