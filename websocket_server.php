<?php
require 'vendor/autoload.php'; // Include Composer's autoloader

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\Socket\Server as ReactorServer;
use React\EventLoop\Factory;

class CallWebSocket implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Handle incoming messages if needed
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }

    public function broadcast($data) {
        foreach ($this->clients as $client) {
            $client->send($data);
        }
    }
}

// Setup server
$loop = Factory::create();
$server = new ReactorServer("0.0.0.0:8080", $loop);
$wsServer = new Ratchet\App($loop);
$wsServer->route('/call', new CallWebSocket(), ['*']);
$loop->run();
