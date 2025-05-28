<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

date_default_timezone_set("Africa/Nairobi");

// Start the timer
$startTime = microtime(true);

$host = '192.168.1.11';
$db = 'afyacallproduction';
$user = 'revenue';
$password = 'AfadBpA22w0d1cQvV';

$dsn = "mysql:host=$host;dbname=$db;charset=UTF8";

try {
    $pdo = new PDO($dsn, $user, $password);

    if ($pdo) {
        echo "Connected to the database successfully.\n";

        // Query to get the revenue for the previous day
        $sql = "SELECT SUM(amount_IN) FROM transactions WHERE DATE(created_at) = CURDATE() - INTERVAL 1 DAY AND status = 1";
        $statement = $pdo->query($sql);
        $row = $statement->fetch();
        $amount = $row[0];

        // Format the amount to number format with 2 decimal places
        $formattedAmount = number_format($amount, 2);
    }
} catch (PDOException $e) {
    echo $e->getMessage();
}

// Calculate the elapsed time
$endTime = microtime(true);
$executionTimeSeconds = $endTime - $startTime;

// Convert time to minutes and format it to 2 decimal places
$executionTimeMinutes = $executionTimeSeconds / 60;
$formattedExecutionTime = number_format($executionTimeMinutes, 2);

echo "Previous day's revenue is $formattedAmount\n";
echo "Time taken to display the result: $formattedExecutionTime minutes\n";
