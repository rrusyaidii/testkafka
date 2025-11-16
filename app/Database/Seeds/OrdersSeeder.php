<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\CLI\CLI;

class OrdersSeeder extends Seeder
{
    /**
     * Run the seeder.
     *
     * It will send messages to Kafka topic 'orders-topic'.
     * Set SEED_COUNT env var to change number of messages, e.g. SEED_COUNT=500 php spark db:seed OrdersSeeder
     */
    public function run()
    {
        $count = (int) (getenv('SEED_COUNT') ?: 100);

        // Try to get kafka producer service
        try {
            $producer = service('kafkaProducer');
        } catch (\Throwable $e) {
            $producer = null;
        }

        if ($producer === null) {
            CLI::error('kafkaProducer service not available. Make sure you added it to app/Config/Services.php.');
            return;
        }

        CLI::write("Seeding {$count} sample orders to Kafka (orders-topic)...", 'green');

        for ($i = 1; $i <= $count; $i++) {
            $order = [
                'order_id' => random_int(1000, 999999),
                'user_id' => random_int(1, 5000),
                'amount' => number_format(mt_rand(100, 100000) / 100, 2, '.', ''),
                'meta' => [
                    'source' => 'seeder',
                    'seed_index' => $i,
                ],
                'created_at' => date('Y-m-d H:i:s'),
            ];

            try {
                $producer->send('orders-topic', $order);
                CLI::write("→ sent order {$order['order_id']} ({$i}/{$count})", 'yellow');
            } catch (\Throwable $e) {
                CLI::error("Failed to send order {$order['order_id']}: " . $e->getMessage());
            }

            // small pause so kafka isn't overwhelmed (adjust or remove as needed)
            usleep(50000); // 50ms
        }

        CLI::write('Seeding finished.', 'green');
    }
}
