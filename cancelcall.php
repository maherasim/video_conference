<?php 
include 'connection.php'; 
header('Content-Type: application/json');

// Enable error reporting (only in development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $call_id = $data['call_id'] ?? '';
    $client_uuid = $data['client_id'] ?? ''; // Use UUID
    $client_token = $data['client_token'] ?? '';

    // Validate input
    if (empty($call_id) || empty($client_uuid) || empty($client_token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'Call ID, Client UUID, and token are required.']);
        exit;
    }

    try {
        // Verify the client token
        if (!verify_jwt_token($client_uuid, $client_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Invalid token.']);
            exit;
        }

        // Check if the call exists and is in 'waiting' status
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ? AND client_id = ? AND call_status = 'waiting'");
        $stmt->execute([$call_id, $client_uuid]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            http_response_code(404); // Not Found
            echo json_encode([
                'status' => 'error',
                'code' => 404,
                'message' => 'Call not found or already claimed by support.'
            ]);
            exit;
        }

        // Update the call status to 'canceled'
        $stmt = $pdo->prepare("UPDATE calls SET call_status = 'canceled', created_at = NOW() WHERE call_id = ?");
        $stmt->execute([$call_id]);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'message' => 'Call has been successfully canceled.',
            'call_id' => $call_id
        ]);

        // Optionally, you can send a WebSocket notification to inform others about the cancellation
        try {
            $wsMessage = json_encode(['call_id' => $call_id, 'status' => 'canceled']);

            // Assuming WebSocket setup exists
            if (isset($webSocketServer)) {
                $webSocketServer->broadcast($wsMessage);
            } else {
                error_log('WebSocket server not initialized.');
            }
        } catch (Exception $e) {
            error_log("WebSocket error: " . $e->getMessage());
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
        error_log('Token verification error: ' . $e->getMessage());
        return false;
    }
}
