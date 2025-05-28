#!/bin/bash

export PATH=/bin:/usr/bin:/usr/local/bin
TODAY=`date +"%Y%m%d"`

DB_BACKUP_PATH='/home/afyacall/backups/BILLING_BACKUP/DB'
MYSQL_HOST='localhost'
MYSQL_PORT='3306'
MYSQL_USER='prodafya'
MYSQL_PASSWORD='Afyacall@2021qazWSX'
DATABASE_NAME='afyacallproduction'
BACKUP_RETAIN_DAYS=20   ## Number of days to keep local backup copy

#################################################################

mysqldump -h ${MYSQL_HOST} \
   -P ${MYSQL_PORT} \
   -u ${MYSQL_USER} \
   -p${MYSQL_PASSWORD} \
   ${DATABASE_NAME} | gzip > ${DB_BACKUP_PATH}/${DATABASE_NAME}-${TODAY}.sql.gz

if [ $? -eq 0 ]; then
  echo "Database backup successfully completed"
else
  echo "Error found during backup"
  exit 1
fi
