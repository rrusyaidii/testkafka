<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RdKafka\Conf;
use RdKafka\KafkaConsumer as RdKafkaConsumer;

class KafkaMessageConsumer extends BaseCommand
{
    protected $group       = 'Kafka';
    protected $name        = 'kafka:consume';
    protected $description = 'Consume messages from Kafka and insert them into MySQL';

    public function run(array $params)
    {
        $conf = new Conf();

        $conf->set('metadata.broker.list', 'localhost:29092');
        $conf->set('group.id', 'ci4-consumer-group');
        $conf->set('auto.offset.reset', 'earliest');
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.commit.interval.ms', 3000);


        $consumer = new RdKafkaConsumer($conf);
        $consumer->subscribe(['test-topic']);

        CLI::write('🚀 Kafka consumer started... Listening to test-topic', 'green');

        while (true) {
            $message = $consumer->consume(120 * 1000);

            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    CLI::write('📥 Received: ' . $message->payload, 'blue');
                    $this->saveToDatabase($message->payload);
                    break;

                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                    break;

                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                    CLI::write('⏳ Timeout... waiting...', 'blue');
                    break;

                default:
                    CLI::error($message->errstr());
                    break;
            }
        }
    }

    private function saveToDatabase(string $json)
    {
        $data = json_decode($json, true);

        if (!$data) {
            CLI::error('Invalid JSON payload!');
            return;
        }

        $db = \Config\Database::connect();

        $db->table('kafka_messages')->insert([
            'msg_id'   => $data['id'] ?? null,
            'message'  => $data['message'] ?? null,
            'raw_json' => $json
        ]);

        CLI::write('💾 Saved to MySQL', 'green');
    }
}
