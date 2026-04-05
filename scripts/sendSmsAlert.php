<?php
function sendSmsAlert($currentCount, $isRecovery = false, $doctorNames = [])
{
    //Log doctor names for debugging
    if (!$isRecovery) {
        echo "📋 Doctors currently online: " . implode(', ', $doctorNames) .
            "\n";
    }

    $recipients = [
        '255743956595',
        '255756532635',
        '255757064197',
        '255754710722',
        '255746088031',
        '255746805383',
        '255791477166',
    ];

    // Build message
    if ($isRecovery) {
        $message = "RECOVERY: $currentCount doctors online now. All OK.";
    } else {
        $firstNames = array_map(fn($name) => explode(' ', $name)[0], $doctorNames);
        $doctorList = !empty($firstNames) ? ' (' . implode(', ', $firstNames) . ')' : '';
        $message = "ALERT: Only $currentCount doctor(s) are online$doctorList. Check system.";
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