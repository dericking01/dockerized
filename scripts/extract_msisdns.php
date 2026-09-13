#!/usr/bin/php
<?php

$inputFile = '/home/derrick/files/NEW_LAKE_SP_CLEAN_not_in_base.csv';
$outputFile = '/home/derrick/files/13_SEPT_LAKE_SP_IVR.csv';
// 27i_JULY_SMS_INACTIVE_60_DAYS.csv promo base ended at 240001
// 24_EXTRACT_AUG_INACTIVE_30_DAYS_IVR_PROMO.csv promo base ended at 50000
// 24_EXTRACT_AUGUST_SMS_INACTIVE_30_DAYS.csv promo base ended at 50000
$start = 1600001; // Starting from this line
$limit = 1500000; // Limit to this many lines 23_AUG_LAKE_two_IVR
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
