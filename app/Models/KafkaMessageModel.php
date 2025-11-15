<?php

namespace App\Models;

use CodeIgniter\Model;

class KafkaMessageModel extends Model
{
    protected $table = 'kafka_messages';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'order_id',
        'user_id',
        'amount',
        'raw_json',
        'created_at'
    ];

    protected $returnType = 'array';
}
