<?php
function shortenSms(string $message, int $limit = 160): string
{
    if (mb_strlen($message) <= $limit) {
        return $message;
    }

    return mb_substr($message, 0, $limit - 3) . '...';
}

function sendSmsAlert($currentCount, $isRecovery = false, array $providerGroups = [])
{
    if (!$isRecovery) {
        $summaryParts = [];
        foreach ($providerGroups as $type => $info) {
            $names = array_slice($info['firstNames'] ?? [], 0, 4);
            $label = ucfirst(strtolower($type));
            if (!empty($names)) {
                $summaryParts[] = "{$label}: " . implode(', ', $names);
            } else {
                $summaryParts[] = "{$label}: {$info['count']}";
            }
        }

        echo "📋 Providers currently online: " . implode(' | ', $summaryParts) . "\n";
    }

    $recipients = [
        '255743956595',
        '255756532635',
        // '255757064197',
        '255754710722',
        '255746088031',
        '255791477166',
    ];

    if ($isRecovery) {
        $message = "RECOVERY: {$currentCount} providers online now. All OK.";
    } else {
        $parts = [];
        foreach ($providerGroups as $type => $info) {
            $label = ucfirst(strtolower($type));
            $firstNames = array_slice($info['firstNames'] ?? [], 0, 4);
            if (!empty($firstNames)) {
                $parts[] = "{$label}: " . implode(', ', $firstNames);
            } else {
                $parts[] = "{$label}: {$info['count']}";
            }
        }

        $groupText = implode(' | ', $parts);
        $message = "ALERT: Only {$currentCount} available provider(s) are online";
        if ($groupText !== '') {
            $message .= " - {$groupText}";
        }
        $message .= '.';
        $message = shortenSms($message, 160);
    }

    echo "📨 SMS Message: \"{$message}\"\n";

    foreach ($recipients as $msisdn) {
        $query = http_build_query([
            'username'   => 'afya',
            'password'   => 'Afya4017',
            'from'       => 'AFYACALL',
            'dlr-mask'   => '31',
            'to'         => $msisdn,
            'text'       => $message
        ]);

        $url = "http://192.168.1.10:6017/cgi-bin/sendsms?{$query}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "❌ SMS to {$msisdn} failed: " . curl_error($ch) . "\n";
        } else {
            echo "✅ SMS sent to {$msisdn}\n";
        }

        curl_close($ch);
    }
}
