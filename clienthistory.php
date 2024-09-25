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
    list($bearer, $client_token) = explode(' ', $authHeader);
    
    if (strcasecmp($bearer, 'Bearer') != 0 || empty($client_token)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid Authorization header']);
        exit;
    }

    try {
        // Verify the client token (JWT token check)
        if (!verify_client_jwt_token($client_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        // Fetch call history for the client (completed or canceled calls)
        $stmt = $pdo->prepare("
        SELECT c.call_id, cl.name AS client_name, c.call_status, c.call_start_time, c.call_end_time, f.rating, f.feedback
        FROM calls c
        LEFT JOIN users s ON c.client_id COLLATE utf8mb4_unicode_ci = s.uuid COLLATE utf8mb4_unicode_ci
        LEFT JOIN feedback f ON c.call_id = f.call_id
        WHERE c.client_id COLLATE utf8mb4_unicode_ci = (
            SELECT uuid FROM users WHERE remember_token = ? COLLATE utf8mb4_unicode_ci
        ) COLLATE utf8mb4_unicode_ci
    ");
    
    
        $stmt->execute([$client_token]);
        $callHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with the call history
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'call_history' => $callHistory
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Function to verify the client token from the 'clients' table
function verify_client_jwt_token($token, $pdo) {
    try {
        // Assuming client tokens are stored in the 'clients' table
        $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}
