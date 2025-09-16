#!/bin/bash
cron               # starts cron daemon in background
/app/scripts/status_summary_loop.sh  # runs loop in foreground
