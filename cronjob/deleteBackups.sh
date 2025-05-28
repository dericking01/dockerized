#!/bin/bash
export PATH=/bin:/usr/bin:/usr/local/bin

#Delete Database backups and keep only last 3 backups
find /home/afyabackup/DB/*-db-srv-11.sql -mtime +1 -exec rm -f {} \;
