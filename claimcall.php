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
    error_log("Incoming data: " . json_encode($data));

    // Extract call_id, support_id, and support_token from the incoming request
    $call_id = $data['call_id'] ?? '';
    $support_id = $data['support_id'] ?? '';
    $support_token = $data['support_token'] ?? '';

    // Log parsed values
    error_log("Parsed values - Call ID: $call_id, Support ID: $support_id, Support Token: $support_token");

    // Validate input
    if (empty($call_id) || empty($support_id) || empty($support_token)) {
        error_log("Validation failed - missing Call ID, Support ID, or Support Token");
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, Support ID, and Support Token are required.']);
        exit;
    }

    try {
        // Verify the support token
        if (!verify_support_token($support_id, $support_token, $pdo)) {
            error_log("Token verification failed for Support ID: $support_id");
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
            exit;
        }

        // Log that token verification passed
        error_log("Token verification passed for Support ID: $support_id");

        // Check if the call exists and is not claimed by another support
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ? AND call_status = 'waiting'");
        $stmt->execute([$call_id]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            error_log("Call not found or already claimed - Call ID: $call_id");
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Call already claimed by another support or does not exist.']);
            exit;
        }

        // Log the current call status
        error_log("Call found and is waiting - Call ID: $call_id");

        // Claim the call by updating the status and assigning support_id
        $stmt = $pdo->prepare("UPDATE calls SET support_id = ?, call_status = 'claimed', updated_at = NOW() WHERE call_id = ?");
        $stmt->execute([$support_id, $call_id]);

        // Log successful claim
        error_log("Call claimed successfully - Call ID: $call_id by Support ID: $support_id");

        // Fetch client and support names for response
        $client_stmt = $pdo->prepare("SELECT name FROM users WHERE uuid = ?");
        $client_stmt->execute([$call['client_id']]);
        $client = $client_stmt->fetch(PDO::FETCH_ASSOC);

        $support_stmt = $pdo->prepare("SELECT name FROM customer_support WHERE uuid = ?");
        $support_stmt->execute([$support_id]);
        $support = $support_stmt->fetch(PDO::FETCH_ASSOC);

        // Log the fetched client and support details
        error_log("Fetched client details: " . json_encode($client));
        error_log("Fetched support details: " . json_encode($support));

        // Initialize variables to prevent undefined variable warnings
        $client_name = isset($client['name']) ? $client['name'] : 'Unknown Client';
        $support_name = isset($support['name']) ? $support['name'] : 'Unknown Support';

        // Log variables before sending the WebSocket message
        error_log("Preparing WebSocket notification - Call ID: $call_id, Client Name: $client_name, Support Name: $support_name, Support ID: $support_id");

        // Send WebSocket notification to notify all clients
        sendWebSocketNotification($call_id, $support_id); 

        // Respond with success
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'message' => 'Call claimed by support',
            'call_details' => [
                'client_name' => $client_name,
                'support_name' => $support_name,
                'support_id' => $support_id // Include the support_id in the response
            ]
        ]);
    } catch (PDOException $e) {
        // Log the exception
        error_log("Database error: " . $e->getMessage());
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

        // Log the support details after fetching
        error_log("Support token fetched for Support ID: $support_id - " . json_encode($support));

        // If support not found or token doesn't match, return false
        if (!$support || $support['token'] !== $token) {
            error_log("Token verification failed for Support ID: $support_id");
            return false;
        }

        // If the token matches, return true
        return true;
    } catch (PDOException $e) {
        // Handle any potential database errors
        error_log('Token verification database error: ' . $e->getMessage());
        return false;
    }
}

function sendWebSocketNotification($call_id , $support_id) {
    // Log the entry into the function
    $ws_url = 'ws://84.247.187.38:8080'; // Your WebSocket server URL

    try {
        // Create a WebSocket connection
        $client = new WebSocket\Client($ws_url);

        // Log successful connection
        error_log("Connected to WebSocket server");

        $message = json_encode([
            'action' => 'claim_call',
           
        ]);

        // Log the WebSocket message before sending
        error_log("Sending WebSocket message: " . $message);

        // Send the WebSocket message
        $client->send($message);
        $client->close();

        // Log successful send
        error_log("WebSocket message sent successfully");
    } catch (Exception $e) {
        // Log the WebSocket error for debugging
        error_log('WebSocket Error: ' . $e->getMessage());
        error_log('WebSocket communication failed: ' . $e->getMessage());
    }
}
