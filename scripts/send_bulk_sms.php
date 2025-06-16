#!/usr/bin/php
<?php

// CONFIGURATION
$csvFile = '/home/derrick/files/output_2280000_to_2380000.csv';
$message = "USIKUBALI changamoto za maisha zikuathiri. Kuongea ni hatua ya kupona. Chat nami, Daktari wako wa kidigitali toka Afyacall-Vodacom. Jibu “TUCHATI”";
$smsboxPorts = [6013, 6014, 6015];
$concurrency = 30; // parallel requests per batch
$chunkSize = 5000;
$pauseBetweenChunks = 30; // seconds
$maxRetries = 3;

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

$msisdns = [];
while (($row = fgetcsv($handle)) !== false) {
    $msisdn = trim($row[$msisdnIndex]);
    if (preg_match('/^255\d{9}$/', $msisdn)) {
        $msisdns[] = $msisdn;
    } else {
        echo "⚠️ Skipping invalid MSISDN: {$msisdn}\n";
    }
}
fclose($handle);

$totalSent = 0;
$totalFailed = 0;
$portCount = count($smsboxPorts);
$batchCount = 0;

foreach (array_chunk($msisdns, $chunkSize) as $chunk) {
    echo "🚀 Sending batch " . (++$batchCount) . " of " . count($chunk) . " recipients...\n";
    
    for ($i = 0; $i < count($chunk); $i += $concurrency) {
        $multiHandle = curl_multi_init();
        $curlHandles = [];

        $batch = array_slice($chunk, $i, $concurrency);

        foreach ($batch as $key => $msisdn) {
            $port = $smsboxPorts[($i + $key) % $portCount];

            $url = "http://192.168.1.200:{$port}/cgi-bin/sendsms?" . http_build_query([
                'username'  => 'afya',
                'password'  => 'Afya4017',
                'from'      => '15723',
                'to'        => $msisdn,
                'text'      => $message,
                'dlr-mask'  => 31,
                'dlr-url'   => "https://192.168.1.200:5443/api/sms/dailydeliveryreport?id={$msisdn}&status=%d",
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $curlHandles[$msisdn] = $ch;
            curl_multi_add_handle($multiHandle, $ch);
        }

        // Execute all requests concurrently
        do {
            $status = curl_multi_exec($multiHandle, $active);
            curl_multi_select($multiHandle);
        } while ($active && $status == CURLM_OK);

        // Process results
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
        usleep(100000); // small pause to prevent TPS spike
    }

    echo "🕒 Chunk done. Pausing for {$pauseBetweenChunks}s...\n";
    sleep($pauseBetweenChunks);
}

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