#!/bin/bash

# PostgreSQL Backup Cleanup Script
# Removes backup files older than 1 month

# Configuration
BACKUP_DIR="/var/local_backups"
RETENTION_DAYS=31  # 1 month = ~31 days
LOG_FILE="/var/log/pg_backup_cleanup.log"

# Ensure running as postgres user
if [ "$(id -un)" != "postgres" ]; then
    echo "ERROR: This script must be run as postgres user" >&2
    exit 1
fi

# Log function
log() {
    echo "$(date "+%Y-%m-%d %H:%M:%S") - $1" >> "${LOG_FILE}"
}

# Verify directory exists
if [ ! -d "${BACKUP_DIR}" ]; then
    log "ERROR: Backup directory ${BACKUP_DIR} does not exist"
    exit 1
fi

# Start cleanup
log "Starting cleanup of backup files older than ${RETENTION_DAYS} days in ${BACKUP_DIR}"

# Find and delete old backups
find "${BACKUP_DIR}" -name "drraha_*.sql.gz" -mtime +${RETENTION_DAYS} -exec rm -v {} \; >> "${LOG_FILE}" 2>&1

log "Cleanup completed successfully"
exit 0