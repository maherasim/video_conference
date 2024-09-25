<?php 
require 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connection.php'; 
 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Log incoming data
    error_log(json_encode($data)); // Log incoming data

    $call_id = $data['call_id'] ?? '';
    $support_id = $data['support_id'] ?? '';
    $support_token = $data['support_token'] ?? '';

    // Validate input
    if (empty($call_id) || empty($support_id) || empty($support_token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, Support ID, and Support Token are required.']);
        exit;
    }

    try {
        // Verify the support token
        if (!verify_support_token($support_id, $support_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
            exit;
        }

        // Check if the call exists and is not claimed by another support
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ? AND call_status = 'waiting'");
        $stmt->execute([$call_id]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Call already claimed by another support or does not exist.']);
            exit;
        }

        // Log claiming details before executing the update
        error_log("Claiming call ID: $call_id by support ID: $support_id"); // Log claim details

        // Claim the call by updating the status and assigning support_id
        $stmt = $pdo->prepare("UPDATE calls SET support_id = ?, call_status = 'claimed', updated_at = NOW() WHERE call_id = ?");
        $stmt->execute([$support_id, $call_id]);

        // Fetch client and support names for response
        $client_stmt = $pdo->prepare("SELECT name FROM users WHERE uuid = ?");
        $client_stmt->execute([$call['client_id']]);
        $client = $client_stmt->fetch(PDO::FETCH_ASSOC);

        $support_stmt = $pdo->prepare("SELECT name FROM customer_support WHERE uuid = ?");
        $support_stmt->execute([$support_id]);
        $support = $support_stmt->fetch(PDO::FETCH_ASSOC);

        // Send WebSocket notification to notify all clients
        sendWebSocketNotification($call_id, $client['name'], $support['name']);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'message' => 'Call claimed by support',
            'call_details' => [
                'client_name' => $client['name'],
                'support_name' => $support['name'],
                'support_id' => $support_id // Include the support_id in the response
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to verify the support token from the 'customer_support' table
function verify_support_token($support_id, $token, $pdo) {
    try {
        // Fetch support by support_id
        $stmt = $pdo->prepare("SELECT token FROM customer_support WHERE uuid = ?");
        $stmt->execute([$support_id]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        // If support not found or token doesn't match, return false
        if (!$support || $support['token'] !== $token) {
            return false;
        }

        // If the token matches, return true
        return true;
    } catch (PDOException $e) {
        // Handle any potential database errors
        return false;
    }
}

// Function to send WebSocket notification
function sendWebSocketNotification($call_id, $client_name, $support_name) {
    // WebSocket server URL
    $ws_url = 'ws://84.247.187.38:8080'; // Your WebSocket server URL

    try {
        // Create a WebSocket connection
        $client = new WebSocket\Client($ws_url);

        $message = json_encode([
            'action' => 'claim_call',
            'call_id' => $call_id,
            'client_name' => $client_name,
            'support_id' => $support_id ,
            'support_name' => $support_name
        ]);

        // Send the WebSocket message
        $client->send($message);
        $client->close();
    } catch (Exception $e) {
        // Log the WebSocket error for debugging
        error_log('WebSocket Error: ' . $e->getMessage());
        // Comment out the exception throw for now
        // throw new Exception('WebSocket communication failed: ' . $e->getMessage());
        error_log('WebSocket communication failed: ' . $e->getMessage());
    }
}
