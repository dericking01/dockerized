<?php
date_default_timezone_set('Africa/Dar_es_Salaam');

require __DIR__ . '/../vendor/autoload.php'; // if you have vlucas/phpdotenv installed

// Load environment (.env) variables
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
    }
}

// DB credentials
$host = $_ENV['PROD_DB_HOST'] ?? '192.168.1.11';
$db   = $_ENV['PROD_DB_NAME'] ?? 'drraha';
$user = $_ENV['PROD_DB_USERNAME'] ?? 'derrick';
$pass = $_ENV['PROD_DB_PASSWORD'] ?? '';

// Logging helper
$logFile = __DIR__ . '/logs/status_sms.log';
function log_msg($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

try {
    $dsn = "pgsql:host=$host;dbname=$db";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    log_msg("Connected to DB successfully");
} catch (PDOException $e) {
    log_msg("DB connection failed: " . $e->getMessage());
    exit;
}

// Build today's condition
$todayStart = date('Y-m-d 00:00:00');
$todayEnd   = date('Y-m-d 23:59:59');

// Query counts
$sql = "
    SELECT status, COUNT(*) AS status_count
    FROM billing.payments
    WHERE created_at BETWEEN :start AND :end
    GROUP BY status
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['start' => $todayStart, 'end' => $todayEnd]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$results) {
    log_msg("No results found for today");
    exit;
}

// Map statuses to short codes
$map = [
    'CHARGE_LIMIT_EXCEEDED' => 'ChargeLimit',
    'FAILED'                => 'Failed',
    'INSUFFICIENT_BALANCE'  => 'Insuff.Bal',
    'INVALID_SUBSCRIBER'    => 'InvSub',
    'SUCCESS'               => 'Success',
    'PROCESSING'            => 'Processing'
];

// Build message like: CLE=1000 F=234 ISB=22 ... TOTAL=1256
$messageParts = [];
$total = 0;

foreach ($results as $row) {
    $status = $row['status'];
    $count  = (int)$row['status_count'];
    $total += $count;

    if (isset($map[$status])) {
        $short = $map[$status];
        $messageParts[] = $short . '=' . number_format($count, 0, '.', ',');
    }
}

$messageParts[] = 'TOTAL=>' . number_format($total, 0, '.', ',');

$message = "Today's Transactions Status count: " . implode(' ', $messageParts);

log_msg("Prepared SMS message: $message");

// Recipient list
$recipients = [
    '255743956595',
    '255754710722',
    '255746088031',
    '255756532635',
    '255743570368',
    '255765975152',
    '255753932250',
    '255757064197'
];


// Send SMS via curl
foreach ($recipients as $msisdn) {
    $url = "http://192.168.1.10:6017/cgi-bin/sendsms?" . http_build_query([
        'username'  => 'afya',
        'password'  => 'Afya4017',
        'from'      => 'AFYACALL',
        'to'        => $msisdn,
        'text'      => $message,
        'dlr-mask'  => 31,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        log_msg("Failed to send SMS to $msisdn: $err");
    } else {
        log_msg("Sent SMS to $msisdn | Response: $response");
    }
}

log_msg("All SMS sent for " . date('Y-m-d'));
