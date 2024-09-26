<?php
require 'vendor/autoload.php'; // Ensure you have installed the required libraries

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage; // Collection of connected clients
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Decode incoming message
        $data = json_decode($msg, true);
        
        // Example: Handle call claimed notification
        if (isset($data['action']) && $data['action'] === 'claim_call') {
            // Notify all clients about the call claim
            foreach ($this->clients as $client) {
                if ($client !== $from) {
                    $client->send(json_encode([
                        'status' => 'success',
                        'message' => 'Call claimed by asim',
                        'call_id' => $data['call_id'],
                        'support_name' => $data['support_name'],
                        'client_name' => $data['client_name'],
                        'support_id' => $data['support_id']
                    ]));
                }
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Remove the connection
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $conn->close();
    }
}

// Run the WebSocket server
$server = IoServer::factory(new HttpServer(new WsServer(new WebSocketServer())), 8080);
$server->run();
