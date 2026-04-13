#!/bin/bash

# Local path to copy (file or directory)
LOCAL_PATH="/home/derrick/pythonApp/files/output/03-Base-clean/"

# Remote server details
REMOTE_USER="derrick"
REMOTE_HOST="192.168.1.200"
REMOTE_PASS=".."  # Replace with actual password
REMOTE_DIR="/home/derrick/"

if [ ! -e "$LOCAL_PATH" ]; then
	echo "Error: local path '$LOCAL_PATH' does not exist."
	exit 1
fi

echo "Copying $LOCAL_PATH to ${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}..."

# Use recursive copy only when the source is a directory
if [ -d "$LOCAL_PATH" ]; then
	sshpass -p "$REMOTE_PASS" scp -r "$LOCAL_PATH" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
else
	sshpass -p "$REMOTE_PASS" scp "$LOCAL_PATH" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
fi

echo "Copy completed."