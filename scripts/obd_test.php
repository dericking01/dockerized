#!/usr/bin/php
<?php

// CONFIGURATION
$csvFile = '/home/derrick/files/1000OBD_02_OCT.csv';  // <-- update path if needed
$endpointBase = "http://192.168.1.49:80/callfile/callfile.php";
$account = 1234;
$chunkSize = 50;       // process 50 MSISDNs per batch
$tps = 20;             // target throughput per second
$sleepPerBatch = ceil($chunkSize / $tps);  // seconds to pause between batches

date_default_timezone_set('Africa/Dar_es_Salaam');

// STARTUP
$startTime = microtime(true);
$startDate = date("Y-m-d H:i:s");

if (!file_exists($csvFile)) {
    echo "❌ CSV file not found: {$csvFile}\n";
    exit(1);
}

$handle = fopen($csvFile, 'r');
if (!$handle) {
    echo "❌ Failed to open CSV file.\n";
    exit(1);
}

// Try to find header "MSISDN"
$headers = fgetcsv($handle);
$msisdnIndex = array_search('MSISDN', $headers);
if ($msisdnIndex === false) {
    echo "❌ 'MSISDN' column not found.\n";
    fclose($handle);
    exit(1);
}

$totalProcessed = 0;
$totalSuccess = 0;
$totalFailed = 0;
$batch = [];
$batchCount = 0;

echo "🚀 Starting push job at {$startDate}\n";

while (($row = fgetcsv($handle)) !== false) {
    $msisdn = trim($row[$msisdnIndex] ?? '');
    if (!preg_match('/^255\d{9}$/', $msisdn)) {
        echo "⚠️ Skipping invalid MSISDN: {$msisdn}\n";
        continue;
    }
    $batch[] = $msisdn;

    if (count($batch) >= $chunkSize) {
        $batchCount++;
        echo "\n📦 Processing batch {$batchCount} (" . count($batch) . " MSISDNs)\n";
        processBatch($batch, $endpointBase, $account, $totalSuccess, $totalFailed);
        $totalProcessed += count($batch);
        $batch = [];
        echo "⏸️ Throttling {$sleepPerBatch}s for TPS control...\n";
        sleep($sleepPerBatch);
    }
}

// Final leftover batch
if (!empty($batch)) {
    $batchCount++;
    echo "\n📦 Processing final batch {$batchCount} (" . count($batch) . " MSISDNs)\n";
    processBatch($batch, $endpointBase, $account, $totalSuccess, $totalFailed);
    $totalProcessed += count($batch);
}

fclose($handle);

// WRAP-UP
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);
$formatted = gmdate("H:i:s", $duration);

echo "\n🎉 ALL DONE!\n";
echo "📱 Total MSISDNs processed: {$totalProcessed}\n";
echo "✅ Success: {$totalSuccess}\n";
echo "❌ Failed: {$totalFailed}\n";
echo "⏱️ Started: {$startDate}\n";
echo "🏁 Ended:   " . date("Y-m-d H:i:s") . "\n";
echo "🕓 Duration: {$formatted} ({$duration}s)\n";
echo "⚡ Average rate: " . round($totalProcessed / $duration, 2) . " requests/sec\n";


// =========================================================
// FUNCTION: Process one batch of MSISDNs concurrently
// =========================================================
function processBatch($batch, $endpointBase, $account, &$totalSuccess, &$totalFailed)
{
    $multiHandle = curl_multi_init();
    $curlHandles = [];

    foreach ($batch as $msisdn) {
        $url = "{$endpointBase}?msisdn={$msisdn}&account={$account}&MaxRetries=3&RetryTime=1&WaitTime=1";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $curlHandles[$msisdn] = $ch;
        curl_multi_add_handle($multiHandle, $ch);
    }

    // Execute concurrent requests
    do {
        $status = curl_multi_exec($multiHandle, $active);
        curl_multi_select($multiHandle);
    } while ($active && $status == CURLM_OK);

    // Process responses
    foreach ($curlHandles as $msisdn => $ch) {
        $response = curl_multi_getcontent($ch);
        if (curl_errno($ch)) {
            echo "❌ Failed: {$msisdn} - " . curl_error($ch) . "\n";
            $totalFailed++;
        } else {
            echo "✅ Success: {$msisdn}\n";
            $totalSuccess++;
        }
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);
}
