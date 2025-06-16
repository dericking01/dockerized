#!/bin/bash

# PostgreSQL Backup Script
# Version: 1.2
# Description: Creates compressed backups of PostgreSQL database and transfers to remote server
# Usage: sudo -u postgres /usr/local/bin/pg_backup.sh

# Environment Configuration
export PGPASSFILE=~postgres/.pgpass
export PGUSER=postgres
export PGDATABASE=drraha

# Set strict error checking
set -o errexit
set -o nounset
set -o pipefail

# Configuration Variables
BACKUP_DIR="/var/local_backups"
DB_NAME="drraha"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${DB_NAME}_${TIMESTAMP}.sql.gz"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_FILE}"
REMOTE_USER="derrick"
REMOTE_HOST="192.168.1.10"
REMOTE_DIR="/var/LocalBackups"
LOG_FILE="/var/log/pg_backup.log"
RETENTION_DAYS=7
SSH_KEY="~postgres/.ssh/id_ed25519"

# Ensure running as postgres user
if [ "$(id -un)" != "postgres" ]; then
    echo "ERROR: This script must be run as postgres user" >&2
    exit 1
fi

# Logging Function
log() {
    local message="$1"
    echo "$(date "+%Y-%m-%d %H:%M:%S") - ${message}" >> "${LOG_FILE}"
}

# Verify backup directory exists
if [ ! -d "${BACKUP_DIR}" ]; then
    log "ERROR: Backup directory ${BACKUP_DIR} does not exist"
    exit 1
fi

# Verify write permissions
if [ ! -w "${BACKUP_DIR}" ]; then
    log "ERROR: No write permission in backup directory ${BACKUP_DIR}"
    exit 1
fi

log "Starting backup of ${DB_NAME}"

# Database Backup
log "Creating database dump..."
if ! pg_dump "${DB_NAME}" | gzip > "${BACKUP_PATH}"; then
    log "ERROR: Failed to create database dump"
    exit 1
fi

# Verify backup was created
if [ ! -f "${BACKUP_PATH}" ]; then
    log "ERROR: Backup file not created at ${BACKUP_PATH}"
    exit 1
fi

BACKUP_SIZE=$(du -h "${BACKUP_PATH}" | cut -f1)
log "Successfully created backup (${BACKUP_SIZE}): ${BACKUP_PATH}"

# Remote Transfer
log "Transferring backup to ${REMOTE_HOST}..."
# Split backup into 2GB chunks for large files
if [ $(du -m "${BACKUP_PATH}" | cut -f1) -gt 2000 ]; then
    log "Large backup detected, splitting into chunks..."
    split -b 2G "${BACKUP_PATH}" "${BACKUP_PATH}.part."
    
    for part in "${BACKUP_PATH}.part."*; do
        if ! scp -o StrictHostKeyChecking=no -i "${SSH_KEY}" \
            "${part}" \
            "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/"; then
            log "ERROR: Failed to transfer backup part ${part}"
            exit 1
        fi
    done
    
    # Reassemble on remote side
    ssh -i "${SSH_KEY}" "${REMOTE_USER}@${REMOTE_HOST}" \
        "cat ${REMOTE_DIR}/${BACKUP_FILE}.part.* > ${REMOTE_DIR}/${BACKUP_FILE} && rm ${REMOTE_DIR}/${BACKUP_FILE}.part.*"
else
    # Original transfer code for smaller files
    if ! scp -o StrictHostKeyChecking=no -i "${SSH_KEY}" \
        "${BACKUP_PATH}" \
        "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/"; then
        log "ERROR: Failed to transfer backup to ${REMOTE_HOST}"
        exit 1
    fi
fi

log "Backup successfully transferred to ${REMOTE_HOST}"

# Local Cleanup
log "Cleaning up old backups (keeping ${RETENTION_DAYS} days)..."
find "${BACKUP_DIR}" -name "${DB_NAME}_*.sql.gz" -mtime +${RETENTION_DAYS} -delete

# Remote Cleanup
if ! ssh -o StrictHostKeyChecking=no -i "${SSH_KEY}" \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "find ${REMOTE_DIR} -name '${DB_NAME}_*.sql.gz' -mtime +${RETENTION_DAYS} -delete"; then
    log "WARNING: Failed to clean up old backups on ${REMOTE_HOST}"
fi

log "Backup and cleanup completed successfully"
exit 0