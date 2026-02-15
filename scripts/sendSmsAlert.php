<?php
function sendSmsAlert($currentCount, $isRecovery = false)
{
    $recipients = [
        '255743956595',
        '255756532635',
        '255757064197',
        '255754710722',
        '255746088031',
        '255746805383',
        // '255743570368',
    ];

    // Build message
    if ($isRecovery) {
        $message = "RECOVERY: $currentCount doctors online now. All OK.";
    } else {
        $message = "ALERT: Only $currentCount doctor(s) are online. Check system.";
    }

    echo "📨 SMS Message: \"$message\"\n";

    foreach ($recipients as $msisdn) {
        $query = http_build_query([
            'username'   => 'afya',
            'password'   => 'Afya4017',
            'from'       => 'AFYACALL',
            'dlr-mask'   => '31',
            'to'         => $msisdn,
            'text'       => $message
        ]);

        $url = "http://192.168.1.10:6017/cgi-bin/sendsms?$query";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "❌ SMS to $msisdn failed: " . curl_error($ch) . "\n";
        } else {
            echo "✅ SMS sent to $msisdn\n";
        }

        curl_close($ch);
    }
}