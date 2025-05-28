#!/bin/bash

# Change to Laravel project directory
cd /var/www/html/afyacall || {
    echo "❌ Failed to cd into /var/www/html/afyacall. Directory not found."
    exit 1
}

# Log file path
log_file="/root/cronjob/emails/migration_logs.log"

# Capture the action and migration filename
action=$1
migration_file=$2

# Get current timestamp
timestamp=$(date '+%Y-%m-%d %H:%M:%S')

# Check if arguments are provided
if [ -z "$action" ] || [ -z "$migration_file" ]; then
  echo "❌ [$timestamp] Error: Missing arguments." | tee -a "$log_file"
  echo "Usage: $0 <action> <migration_filename.php>"
  echo "Actions: migrate | rollback | fresh"
  echo "Example: $0 migrate 2025_04_28_102630_create_bot_subscriptions_table.php"
  exit 1
fi

# Always prepend "database/migrations/" to the migration path
migration_path="database/migrations/$migration_file"

# Perform the requested action
case $action in
  migrate)
    echo "🚀 [$timestamp] Running migration: $migration_path" | tee -a "$log_file"
    php artisan migrate --path="$migration_path" 2>&1 | tee -a "$log_file"
    ;;

  rollback)
    echo "🔄 [$timestamp] Rolling back migration: $migration_path" | tee -a "$log_file"
    php artisan migrate:rollback --path="$migration_path" 2>&1 | tee -a "$log_file"
    ;;

  fresh)
    echo "🧹 [$timestamp] Fresh migrating the whole database..." | tee -a "$log_file"
    php artisan migrate:fresh 2>&1 | tee -a "$log_file"
    ;;

  *)
    echo "❌ [$timestamp] Error: Unknown action '$action'" | tee -a "$log_file"
    echo "Available actions: migrate | rollback | fresh"
    exit 1
    ;;
esac
