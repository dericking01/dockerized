#!/bin/bash
# cleanup_backups.sh
# Permanently delete backups older than 2 days in /var/LocalBackups
# Ensures at least 2 most recent backups are always kept.

BACKUP_DIR="/var/LocalBackups"
DAYS_TO_KEEP=1
LOGFILE="/var/log/cleanup_backups.log"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting cleanup..." >> "$LOGFILE"

# Count total backups
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -type f \( -name "*.sql.gz" -o -name "*.sql.gz.gpg" \) | wc -l)


# If fewer than 2 backups, skip cleanup
if [ "$TOTAL_BACKUPS" -le 2 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Skipping cleanup: only $TOTAL_BACKUPS backups found." >> "$LOGFILE"
    exit 0
fi

# Find and delete files older than DAYS_TO_KEEP, but keep at least 2 recent backups
FILES_TO_DELETE=$(find "$BACKUP_DIR" -type f \( -name "*.sql.gz" -o -name "*.sql.gz.gpg" \) -mtime +$DAYS_TO_KEEP | sort)


for FILE in $FILES_TO_DELETE; do
    # Recount before deleting
    TOTAL_BACKUPS=$(find "$BACKUP_DIR" -type f \( -name "*.sql.gz" -o -name "*.sql.gz.gpg" \) | wc -l)
    if [ "$TOTAL_BACKUPS" -le 2 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Stop deleting: only $TOTAL_BACKUPS backups left." >> "$LOGFILE"
        break
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deleting $FILE" >> "$LOGFILE"
    rm -f "$FILE"
done

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleanup finished." >> "$LOGFILE"