<?php
    $host='aws-1-us-east-1.pooler.supabase.com';
    $port='5432';
    $dbname='postgres';
    $user='postgres.uekcvegjgdnqcvdfwcjc';
    $password='bdp@Smn2025!?';
    try {
        $pdo = new PDO(
            "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require",
            $user,
            $passworddb,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
?>








