#!/usr/bin/env bash
# CIVILINA Engineering — daily backup (database + uploaded evidence).
#
#   cron: 30 2 * * * civilina /srv/civilina/app/deploy/backup.sh
#
# Database credentials come from ~/.my.cnf of the backup user (chmod 600),
# never from the application .env. Copy the backup directory off the server
# (rclone, rsync…) after each run; a backup kept only on the server is not a
# backup.
set -euo pipefail

APP_DIR="${APP_DIR:-/srv/civilina/app}"
BACKUP_DIR="${BACKUP_DIR:-/srv/civilina/backups}"
DB_NAME="${DB_NAME:-civilina}"
KEEP_DAYS="${KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

umask 077
mkdir -p "$BACKUP_DIR"

mysqldump --single-transaction --quick --routines --no-tablespaces "$DB_NAME" \
    | gzip -9 > "$BACKUP_DIR/db-$STAMP.sql.gz"

# Uploaded evidence (PV, test reports, photos). The standards inbox is not
# on the server and is excluded in case it is ever copied there.
tar -C "$APP_DIR/storage/app" \
    --exclude='private/engineering/standards_inbox' \
    --exclude='private/engineering/ocr_tmp' \
    -czf "$BACKUP_DIR/files-$STAMP.tar.gz" private

find "$BACKUP_DIR" -type f -name '*.gz' -mtime +"$KEEP_DAYS" -delete

echo "Sauvegarde $STAMP : $(du -sh "$BACKUP_DIR" | cut -f1) au total dans $BACKUP_DIR"
