<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use App\Libraries\KafkaProducer;

class OrderSeeder extends BaseCommand
{
    protected $group = 'Kafka';
    protected $name = 'orders:seed';
    protected $description = 'Generate 1000 example orders';

    public function run(array $params)
    {
        $producer = new KafkaProducer();

        for ($i = 0; $i < 1000; $i++) {
            $order = [
                'order_id' => rand(1000, 9999),
                'user_id'  => rand(1, 500),
                'amount'   => rand(20, 999),
                'items' => [
                    ['sku' => 'A100', 'qty' => rand(1,3)],
                    ['sku' => 'B200', 'qty' => rand(1,5)]
                ],
                'timestamp' => date('Y-m-d H:i:s'),
                'retry' => 0
            ];

            $producer->send('orders-topic', $order);
        }

        echo "🔥 1000 orders sent to Kafka\n";
    }
}
