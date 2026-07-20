#!/usr/bin/php
<?php

$inputFile = '/home/derrick/files/LAKE_NSP_BASE_CLEAN.csv';
$outputFile = '/home/derrick/files/20_JULY_LAKE_DR.csv';

$start = 2400001; // Starting from this line
$limit = 100000; // Limit to this many lines
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
