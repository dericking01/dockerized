#!/usr/bin/php -q
<?php

$logDir = '/home/docker/kannel/logs';
$zipDir = '/home/docker/kannel/zip';

// Create zip directory if it doesn't exist
if (!is_dir($zipDir)) {
    mkdir($zipDir, 0755, true);
}

// Get all files in the logs directory
$files = glob($logDir . '/*');

foreach ($files as $file) {
    if (is_file($file)) {
        $filename = basename($file);
        $timestamp = date('Ymd_His');
        $zipFile = "$zipDir/{$filename}_{$timestamp}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($file, $filename);
            $zip->close();

            // Truncate original file
            file_put_contents($file, '');
        } else {
            error_log("Failed to create zip file: $zipFile");
        }
    }
}
