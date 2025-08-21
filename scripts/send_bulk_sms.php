#!/usr/bin/php
<?php

// CONFIGURATION
$csvFile = '/home/derrick/files/test.csv';
$message = "Usisubiri dalili! Magonjwa hatari kama saratani huja mwilini kimya. Piga 0900011111 sasa chagua 2 upate elimu sahihi ya afya na uchambuzi wa magonjwa mbalimbali";
$smsboxPorts = [6016, 6017, 6018];
$concurrency = 11; // parallel requests per batch TPS 160
$chunkSize = 5000;
$maxRetries = 3;
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
    echo "❌ Failed to open file.\n";
    exit(1);
}

$headers = fgetcsv($handle);
$msisdnIndex = array_search('MSISDN', $headers);
if ($msisdnIndex === false) {
    echo "❌ 'MSISDN' column not found.\n";
    fclose($handle);
    exit(1);
}

// COUNTERS
$totalSent = 0;
$totalFailed = 0;
$chunk = [];
$chunkIndex = 0;

while (($row = fgetcsv($handle)) !== false) {
    $msisdn = trim((string)($row[$msisdnIndex] ?? ''));
    if (!preg_match('/^255\d{9}$/', $msisdn)) {
        echo "⚠️ Skipping invalid MSISDN: {$msisdn}\n";
        continue;
    }

    $chunk[] = $msisdn;

    if (count($chunk) >= $chunkSize) {
        echo "🚀 Processing chunk " . (++$chunkIndex) . " of " . count($chunk) . " numbers...\n";
        processChunk($chunk, $smsboxPorts, $message, $concurrency, $totalSent, $totalFailed);
        $chunk = [];
    }
}

// Final leftover chunk
if (!empty($chunk)) {
    echo "🚀 Processing final chunk " . (++$chunkIndex) . " of " . count($chunk) . " numbers...\n";
    processChunk($chunk, $smsboxPorts, $message, $concurrency, $totalSent, $totalFailed);
}

fclose($handle);

// WRAP-UP
$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);
$formatted = gmdate("H:i:s", $duration);

echo "\n🎉 ALL DONE!\n";
echo "📦 Total Sent: {$totalSent}\n";
echo "❌ Total Failed: {$totalFailed}\n";
echo "⏱️ Started at: {$startDate}\n";
echo "✅ Ended at:   " . date("Y-m-d H:i:s") . "\n";
echo "🕓 Duration:   {$duration}s ({$formatted})\n";
echo "📊 Sent at rate: " . round($totalSent / $duration, 2) . " messages/sec\n";

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

            $url = "http://192.168.1.10:{$port}/cgi-bin/sendsms?" . http_build_query([
                'username'  => 'afya',
                'password'  => 'Afya4017',
                'from'      => 'AFYACALL',
                'to'        => $msisdn,
                'text'      => $message,
                'dlr-mask'  => 31,
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
        } while ($active && $status == CURLM_OK);

        // Process responses
        foreach ($curlHandles as $msisdn => $ch) {
            $response = curl_multi_getcontent($ch);
            if (curl_errno($ch)) {
                echo "❌ Failed to send to {$msisdn}: " . curl_error($ch) . "\n";
                $totalFailed++;
            } else {
                echo "✅ Sent to {$msisdn}\n";
                $totalSent++;
            }
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        // Optional throttle
        // usleep(100000); // 0.1 second
    }
}
