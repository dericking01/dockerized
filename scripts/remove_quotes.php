<?php
// File paths
$inputFile  = __DIR__ . '/AFYACALL-JULY-BASE.csv';
$outputFile = __DIR__ . '/AFYACALL-JULY-BASE-clean.csv';

// Read the file contents
if (!file_exists($inputFile)) {
    die("Input file not found.\n");
}

$content = file_get_contents($inputFile);

// Remove all double quotes
$cleaned = str_replace('"', '', $content);

// Save cleaned content to new file
if (file_put_contents($outputFile, $cleaned) !== false) {
    echo "Quotes removed successfully. New file saved as: $outputFile\n";
} else {
    echo "Failed to write cleaned file.\n";
}
