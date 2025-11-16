<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;

class KafkaWorker extends BaseCommand
{
    protected $group = 'Kafka';
    protected $name = 'kafka:work';
    protected $description = 'Multi-topic consumer with retry + DLQ support';

    private $db;

    public function run(array $params)
    {
        $this->db = \Config\Database::connect();

        $conf = new Conf();
        $conf->set('metadata.broker.list', '127.0.0.1:29092'); // force IPv4
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

        try {
            match ($topic) {
                'orders-topic' => $this->processOrder($data),
                'orders-retry-topic' => $this->processRetryOrder($data),
                'notifications-topic' => $this->processNotification($data),
                default => null
            };
        } catch (\Throwable $e) {
            // Log error
            CLI::error("Processing error: " . $e->getMessage());

            // increment retry counter and send to retry topic with next_try timestamp (exponential backoff)
            $data['retry'] = ($data['retry'] ?? 0) + 1;

            // calculate backoff in seconds: e.g. 5, 30, 60, 300...
            $backoffs = [5, 30, 60, 300, 900]; // adjust as needed
            $idx = min($data['retry'] - 1, count($backoffs) - 1);
            $delaySeconds = $backoffs[$idx] ?? end($backoffs);

            $data['next_try'] = date('Y-m-d H:i:s', time() + $delaySeconds);

            // If retry exceeded threshold, send to DLQ instead
            $maxRetries = 3;
            if ($data['retry'] > $maxRetries) {
                $this->sendToDLQ($data);
            } else {
                $this->sendToRetry($data);
            }
        }
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
            'user_id' => $order['user_id'],
            'amount' => $order['amount'],
            'raw_json' => json_encode($order)
        ]);

        CLI::write("✔ Order {$order['order_id']} processed", 'yellow');
    }

    private function processRetryOrder(array $order)
    {
        // If next_try exists and it's in the future, re-enqueue the message (sleep small) and return.
        if (!empty($order['next_try'])) {
            $nextTryTs = strtotime($order['next_try']);
            if ($nextTryTs > time()) {
                $wait = $nextTryTs - time();
                CLI::write("⏳ Retry for order {$order['order_id']} scheduled in {$wait}s", 'blue');
                // Don't busy-loop — re-publish into retry topic so other workers can pick later.
                // Re-publish unchanged (or with same next_try) and return.
                // But to avoid tight loops, we re-publish with same next_try and return.
                service('kafkaProducer')->send('orders-retry-topic', $order);
                return;
            }
        }

        // If retry count exceeded -> send to DLQ
        if (($order['retry'] ?? 0) >= 3) {
            $this->sendToDLQ($order);
            return;
        }

        // increment retry (we attempted now)
        $order['retry'] = ($order['retry'] ?? 0) + 1;

        // try processing; any exception will be caught by caller (handleMessage) or rethrown here
        try {
            $this->processOrder($order);
        } catch (\Throwable $e) {
            CLI::error("Retry processing failed for order {$order['order_id']}: " . $e->getMessage());
            // compute a new next_try and requeue to retry-topic (exponential backoff)
            $backoffs = [5, 30, 60, 300, 900];
            $idx = min($order['retry'] - 1, count($backoffs) - 1);
            $delaySeconds = $backoffs[$idx] ?? end($backoffs);
            $order['next_try'] = date('Y-m-d H:i:s', time() + $delaySeconds);

            // If retry limit reached, send to DLQ
            if ($order['retry'] >= 3) {
                $this->sendToDLQ($order);
            } else {
                service('kafkaProducer')->send('orders-retry-topic', $order);
                CLI::write("⏳ Requeued order {$order['order_id']} for retry (retry={$order['retry']})", 'blue');
            }
        }
    }


    private function processNotification(array $data)
    {
        CLI::write("🔔 Notification: {$data['message']}", 'cyan');
    }

    private function sendToRetry($data)
    {
        $data['retry'] = ($data['retry'] ?? 0);
        try {
            $producer = service('kafkaProducer');
            if ($producer === null) {
                throw new \RuntimeException('kafkaProducer service not registered');
            }
            $producer->send('orders-retry-topic', $data);
            CLI::write("⏳ Sent order to retry-topic (retry={$data['retry']})", 'blue');
        } catch (\Throwable $e) {
            CLI::error("Failed to send to retry-topic: " . $e->getMessage());
            // fallback: send to DLQ if cannot send to retry
            $this->sendToDLQ($data);
        }
    }

    private function sendToDLQ($data)
    {
        try {
            $producer = service('kafkaProducer');
            if ($producer === null) {
                throw new \RuntimeException('kafkaProducer service not registered');
            }
            $producer->send('orders-dlq-topic', $data);
            CLI::write("❌ Sent to DLQ", 'red');
        } catch (\Throwable $e) {
            // last resort: write to DB 'failed' table or log file
            CLI::error("Failed to send to DLQ: " . $e->getMessage());
            // optional: write to failed_messages table
            $this->db->table('failed_messages')->insert([
                'payload' => json_encode($data),
                'error' => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }


    private function broadcastWS($msg)
    {
        $ch = curl_init("http://localhost:8082/broadcast");
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['msg' => $msg]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
    }

}
