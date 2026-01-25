<?php
date_default_timezone_set('Africa/Dar_es_Salaam');

require __DIR__ . '/../vendor/autoload.php';

/**
 * Load .env manually (cron-safe)
 */
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    foreach (file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value, " \t\n\r\0\x0B\"");
    }
}

/**
 * DB credentials
 */
$host = $_ENV['PROD_DB_HOST'] ?? '192.168.1.11';
$db   = $_ENV['PROD_DB_NAME'] ?? 'afyacall';
$user = $_ENV['PROD_DB_USERNAME'] ?? 'derrick';
$pass = $_ENV['PROD_DB_PASSWORD'] ?? '';

/**
 * Logging
 */
$logFile = __DIR__ . '/logs/revenue_sms.log';
function log_msg($msg) {
    global $logFile;
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

/**
 * DB Connection
 */
try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$db",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    log_msg("DB connected");
} catch (PDOException $e) {
    log_msg("DB connection failed: " . $e->getMessage());
    exit;
}

/**
 * Get today's revenue (midnight → now)
 */
$sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_revenue
    FROM billing.icg_payments
    WHERE status = 'SUCCESS'
      AND created_at >= CURRENT_DATE
      AND created_at < CURRENT_DATE + INTERVAL '1 day'
";

$stmt = $pdo->query($sql);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$totalRevenue = (float)$row['total_revenue'];
$formattedRevenue = number_format($totalRevenue, 0, '.', ',');

/**
 * Recipients
 */
$recipients = [
    '255743956595' => 'Mr. Derrick',
    '255746088031' => 'Mr. Wingslaus',
    '255756532635' => 'Mr. Siwangu',
    '255757064197' => 'Ms. Nancy',
];

/**
 * Send SMS
 */
foreach ($recipients as $msisdn => $name) {

    $message = "Hi, $name, Today's Current Revenue is: $formattedRevenue";

    $url = "http://192.168.1.10:6017/cgi-bin/sendsms?" . http_build_query([
        'username' => 'afya',
        'password' => 'Afya4017',
        'from'     => 'AFYACALL',
        'to'       => $msisdn,
        'text'     => $message,
        'dlr-mask' => 31,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_msg("SMS FAILED [$msisdn]: $error");
    } else {
        log_msg("SMS SENT [$msisdn]: $response | $message");
    }
}

log_msg("Hourly revenue SMS completed");