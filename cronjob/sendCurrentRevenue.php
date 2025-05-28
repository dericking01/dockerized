<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLanguage;
use Exception;
use Illuminate\Console\Command;
use PDO;
use PDOException;

class smsRevenue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:revenue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'send daily revenue notifications';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
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
            $pdo = new PDO($dsn, $user, $password);
	    // Confirm successful connection
	    echo "Successfully connected to the database.\n";

            // Prepare and execute the SQL query
            $sql = "SELECT SUM(amount_IN) AS total_revenue FROM transactions WHERE DATE(created_at)=CURDATE() AND status=1";
            $statement = $pdo->query($sql);
            $row = $statement->fetch();

            // Fetch and round the revenue amount
            $revenueAmount = number_format(round($row['total_revenue'], 2), 2, '.', ',');

            // List of recipients with their names
            $recipients = [
                ['msisdn' => '255743956595', 'name' => 'Derrick'],
                ['msisdn' => '255746805383', 'name' => 'Julius'],
                ['msisdn' => '255746088031', 'name' => 'Wingslaus'],
                ['msisdn' => '255746193050', 'name' => 'Ireri'],
                ['msisdn' => '255754710722', 'name' => 'Mwamba'],
                ['msisdn' => '255754710702', 'name' => 'Sam'],
                ['msisdn' => '255756532635', 'name' => 'Siwangu'],
                ['msisdn' => '255745994671', 'name' => 'Rodrick']
            ];

            // Loop through each recipient and send the personalized message
            foreach ($recipients as $recipient) {
                $msisdn = $recipient['msisdn'];
                $name = $recipient['name'];
                $message = [
                    'sw' => "Hi Mr. $name, the current revenue is => $revenueAmount",
                    'en' => "Hi Mr. $name, the current revenue is => $revenueAmount",
                ];

                // Dispatch the message
                ProcessLanguage::dispatchSync($msisdn, $message['sw'], $message['en']);
                echo "Message sent to $name successfully.\n";
            }

        } catch (PDOException $e) {
            // Handle database connection error
            echo "Database error: " . $e->getMessage();
        } catch (Exception $e) {
            // Handle any other errors
            echo "Error: " . $e->getMessage();
        }
    }


}
