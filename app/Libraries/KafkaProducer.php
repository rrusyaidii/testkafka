<?php

namespace App\Libraries;

use RdKafka\Producer;
use RdKafka\Conf;

class KafkaProducer
{
    protected Producer $producer;

    public function __construct()
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', 'localhost:29092');
        $conf->set('queue.buffering.max.ms', 10);
        $conf->set('message.send.max.retries', 5);
        $conf->set('retry.backoff.ms', 250);


        $this->producer = new Producer($conf);
    }

    public function send(string $topicName, array $data): bool
    {
        $topic = $this->producer->newTopic($topicName);

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);

        $topic->produce(RD_KAFKA_PARTITION_UA, 0, $payload);

        $this->producer->poll(0);

        return $this->producer->flush(10000) === RD_KAFKA_RESP_ERR_NO_ERROR;
    }


}
