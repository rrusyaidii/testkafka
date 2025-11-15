<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;

class KafkaWorker extends BaseCommand
{
    protected $group       = 'Kafka';
    protected $name        = 'kafka:work';
    protected $description = 'Multi-topic consumer with retry + DLQ support';

    private $db;

    public function run(array $params)
    {
        $this->db = \Config\Database::connect();

        $conf = new Conf();
        $conf->set('metadata.broker.list', 'localhost:29092');
        $conf->set('group.id', 'ci4-worker-group');
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');

        $consumer = new RdKafkaConsumer($conf);
        $consumer->subscribe([
            'orders-topic',
            'orders-retry-topic',
            'notifications-topic'
        ]);

        CLI::write("🔥 Kafka Worker Running...", 'green');

        while (true) {
            $msg = $consumer->consume(2000);

            switch ($msg->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->handleMessage($msg->topic_name, $msg->payload);
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    break;

                default:
                    CLI::error($msg->errstr());
                    break;
            }
        }
    }

    private function handleMessage(string $topic, string $json)
    {
        $data = json_decode($json, true);

        if (!$data) {
            $this->sendToDLQ($json);
            return;
        }

        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                match ($topic) {
                    'orders-topic' => $this->processOrder($data),
                    'orders-retry-topic' => $this->processRetryOrder($data),
                    'notifications-topic' => $this->processNotification($data),
                    default => null
                };

                return; // success → exit loop

            } catch (\Throwable $e) {
                $attempt++;
                CLI::error("⚠ Error attempt {$attempt}: " . $e->getMessage());
                sleep(1); // optional backoff
            }
        }

        // ⛔ after 5 failed attempts → DLQ
        $this->sendToDLQ($data);
    }


    private function processOrder(array $order)
    {

        $this->broadcastWS("New Order Processed: {$order['order_id']}");

        // simulate work
        if (rand(1, 5) === 1) {  // random fail
            throw new \Exception("Random fail");
        }

        $this->db->table('orders')->insert([
            'order_id' => $order['order_id'],
            'user_id'  => $order['user_id'],
            'amount'   => $order['amount'],
            'raw_json' => json_encode($order)
        ]);

        CLI::write("✔ Order {$order['order_id']} processed", 'yellow');
    }

    private function processRetryOrder(array $order)
    {
        if (($order['retry'] ?? 0) >= 3) {
            $this->sendToDLQ($order);
            return;
        }

        $order['retry'] = ($order['retry'] ?? 0) + 1;

        // PROCESS AGAIN
        $this->processOrder($order);
    }

    private function processNotification(array $data)
    {
        CLI::write("🔔 Notification: {$data['message']}", 'cyan');
    }

    private function sendToRetry($data)
    {
        $data['retry'] = ($data['retry'] ?? 0) + 1;
        service('kafkaProducer')->send('orders-retry-topic', $data);

        CLI::write("⏳ Sent to retry queue", 'blue');
    }

    private function sendToDLQ($data)
    {
        service('kafkaProducer')->send('orders-dlq-topic', $data);

        CLI::write("❌ Sent to DLQ", 'red');
    }

    private function broadcastWS($msg)
    {
        $ch = curl_init("http://localhost:8082/broadcast");
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['msg' => $msg]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

}
