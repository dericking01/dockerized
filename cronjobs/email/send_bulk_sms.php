#!/usr/bin/php
<?php

// Configuration
$csvFile = '/home/derrick/files/output_250k_to_550k.csv';
$message = "Habari! Mimi ni daktari wako wa mtandaoni. Chati nami wakati wowote ili ufahamu zaidi kuhusu afya yako. Je, unajisikiaje leo?";
$maxRetries = 3;
$delayMicroseconds = 11000; // 11ms = ~90 TPS
$chunkSize = 5000;
$pauseBetweenChunksSeconds = 30;

// Open CSV
if (!file_exists($csvFile)) {
    echo "❌ CSV file not found: {$csvFile}\n";
    exit(1);
}

$handle = fopen($csvFile, 'r');
if ($handle === false) {
    echo "❌ Failed to open file.\n";
    exit(1);
}

// Read header
$headers = fgetcsv($handle);
$msisdnIndex = array_search('MSISDN', $headers);
if ($msisdnIndex === false) {
    echo "❌ 'msisdn' column not found in CSV.\n";
    fclose($handle);
    exit(1);
}

$totalSent = 0;
$chunkCounter = 0;

while (($row = fgetcsv($handle)) !== false) {
    $msisdn = trim($row[$msisdnIndex]);

    if (!preg_match('/^255\d{9}$/', $msisdn)) {
        echo "⚠️ Skipping invalid MSISDN: {$msisdn}\n";
        continue;
    }

    $url = 'http://192.168.1.200:6013/cgi-bin/sendsms?' . http_build_query([
        'username'  => 'afya',
        'password'  => 'Afya4017',
        'from'      => '15723',
        'to'        => $msisdn,
        'text'      => $message,
        'dlr-mask'  => 31,
        'dlr-url'   => "https://192.168.1.200:5443/api/sms/dailydeliveryreport?id={$msisdn}&status=%d",
    ]);

    $sent = false;
    $attempts = 0;

    while (!$sent && $attempts < $maxRetries) {
        $attempts++;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "❌ Attempt {$attempts}: Failed to send to {$msisdn}: " . curl_error($ch) . "\n";
        } else {
            echo "✅ Sent to {$msisdn} (Attempt {$attempts})\n";
            $sent = true;
            $totalSent++;
        }

        curl_close($ch);

        if (!$sent && $attempts < $maxRetries) {
            usleep(200000); // Wait 200ms before retry
        }
    }

    usleep($delayMicroseconds); // Throttle TPS
    $chunkCounter++;

    if ($chunkCounter >= $chunkSize) {
        echo "🕒 Pausing for {$pauseBetweenChunksSeconds} seconds...\n";
        sleep($pauseBetweenChunksSeconds);
        $chunkCounter = 0;
    }
}

fclose($handle);

echo "🎉 Completed! Total successful SMS sent: {$totalSent}\n";
