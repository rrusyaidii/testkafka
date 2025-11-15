<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('kafka/send', 'KafkaTest::send');
$routes->get('api/kafka/messages', 'KafkaData::list');
$routes->get('kafka/dashboard', function() {
    return view('kafka_dashboard');
});
$routes->get('kafka/order-test', 'KafkaTest::sendOrderExample');
