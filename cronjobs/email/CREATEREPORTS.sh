#!/bin/bash
export PATH=/bin:/usr/bin:/usr/local/bin

#php /root/cronjobs/email/createDailyCDR.php

#echo "Creating Daily CDR Reports and DR calling Reports....."
#sleep 40

php /root/cronjobs/email/createDRDailyReport.php

echo "Sucessful created Reports."

sleep 40

#php /root/cronjobs/email/sendCDRDailyReports.php

#echo "Sending Reports to Emails."

php /root/cronjobs/email/sendDrCallLogs.php


echo "Sucessful finished."
