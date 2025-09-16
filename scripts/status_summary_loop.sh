#!/bin/bash

export TZ="Africa/Dar_es_Salaam"
# Scheduled hours: 24-hour format
SCHEDULE=("00:00" "03:30" "07:00" "10:30" "14:00" "17:30" "21:00" "23:58")

while true; do
  NOW=$(date +%H:%M)

  # Find the next scheduled time
  NEXT=""
  for T in "${SCHEDULE[@]}"; do
    if [[ "$T" > "$NOW" ]]; then
      NEXT="$T"
      break
    fi
  done

  # If none left today, pick the first schedule for tomorrow
  if [[ -z "$NEXT" ]]; then
    NEXT="${SCHEDULE[0]}"
    TOMORROW=true
  else
    TOMORROW=false
  fi

  # Calculate seconds until next run
  if [ "$TOMORROW" = true ]; then
    TARGET=$(date -d "tomorrow $NEXT" +%s)
  else
    TARGET=$(date -d "today $NEXT" +%s)
  fi

  NOW_SEC=$(date +%s)
  SLEEP_SEC=$((TARGET - NOW_SEC))

  echo "Next run at $NEXT (in $SLEEP_SEC seconds)..."
  sleep $SLEEP_SEC

  # Run your PHP script
  /usr/local/bin/php /app/scripts/send_Transactions_status_summary.php >> /var/log/status_sms.log 2>&1
done
# Note: This script runs indefinitely. To stop it, you will need to terminate the process manually.