#!/bin/bash

export PATH=/bin:/usr/bin:/usr/local/bin
TODAY=`date +"%Y%m%d"`

scp /home/afyacall/backups/BILLING_BACKUP/DB/afyacallproduction-${TODAY}.sql.gz root@147.182.234.99:/root/backup/BILLING_BACKUP/
