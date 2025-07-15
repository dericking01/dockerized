#!/bin/bash

# Directory containing logs
LOG_DIR="/root/cronjob/emails/scripts/logs"
ARCHIVE_DIR="$LOG_DIR/archive"

# Create archive directory if not exists
mkdir -p "$ARCHIVE_DIR"

# Get current timestamp
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")

# Loop through logs and rotate
for logfile in "$LOG_DIR"/*.txt; do
    [ -e "$logfile" ] || continue  # skip if no .txt files
    filename=$(basename "$logfile")
    
    # Move to archive folder with timestamp
    mv "$logfile" "$ARCHIVE_DIR/${filename%.txt}_$TIMESTAMP.txt"
    
    # Recreate empty log
    touch "$logfile"
    chmod 644 "$logfile"
    echo "✅ Rotated $filename"
done

# Optional: Clean logs older than 60 days
find "$ARCHIVE_DIR" -type f -name "*.txt" -mtime +60 -exec rm {} \;
