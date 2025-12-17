#!/bin/bash

# Local file to copy
LOCAL_FILE="/home/derrick/pythonApp/files/output/11_NOV_IVR_inactive_customers_90days.csv"

# Remote server details
REMOTE_USER="derrick"
REMOTE_HOST="192.168.1.200"
REMOTE_PASS="@PwD"  # Replace with actual password
REMOTE_DIR="/home/derrick/"

# Use sshpass + scp to copy the file
sshpass -p "$REMOTE_PASS" scp "$LOCAL_FILE" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"