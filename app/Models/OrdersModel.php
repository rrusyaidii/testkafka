<?php

namespace App\Models;

use CodeIgniter\Model;

class OrdersModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';

    protected $allowedFields = [
        'order_id',
        'user_id',
        'amount',
        'raw_json',
        'created_at'
    ];

    protected $returnType = 'array';
    protected $useTimestamps = false;
}
