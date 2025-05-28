<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessLanguage;
use Carbon\Carbon;

class SendRevenueReport extends Command
{
    protected $signature = 'sms:revenue-report';
    protected $description = 'Send revenue report SMS to recipients';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Database connection details
        $host = '192.168.1.11';
        $db = 'afyacallproduction';
        $user = 'prodafya';
        $password = 'Afyacall@2021qazWSX';

        $dsn = "mysql:host=$host;dbname=$db;charset=UTF8";

        try {
            // Create a new PDO instance
            $pdo = new \PDO($dsn, $user, $password);

            // Confirm successful connection
            echo "Successfully connected to the database.\n";

            // Get yesterday's date
            $yesterday = Carbon::yesterday()->toDateString();

            // Prepare and execute the SQL query
            $sql = "
                SELECT 
                    `subquery`.`DateCreated` AS `DateCreated`,
                    SUM(CASE WHEN `subquery`.`product_id` = 1 THEN `subquery`.`amount_IN` END) AS `ivr`,
                    SUM(CASE WHEN `subquery`.`product_id` = 2 THEN `subquery`.`amount_IN` END) AS `sms`,
                    SUM(CASE WHEN `subquery`.`product_id` = 4 THEN `subquery`.`amount_IN` END) AS `doctor_subs`,
                    SUM(CASE WHEN `subquery`.`product_id` IN (3, 5, 6) THEN `subquery`.`amount_IN` ELSE 0 END) AS `calls`,
                    SUM(`subquery`.`amount_IN`) AS `total`
                FROM (
                    SELECT 
                        CAST(`transactions`.`created_at` AS DATE) AS `DateCreated`,
                        `transactions`.`product_id` AS `product_id`,
                        `transactions`.`amount_IN` AS `amount_IN`
                    FROM 
                        `transactions`
                    WHERE 
                        `transactions`.`status` = 1
                        AND CAST(`transactions`.`created_at` AS DATE) = :yesterday
                ) `subquery`
                GROUP BY 
                    `subquery`.`DateCreated`;
            ";

            $statement = $pdo->prepare($sql);
            $statement->bindParam(':yesterday', $yesterday);
            $statement->execute();
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            // Check if we have results
            if ($row) {
                // Format the revenue amounts with commas
                $ivr = number_format(round($row['ivr'], 2), 2, '.', ',');
                $sms = number_format(round($row['sms'], 2), 2, '.', ',');
                $doctorSubs = number_format(round($row['doctor_subs'], 2), 2, '.', ',');
                $calls = number_format(round($row['calls'], 2), 2, '.', ',');
                $total = number_format(round($row['total'], 2), 2, '.', ',');

                // List of recipients with their names
                $recipients = [
                    ['msisdn' => '255743956595', 'name' => 'Derrick'],
                    ['msisdn' => '255746805383', 'name' => 'Julius'],
                    ['msisdn' => '255746088031', 'name' => 'Wingslaus'],
                    ['msisdn' => '255746193050', 'name' => 'Ireri'],
                    ['msisdn' => '255754710722', 'name' => 'Mwamba'],
                    ['msisdn' => '255754710702', 'name' => 'Sam'],
                    ['msisdn' => '255745994671', 'name' => 'Rodrick']
                ];

                // Loop through each recipient and send the personalized message
                foreach ($recipients as $recipient) {
                    $msisdn = $recipient['msisdn'];
                    $name = $recipient['name'];
                    $message = [
                        'sw' => "Hi Mr.$name, revenue ($yesterday): IVR => $ivr, SMS => $sms, Dr Subs => $doctorSubs, Calls => $calls, TOTAL => $total",
                        'en' => "Hi Mr.$name, revenue ($yesterday): IVR => $ivr, SMS => $sms, Dr Subs => $doctorSubs, Calls => $calls, TOTAL => $total",
                    ];

                    // Dispatch the message
                    ProcessLanguage::dispatchSync($msisdn, $message['sw'], $message['en']);
                    echo "Message sent to $name successfully.\n";
                }
            } else {
                echo "No revenue data found for yesterday ($yesterday).\n";
            }

        } catch (\PDOException $e) {
            // Handle database connection error
            echo "Database error: " . $e->getMessage();
        } catch (\Exception $e) {
            // Handle any other errors
            echo "Error: " . $e->getMessage();
        }
    }
}

?>
