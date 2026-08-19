#!/usr/bin/env bash
# بکاپ روزانه دیتابیس و فایل‌های آپلودی repert-robot
#
# نصب در crontab (هر شب ساعت ۳):
#   crontab -e
#   0 3 * * * /home/simorgh/stacks/repert-robot/docker/backup.sh >> /home/simorgh/backups/repert/backup.log 2>&1

set -euo pipefail

STACK_DIR="/home/simorgh/stacks/repert-robot"
BACKUP_DIR="/home/simorgh/backups/repert"
KEEP_DAYS=14
STAMP=$(date +%F-%H%M)

mkdir -p "$BACKUP_DIR"
cd "$STACK_DIR"

echo "[$(date '+%F %T')] شروع بکاپ"

# ---- دیتابیس ----
docker compose --env-file .env.docker exec -T mysql sh -c \
  'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines \
             --triggers --no-tablespaces --default-character-set=utf8mb4 repert' \
  | gzip > "${BACKUP_DIR}/db-${STAMP}.sql.gz"

DB_SIZE=$(du -h "${BACKUP_DIR}/db-${STAMP}.sql.gz" | cut -f1)
echo "  دیتابیس: ${DB_SIZE}"

# ---- فایل‌های آپلودی (هفتگی، چون حجیم است) ----
if [ "$(date +%u)" = "5" ]; then
  docker run --rm \
    -v repert-robot_repert_storage:/data:ro \
    -v "${BACKUP_DIR}":/backup \
    alpine tar czf "/backup/storage-${STAMP}.tar.gz" -C /data .
  ST_SIZE=$(du -h "${BACKUP_DIR}/storage-${STAMP}.tar.gz" | cut -f1)
  echo "  فایل‌ها: ${ST_SIZE}"
fi

# ---- حذف بکاپ‌های قدیمی ----
find "$BACKUP_DIR" -name 'db-*.sql.gz'      -mtime +${KEEP_DAYS} -delete
find "$BACKUP_DIR" -name 'storage-*.tar.gz' -mtime +60           -delete

echo "[$(date '+%F %T')] بکاپ تمام شد"
