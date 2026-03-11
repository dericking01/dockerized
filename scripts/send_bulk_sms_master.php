#!/usr/bin/php
<?php

// CONFIGURATION
$smsboxPorts = [6016, 6017, 6018];
$concurrency = 11; // Parallel requests per batch (~160 general TPS with current infra)
$chunkSize = 5000;
$maxRetries = 3;
date_default_timezone_set('Africa/Dar_es_Salaam');

// FILETYPE -> MESSAGE MAP
$fileTypeMessageMap = [
    'p02' => 'Umejiunga na Afyacall lakini bado hujazungumza na daktari? Pata ushauri wa afya bure wa kitaalamu ndani ya kifurushi chako. Jibu 1 kwa maongezi ya faragha',
    'ivr' => 'Kukosa usingizi, mawazo mengi au uchovu usioisha vinaweza kuwa dalili za msongo wa mawazo. Pata mwanga wa daktari kwa kusikiliza dondoo za afya. Jibu 2 kujiunga',
    'sms' => 'Mawazo mengi, kukosa usingizi au kupoteza hamu ya kula si kawaida kila mara. Fahamu kinachoendelea na afya yako. Jibu 3 kuchat na mimi',
];

$csvFiles = [];

if ($argc >= 2) {
    $csvFile = $argv[1];

    if (!file_exists($csvFile)) {
        echo "ERROR: CSV file not found: {$csvFile}\n";
        exit(1);
    }

    $csvFiles[] = $csvFile;
} else {
    $defaultFolder = '/home/derrick/files';
    $csvFiles = discoverCsvFiles($defaultFolder, $fileTypeMessageMap);

    if (empty($csvFiles)) {
        echo "ERROR: No matching CSV files found in {$defaultFolder}.\n";
        echo "Supported filename markers: p02, 921465, ivr, sms\n";
        exit(1);
    }

    echo "INFO: Auto-dispatch mode enabled. Found " . count($csvFiles) . " matching CSV file(s) in {$defaultFolder}.\n";
}

$grandTotalSent = 0;
$grandTotalFailed = 0;
$globalStartTime = microtime(true);
$globalStartDate = date('Y-m-d H:i:s');

foreach ($csvFiles as $index => $csvFile) {
    $fileType = detectFileType($csvFile);
    if ($fileType === null) {
        echo "WARN: Skipping unsupported CSV filename type: {$csvFile}\n";
        continue;
    }

    $message = $fileTypeMessageMap[$fileType];

    echo "\n==================================================\n";
    echo 'INFO: Dispatching file ' . ($index + 1) . '/' . count($csvFiles) . "\n";
    echo "INFO: File type detected: {$fileType}\n";
    echo "INFO: Using CSV: {$csvFile}\n";
    echo "INFO: Starting send process...\n";

    $fileTotals = processCsvFile($csvFile, $smsboxPorts, $message, $concurrency, $chunkSize);

    $grandTotalSent += $fileTotals['sent'];
    $grandTotalFailed += $fileTotals['failed'];
}

$globalEndTime = microtime(true);
$globalDuration = round($globalEndTime - $globalStartTime, 2);
$globalFormatted = gmdate('H:i:s', (int)$globalDuration);
$globalRate = $globalDuration > 0 ? round($grandTotalSent / $globalDuration, 2) : 0;

echo "\n==================================================\n";
echo "ALL FILES DONE!\n";
echo "Total Sent: {$grandTotalSent}\n";
echo "Total Failed: {$grandTotalFailed}\n";
echo "Started at: {$globalStartDate}\n";
echo 'Ended at:   ' . date('Y-m-d H:i:s') . "\n";
echo "Duration:   {$globalDuration}s ({$globalFormatted})\n";
echo "Sent at rate: {$globalRate} messages/sec\n";

// FUNCTION: Process a single CSV using original send logic
function processCsvFile($csvFile, $smsboxPorts, $message, $concurrency, $chunkSize)
{
    $startTime = microtime(true);
    $startDate = date('Y-m-d H:i:s');

    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        echo "ERROR: Failed to open file.\n";
        return ['sent' => 0, 'failed' => 0];
    }

    $headers = fgetcsv($handle);
    $msisdnIndex = is_array($headers) ? array_search('MSISDN', $headers, true) : false;
    if ($msisdnIndex === false) {
        echo "ERROR: 'MSISDN' column not found.\n";
        fclose($handle);
        return ['sent' => 0, 'failed' => 0];
    }

    $totalSent = 0;
    $totalFailed = 0;
    $chunk = [];
    $chunkIndex = 0;

    while (($row = fgetcsv($handle)) !== false) {
        $msisdn = trim((string)($row[$msisdnIndex] ?? ''));
        if (!preg_match('/^255\d{9}$/', $msisdn)) {
            echo "WARN: Skipping invalid MSISDN: {$msisdn}\n";
            continue;
        }

        $chunk[] = $msisdn;

        if (count($chunk) >= $chunkSize) {
            echo 'INFO: Processing chunk ' . (++$chunkIndex) . ' of ' . count($chunk) . " numbers...\n";
            processChunk($chunk, $smsboxPorts, $message, $concurrency, $totalSent, $totalFailed);
            $chunk = [];
        }
    }

    if (!empty($chunk)) {
        echo 'INFO: Processing final chunk ' . (++$chunkIndex) . ' of ' . count($chunk) . " numbers...\n";
        processChunk($chunk, $smsboxPorts, $message, $concurrency, $totalSent, $totalFailed);
    }

    fclose($handle);

    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    $formatted = gmdate('H:i:s', (int)$duration);
    $sentRate = $duration > 0 ? round($totalSent / $duration, 2) : 0;

    echo "\nFILE DONE!\n";
    echo "Total Sent: {$totalSent}\n";
    echo "Total Failed: {$totalFailed}\n";
    echo "Started at: {$startDate}\n";
    echo 'Ended at:   ' . date('Y-m-d H:i:s') . "\n";
    echo "Duration:   {$duration}s ({$formatted})\n";
    echo "Sent at rate: {$sentRate} messages/sec\n";

    return ['sent' => $totalSent, 'failed' => $totalFailed];
}

// FUNCTION: Discover all CSV files with supported file type markers
function discoverCsvFiles($folderPath, $fileTypeMessageMap)
{
    if (!is_dir($folderPath)) {
        return [];
    }

    $result = [];
    $entries = scandir($folderPath);
    if ($entries === false) {
        return [];
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $fullPath = rtrim($folderPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $entry;
        if (!is_file($fullPath)) {
            continue;
        }

        if (strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) !== 'csv') {
            continue;
        }

        $detectedType = detectFileType($fullPath);
        if ($detectedType !== null && array_key_exists($detectedType, $fileTypeMessageMap)) {
            $result[] = $fullPath;
        }
    }

    sort($result);
    return $result;
}

// FUNCTION: Detect file type from CSV filename
function detectFileType($csvFile)
{
    $name = strtolower(basename($csvFile));

    if (strpos($name, 'p02') !== false || strpos($name, '921465') !== false) {
        return 'p02';
    }

    if (strpos($name, 'ivr') !== false) {
        return 'ivr';
    }

    if (strpos($name, 'sms') !== false) {
        return 'sms';
    }

    return null;
}

// FUNCTION: Send SMS chunk using curl_multi
function processChunk($chunk, $smsboxPorts, $message, $concurrency, &$totalSent, &$totalFailed)
{
    $portCount = count($smsboxPorts);

    for ($i = 0; $i < count($chunk); $i += $concurrency) {
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        $batch = array_slice($chunk, $i, $concurrency);

        foreach ($batch as $key => $msisdn) {
            $port = $smsboxPorts[($i + $key) % $portCount];

            $url = 'http://192.168.1.10:' . $port . '/cgi-bin/sendsms?' . http_build_query([
                'username' => 'afya',
                'password' => 'Afya4017',
                'from' => '15723',
                'to' => $msisdn,
                'text' => $message,
                'dlr-mask' => 31,
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $curlHandles[$msisdn] = $ch;
            curl_multi_add_handle($multiHandle, $ch);
        }

        // Execute concurrent requests
        do {
            $status = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);
        } while ($active && $status === CURLM_OK);

        // Process responses
        foreach ($curlHandles as $msisdn => $ch) {
            $response = curl_multi_getcontent($ch);
            if (curl_errno($ch)) {
                echo 'FAILED to send to ' . $msisdn . ': ' . curl_error($ch) . "\n";
                $totalFailed++;
            } else {
                echo "SENT to {$msisdn}\n";
                $totalSent++;
            }

            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
            unset($response);
        }

        curl_multi_close($multiHandle);
        // Optional throttle
        // usleep(100000); // 0.1 second
    }
}
