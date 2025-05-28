#!/bin/bash

THRESHOLD=100 # Set the threshold for disk usage percentage

# Get the current disk usage percentage
DISK_USAGE=$(df -h | awk '$NF=="/"{printf "%d\n", $5}')

# Check if the disk usage percentage exceeds the threshold
if [ "$DISK_USAGE" -ge "$THRESHOLD" ]; then
response=$(curl -X POST -d "key=Uncxy7VvNcUjdfjLLdkfjdsdssxcfXX&space=$THRESHOLD" http://192.168.1.49/callfile/alert.php)   
fi

