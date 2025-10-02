#!/bin/bash

# Local file to copy
LOCAL_FILE="/home/derrick/files/17_SEPT_IVR_via_sms_1_point_5_M.csv"

# Remote server details
REMOTE_USER="derrick"
REMOTE_HOST="192.168.1.10"
REMOTE_PASS="your_password_here"
REMOTE_DIR="/home/derrick/pythonApp/files/input/"

# Use sshpass + scp to copy the file
sshpass -p "$REMOTE_PASS" scp "$LOCAL_FILE" "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}"
