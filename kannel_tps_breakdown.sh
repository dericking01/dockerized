#!/bin/bash

# File: kannel_tps_breakdown.sh
# Purpose: Real-time breakdown of Kannel PDU types per second

LOG_PATH="/home/derrick/kannel/logs/bearerbox-voda.log"

tail -f "$LOG_PATH" | \
grep --line-buffered 'PDU' | \
awk '
{
    split($2, t, ":");
    ts = t[1] ":" t[2] ":" t[3];

    if (last != ts && last != "") {
        print last, "submit_sm=" submit[last], "deliver_sm=" deliver[last], "dlr=" dlr[last];
        delete submit[last]; delete deliver[last]; delete dlr[last];
        last = ts;
    }

    if ($0 ~ /submit_sm/) submit[ts]++;
    else if ($0 ~ /deliver_sm/) deliver[ts]++;
    else if ($0 ~ /delivery_sm/) dlr[ts]++;
    else {}
}'
# Note: This script assumes that the log entries contain the string 'PDU' for all types of PDUs.
# It will print the counts of submit_sm, deliver_sm, and delivery_sm per second.