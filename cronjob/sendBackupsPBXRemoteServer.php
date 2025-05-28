#!/bin/bash
 
export PATH=/bin:/usr/bin:/usr/local/bin
TODAY=`date +"%Y%m%d"`


scp /home/afyacall/backups/PBX_BACKUP/DB/AFYACALL-${TODAY}.sql.gz root@147.182.234.99:/root/backup/PBX_BACKUP/DB/
