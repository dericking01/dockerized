#!/bin/bash

# Local file to copy
LOCAL_FILE="/home/derrick/pythonApp/files/output/subscribers_not_on_921465_P02.csv"

# Remote server details
REMOTE_USER="derrick"
REMOTE_HOST="192.168.1.200"
REMOTE_PASS=".."  # Replace with actual password
REMOTE_DIR="/home/derrick/"

echo "Copying '$LOCAL_FILE' to '${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}' ..."
# Use sshpass + scp to copy the file
sshpass -p "$REMOTE_PASS" scp "$LOCAL_FILE" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"

echo "File copy completed."