#!/bin/bash

# File: kannel_outgoing_tps.sh
# Purpose: Monitor Kannel outgoing submit_sm TPS per second

LOG_PATH="/home/derrick/kannel/logs/bearerbox-voda.log"

tail -f "$LOG_PATH" | \
grep --line-buffered 'Sending PDU' | \
grep --line-buffered 'submit_sm' | \
awk '{
    split($2,t,":");
    ts = t[1] ":" t[2] ":" t[3];
    count[ts]++;
    if (last != ts) {
        print count[last], last;
        last = ts
    }
}'
