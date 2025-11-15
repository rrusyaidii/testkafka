<?php

namespace App\Controllers;

use App\Models\OrdersModel;
use CodeIgniter\RESTful\ResourceController;

class KafkaData extends ResourceController
{
    public function list()
    {
        $model = new OrdersModel();
        // return latest 200 orders by default to avoid huge payloads
        $data = $model->orderBy('id', 'DESC')->findAll(200);

        // if you want ascending order change next line
        $data = array_reverse($data);

        return $this->respond($data);
    }
}
