<?php

// Start tracking the total time taken for the script to run
$startTime = microtime(true);

// Database credentials
$host = '192.168.1.11';
$db = 'afyacallproduction';
$user = 'prodafya';
$pass = 'Afyacall@2021qazWSX';
$port = '3306';

// Create a connection to the database
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Successfully connected to the database.\n";
    
    // Get the current date and define start and end of the day for range filtering
    $currentDate = date('Y-m-d');
    $startDate = $currentDate . ' 00:00:00';
    $endDate = $currentDate . ' 23:59:59';

    // Track the time taken for the query execution
    $queryStartTime = microtime(true);

    // Prepare the optimized SQL query with BETWEEN clause
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
    echo "Query execution time: " . round($queryTimeTaken, 4) . " seconds\n";

    // Fetch the result
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenue = $result['revenue'] ?? 0;

    // Format the revenue amount
    $revenueAmount = number_format(round($revenue, 2), 2, '.', ',');

    // Display the current revenue
    echo "The current revenue for today ({$currentDate}) is: $revenueAmount\n";

} catch (PDOException $e) {
    // Handle any database connection errors
    echo "Database connection failed: " . $e->getMessage();
}

// Stop tracking the total time taken
$endTime = microtime(true);
$totalTimeTaken = $endTime - $startTime;

// Convert total time taken to minutes for display
$totalTimeTakenMinutes = $totalTimeTaken / 60;
echo "Total time taken to display the result: " . round($totalTimeTakenMinutes, 4) . " minutes\n";

?>
