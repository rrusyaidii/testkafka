<?php

namespace App\Controllers;

use App\Libraries\KafkaProducer;
use CodeIgniter\RESTful\ResourceController;

class KafkaTest extends ResourceController
{
    /**
     * Sends a message to a Kafka topic.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function send()
    {
        // Ensure the KafkaProducer library is properly set up in App/Libraries/KafkaProducer.php
        $producer = new KafkaProducer();

        try {
            $topic = 'test-topic';
            $data = [
                'id' => 2,
                'message' => 'TEST 123'
            ];

            // Call the send method on your KafkaProducer instance
            $producer->send($topic, $data);

            // Return a successful JSON response
            return $this->respondCreated(['status' => 'sent', 'topic' => $topic, 'data' => $data]);

        } catch (\Exception $e) {
            // Handle any exceptions that might occur during the send process
            log_message('error', 'Kafka send error: ' . $e->getMessage());

            // Return an error response
            return $this->failServerError('Failed to send message to Kafka: ' . $e->getMessage());
        }
    }

    public function sendOrderExample()
    {
        $producer = new \App\Libraries\KafkaProducer();

        $order = [
            'order_id' => rand(1000, 9999),
            'user_id' => rand(1, 500),
            'amount' => rand(20, 999),
            'items' => [
                ['sku' => 'A100', 'qty' => rand(1, 3)],
                ['sku' => 'B200', 'qty' => rand(1, 5)]
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'retry' => 0
        ];

        $producer->send('orders-topic', $order);

        return $this->respond([
            'status' => 'queued',
            'data' => $order
        ]);
    }

}
