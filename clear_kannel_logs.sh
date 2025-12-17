#!/bin/bash

# Directory containing the log files
LOG_DIR="/home/derrick/kannel/logs"

# List of files to truncate
FILES=(
  "$LOG_DIR/bearerbox-access-voda.log"
  "$LOG_DIR/bearerbox-voda.log"
  "$LOG_DIR/kannel-voda.store"
  "$LOG_DIR/kannel-voda.store.bak"
  "$LOG_DIR/smsbox-access-vodacom.log"
  "$LOG_DIR/smsbox-vodacom.log"
)

echo "Truncating log files in $LOG_DIR ..."

for file in "${FILES[@]}"; do
  if [ -f "$file" ]; then
    > "$file"
    echo "Cleared: $file"
  else
    echo "File not found: $file"
  fi
done

echo "All done!"
