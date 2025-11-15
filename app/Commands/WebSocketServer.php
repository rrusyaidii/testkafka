<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer extends BaseCommand implements MessageComponentInterface
{
    protected $group       = 'Websocket';
    protected $name        = 'ws:start';
    protected $description = 'Start WebSocket server for real-time dashboard';

    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

    public function run(array $params)
    {
        CLI::write("🔥 WebSocket Server Started @ ws://localhost:8082", 'green');

        $server = IoServer::factory(
            new HttpServer(new WsServer($this)),
            8082
        );

        $server->run();
    }

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
    }

    public function onClose(ConnectionInterface $conn)
    {
        $this->clients->detach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {}
    public function onError(ConnectionInterface $conn, \Exception $e) {}
}
