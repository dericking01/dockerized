#!/bin/bash
php /root/cronjobs/email/createReports.php

sleep 40

php /root/cronjobs/email/sendDrCallLogs.php
