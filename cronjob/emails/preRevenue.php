<?php

// Start tracking the total time taken for the script to run
$startTime = microtime(true);

// Database credentials
$host = '192.168.1.11';
$db = 'afyacallproduction';
$user = 'prodafya';
$pass = 'Afyacall@2021qazWSX';
$port = '3306';

try {
    // Create a connection to the database
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get the previous day's date range (start and end of the day)
    $previousDate = date('Y-m-d', strtotime('-1 day'));
    $startDate = $previousDate . ' 00:00:00';
    $endDate = $previousDate . ' 23:59:59';

    // Track the time taken for the query execution
    $queryStartTime = microtime(true);

    // Prepare and execute the optimized SQL query using BETWEEN
    $stmt = $pdo->prepare("
        SELECT SUM(amount_IN) AS revenue
        FROM transactions
        WHERE created_at BETWEEN :startDate AND :endDate
        AND status = 1
    ");
    $stmt->execute(['startDate' => $startDate, 'endDate' => $endDate]);

    // Measure and output query execution time
    $queryEndTime = microtime(true);
    $queryTimeTaken = $queryEndTime - $queryStartTime;
    // echo "Query execution time: " . round($queryTimeTaken, 4) . " seconds\n"; // Optional for debugging

    // Fetch the result
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenue = $result['revenue'] ?? 0;

    // Format the revenue amount for display
    $revenueAmount = number_format(round($revenue, 2), 2, '.', ',');

    // Display the previous day's revenue
    echo "Yesterday's revenue ({$previousDate}) is: $revenueAmount\n";

} catch (PDOException $e) {
    // Handle database connection error
    echo "Database connection failed: " . $e->getMessage();
}

// Stop tracking the total time taken
$endTime = microtime(true);
$totalTimeTaken = $endTime - $startTime;

// Convert total time taken to minutes for display
$totalTimeTakenMinutes = $totalTimeTaken / 60;

// Optional: Display the total script execution time
 echo "Total time taken to display the result: " . round($totalTimeTakenMinutes, 4) . " minutes\n"; // Optional for debugging

?>
