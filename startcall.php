<?php 
include 'connection.php'; 
header('Content-Type: application/json');
date_default_timezone_set('America/New_York'); // Set time zone to New York
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $client_uuid = $data['client_id'] ?? ''; // Use UUID
    $client_token = $data['client_token'] ?? '';

    // Validate input
    if (empty($client_uuid) || empty($client_token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'Client UUID and token are required.']);
        exit;
    }

    try {
        // Verify the client token
        if (!verify_jwt_token($client_uuid, $client_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Invalid token.']);
            exit;
        }

        // Check if the client already has an ongoing or waiting call
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE client_id = ? AND call_status IN ('waiting', 'claimed')");
        $stmt->execute([$client_uuid]);
        $existingCall = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingCall) {
            http_response_code(409); // Conflict
            echo json_encode([
                'status' => 'error',
                'code' => 409,
                'message' => 'Client already has an ongoing or waiting call.'
            ]);
            exit;
        }

        // Check for available support agents
        $stmt = $pdo->prepare("SELECT * FROM customer_support WHERE status = 'available'");
        $stmt->execute();
        $availableSupport = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($availableSupport)) {
            // No support available
            http_response_code(503); // Service Unavailable
            echo json_encode(['status' => 'error', 'code' => 503, 'message' => 'No support available at this time.']);
            exit;
        }

        // Generate a unique call ID
        $call_id = 'call_' . bin2hex(random_bytes(6));

        // Get the current timestamp in New York timezone
        $currentDateTime = date('Y-m-d H:i:s'); 

        // Insert the new call record into the database using PHP's current date/time
        $stmt = $pdo->prepare("INSERT INTO calls (call_id, client_id, call_status, created_at, call_start_time) VALUES (?, ?, 'waiting', ?, ?)");
        $stmt->execute([$call_id, $client_uuid, $currentDateTime, $currentDateTime]);

        // Respond with success first to ensure data is sent even if WebSocket fails
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'call_id' => $call_id,
            'message' => 'Waiting for support to join'
        ]);

        // WebSocket broadcasting
        try {
            $wsMessage = json_encode(['call_id' => $call_id, 'status' => 'waiting']);

            // Ensure WebSocket server object is initialized
            if (isset($webSocketServer)) {
                $webSocketServer->broadcast($wsMessage); // Assuming WebSocket setup exists
            } else {
                error_log('WebSocket server not initialized.');
            }
        } catch (Exception $e) {
            error_log("WebSocket error: " . $e->getMessage()); // Log WebSocket errors
        }

    } catch (PDOException $e) {
        // Log the database error for troubleshooting
        error_log('Database error: ' . $e->getMessage());
        
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'code' => 500, 'message' => 'Internal Server Error, please try again later.']);
    }
}

// Function to verify the token from the 'users' table using the UUID
function verify_jwt_token($client_uuid, $token, $pdo) {
    try {
        // Fetch user by UUID
        $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE uuid = ?");
        $stmt->execute([$client_uuid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user not found or token doesn't match, return false
        if (!$user || $user['remember_token'] !== $token) {
            return false;
        }

        // If the token matches, return true
        return true;
    } catch (PDOException $e) {
        error_log('Token verification error: ' . $e->getMessage()); // Log the error
        return false;
    }
}
