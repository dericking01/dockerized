#!/bin/bash

while true; do
  /usr/local/bin/php /app/scripts/send_Transactions_status_summary.php >> /var/log/status_sms.log 2>&1
  sleep 12600   # 3.5 hours = 3*3600 + 1800 = 12600 seconds
done
# this script runs indefinitely, sending the status summary every 3.5 hours