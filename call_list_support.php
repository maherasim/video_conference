<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    
    // Get the Authorization header
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
        exit;
    }

    // Extract the token from the Authorization header
    list($bearer, $support_token) = explode(' ', $authHeader);
    
    if (strcasecmp($bearer, 'Bearer') != 0 || empty($support_token)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid Authorization header']);
        exit;
    }

    try {
        // Verify the support token (JWT token check)
        if (!verify_support_jwt_token($support_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        // Fetch incoming calls (status = 'waiting')
        $stmt = $pdo->prepare("SELECT c.call_id, u.name as client_name, c.call_status 
        FROM calls c 
                LEFT JOIN users u ON c.client_id = u.id 
               ");
        $stmt->execute();
        $incomingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch ongoing calls (status = 'ongoing')
        $stmt = $pdo->prepare("SELECT c.call_id, u.name as client_name, c.call_status 
                                FROM calls c 
                                JOIN users u ON c.client_id = u.id 
                                 ");
        $stmt->execute();
        $ongoingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with the list of incoming and ongoing calls
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'incoming_calls' => $incomingCalls,
            'ongoing_calls' => $ongoingCalls
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to verify the support token from the 'support' table
function verify_support_jwt_token($token, $pdo) {
    try {
        // Here you would implement your logic to verify the JWT token.
        // For simplicity, assuming tokens are stored in the 'support' table.

        $stmt = $pdo->prepare("SELECT token FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        // If support not found or token doesn't match, return false
        return $support ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}
