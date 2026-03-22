#!/usr/bin/php
<?php

$inputFile = '/home/derrick/files/01_OCT_non_SMS_subscribers.csv';
$outputFile = '/home/derrick/files/22_MAR_2026_ivr.csv';

$start = 1900001; // Starting from this line
$limit = 9000000; // Limit to this many lines
$currentLine = 0;
$written = 0;

$in = fopen($inputFile, 'r');
if (!$in) {
    die("Failed to open input file\n");
}

$out = fopen($outputFile, 'w');
if (!$out) {
    fclose($in);
    die("Failed to open output file\n");
}

// Read header
$header = fgetcsv($in);
if ($header === false || !in_array('MSISDN', $header)) {
    fclose($in);
    fclose($out);
    die("Header missing or MSISDN column not found\n");
}

// Write header to output
fputcsv($out, $header);

// Find MSISDN column index
$msisdnIndex = array_search('MSISDN', $header);

while (($data = fgetcsv($in)) !== false) {
    $currentLine++;

    if ($currentLine <= $start) {
        continue;
    }

    if ($written >= $limit) {
        break;
    }

    fputcsv($out, $data);
    $written++;
}

fclose($in);
fclose($out);

echo "Done! Extracted $written MSISDNs to $outputFile\n";
