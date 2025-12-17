#!/bin/bash

# File: kannel_combined_tps.sh
# Purpose: Monitor total Kannel PDU TPS per second

LOG_PATH="/home/derrick/kannel/logs/bearerbox-voda.log"

tail -f "$LOG_PATH" | \
grep --line-buffered 'PDU' | \
awk '{
    split($2,t,":");
    ts = t[1] ":" t[2] ":" t[3];
    count[ts]++;
    if (last != ts) {
        print count[last], last;
        last = ts
    }
}'
# Note: This script assumes that the log entries contain the string 'PDU' for all types of PDUs.