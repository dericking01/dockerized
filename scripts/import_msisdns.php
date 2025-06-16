#!/usr/bin/php
<?php

$host = '192.168.1.11';
$db = 'afyacallproduction';
$user = 'derrickdb';
$password = 'Derrick#@!2023';
$table = 'bot_campaigns';

$csvPath = '/home/derrick/files/output_2280000_to_2380000.csv';

// Connect to the database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to DB ✅\n";
} catch (PDOException $e) {
    die("Connection failed ❌: " . $e->getMessage());
}

// Read CSV
if (!file_exists($csvPath)) {
    die("CSV file not found: $csvPath\n");
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    die("Failed to open CSV file.\n");
}

// Skip header if it exists
$header = fgetcsv($handle);

$count = 0;
$now = date('Y-m-d H:i:s');

// Prepare the insert statement
$stmt = $pdo->prepare("
    INSERT INTO $table (msisdn, status, created_at, updated_at, deleted_at)
    VALUES (:msisdn, :status, :created_at, :updated_at, :deleted_at)
");


while (($row = fgetcsv($handle)) !== false) {
    $msisdn = trim($row[0]);
    if ($msisdn === '') continue;

    try {
        $stmt->execute([
            ':msisdn' => $msisdn,
            ':status' => '0',
            ':created_at' => $now,
            ':updated_at' => null,
            ':deleted_at' => null,
        ]);
        $count++;
        if ($count % 1000 === 0) echo "Inserted: $count\n";
    } catch (Exception $e) {
        echo "❌ Error inserting $msisdn: " . $e->getMessage() . "\n";
    }
}

fclose($handle);

echo "✅ Import complete. Total inserted: $count\n";
