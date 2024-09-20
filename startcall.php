<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $client_id = $data['client_id'] ?? '';
    $client_token = $data['client_token'] ?? '';

    // Validate input
    if (empty($client_id) || empty($client_token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'code' => 400, 'message' => 'Client ID and token are required.']);
        exit;
    }

    try {
        // Verify the client token using the function
        if (!verify_jwt_token($client_id, $client_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'code' => 401, 'message' => 'Invalid token.']);
            exit;
        }

        // Check if client already has an ongoing or waiting call
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE client_id = ? AND call_status IN ('waiting', 'ongoing')");
        $stmt->execute([$client_id]);
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

        // Generate a unique call ID
        $call_id = 'call_' . bin2hex(random_bytes(6));

        // Insert the new call record into the database
        $stmt = $pdo->prepare("INSERT INTO calls (call_id, client_id, call_status, created_at, call_start_time) VALUES (?, ?, 'waiting', NOW(), NOW())");
        $stmt->execute([$call_id, $client_id]);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            
            'call_id' => $call_id,
            'message' => 'Waiting for support to join'
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'code' => 500, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to verify the token from the 'users' table
function verify_jwt_token($client_id, $token, $pdo) {
    try {
        // Fetch user by client_id
        $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE id = ?");
        $stmt->execute([$client_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user not found or token doesn't match, return false
        if (!$user || $user['remember_token'] !== $token) {
            return false;
        }

        // If the token matches, return true
        return true;
    } catch (PDOException $e) {
        // Handle any potential database errors
        return false;
    }
}
