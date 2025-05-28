#!/bin/bash
 
export PATH=/bin:/usr/bin:/usr/local/bin
TODAY=`date +"%Y%m%d"`

#Sending IVR DB
#scp /home/afyacall/backups/PBX_BACKUP/DB/AFYACALL-${TODAY}.sql.gz backupuser@192.168.1.50:/home/backupuser/PBX/

#Sending Billing
scp /home/afyacall/backups/BILLING_BACKUP/DB/afyacallproduction-${TODAY}.sql.gz backupuser@192.168.1.50:/home/backupuser/SMS/

#Sending Doctor
scp /home/afyacall/backups/DOCTOR_BACKUP/DB/doctorproduction-${TODAY}.sql.gz backupuser@192.168.1.50:/home/backupuser/DOCTOR/
