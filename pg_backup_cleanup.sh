#!/bin/bash

# PostgreSQL Backup Cleanup Script
# Removes backup files older than 7 days on host and logs the cleanup process
# Usage: sudo -u postgres /usr/local/bin/pg_backup_cleanup.sh

# Configuration

BACKUP_DIR="/var/local_backups"
KEEP=7
LOG_FILE="/var/log/pg_backup_cleanup.log"

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" >> "${LOG_FILE}"
}

if [ "$(id -un)" != "postgres" ]; then
    echo "ERROR: Must run as postgres"
    exit 1
fi

if [ ! -d "${BACKUP_DIR}" ]; then
    log "ERROR: Backup directory missing"
    exit 1
fi

log "Starting backup cleanup"

FILES_TO_DELETE=$(find "${BACKUP_DIR}" -type f -name "afyacall_*.sql.gz.gpg" | sort | head -n -${KEEP})

if [ -z "${FILES_TO_DELETE}" ]; then
    log "No old backups to delete"
else
    echo "${FILES_TO_DELETE}" | while read -r file; do
        log "Deleting ${file}"
        rm -f "${file}"
    done
fi

log "Cleanup completed"