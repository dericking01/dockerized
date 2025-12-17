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
RETENTION_DAYS=3
SSH_KEY="~postgres/.ssh/id_ed25519"
GPG_RECIPIENT="dericking01@gmail.com"
ENCRYPTED_BACKUP_FILE="${BACKUP_FILE}.gpg"
ENCRYPTED_BACKUP_PATH="${BACKUP_DIR}/${ENCRYPTED_BACKUP_FILE}"


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

# Encrypted database Backup begins
log "Checking GPG public key availability for ${GPG_RECIPIENT}..."

if ! gpg --list-keys "${GPG_RECIPIENT}" >/dev/null 2>&1; then
    log "ERROR: GPG public key for ${GPG_RECIPIENT} not found in postgres keyring"
    log "HINT: Run -> sudo -u postgres gpg --import pg_backup_public.key"
    exit 1
fi

log "GPG public key found"

# Database Backup
log "Creating Encrypted backup at ${ENCRYPTED_BACKUP_PATH}..."
if ! pg_dump "${DB_NAME}" | gzip | \
    gpg --encrypt --recipient "${GPG_RECIPIENT}" > "${ENCRYPTED_BACKUP_PATH}"; then
    log "ERROR: Failed to create encrypted backup"
    exit 1
fi

# Verify backup was created
if [ ! -f "${ENCRYPTED_BACKUP_PATH}" ]; then
    log "ERROR: Backup file not created at ${ENCRYPTED_BACKUP_PATH}"
    exit 1
fi

BACKUP_SIZE=$(du -h "${ENCRYPTED_BACKUP_PATH}" | cut -f1)
log "Successfully created Encrypted backup (${BACKUP_SIZE}): ${ENCRYPTED_BACKUP_PATH}"

# remove unencrypted backup
rm -f "${BACKUP_PATH}"

# Remote Transfer
log "Transferring Encrypted backup to ${REMOTE_HOST}..."
if ! scp -o StrictHostKeyChecking=no -i "${SSH_KEY}" \
    "${ENCRYPTED_BACKUP_PATH}" \
    "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_DIR}/"; then
    log "ERROR: Failed to transfer Encrypted backup to ${REMOTE_HOST}"
    exit 1
fi

log "Backup successfully transferred to ${REMOTE_HOST}"

# Local Cleanup
log "Cleaning up old backups (keeping ${RETENTION_DAYS} days)..."
find "${BACKUP_DIR}" -name "${DB_NAME}_*.sql.gz.gpg" -mtime +${RETENTION_DAYS} -delete


# Remote Cleanup
if ! ssh -o StrictHostKeyChecking=no -i "${SSH_KEY}" \
    "${REMOTE_USER}@${REMOTE_HOST}" \
    "find ${REMOTE_DIR} -name '${DB_NAME}_*.sql.gz.gpg' -mtime +${RETENTION_DAYS} -delete"; then
    log "WARNING: Failed to clean up old backups on ${REMOTE_HOST}"
fi

log "Backup and cleanup completed successfully"
exit 0