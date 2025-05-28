#!/bin/bash

export PATH=/bin:/usr/bin:/usr/local/bin
TODAY=`date +"%Y%m%d"`

scp /home/afyacall/backups/DOCTOR_BACKUP/DB/doctorproduction-${TODAY}.sql.gz root@147.182.234.99:/root/backup/DOCTOR_BACKUP/
