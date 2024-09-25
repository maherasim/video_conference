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
    list($bearer, $token) = explode(' ', $authHeader);

    if (strcasecmp($bearer, 'Bearer') != 0 || empty($token)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid Authorization header']);
        exit;
    }

    try {
        // Verify the support token (JWT token check)
        if (!verify_support_jwt_token($token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        // Fetch call history for the support (completed or ongoing calls)
        $stmt = $pdo->prepare("
            SELECT c.call_id, cl.name AS client_name, c.call_status, c.call_start_time, c.call_end_time, f.rating, f.feedback
            FROM calls c
            LEFT JOIN users cl ON c.client_id = cl.uuid
            LEFT JOIN feedback f ON c.call_id = f.call_id
            WHERE c.support_id = (
                SELECT uuid FROM customer_support WHERE token = ? COLLATE utf8mb4_unicode_ci
            ) COLLATE utf8mb4_unicode_ci
        ");
        $stmt->execute([$token]);
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

// Function to verify the support token from the 'customer_support' table
function verify_support_jwt_token($token, $pdo) {
    try {
        // Use the token field for verification for support staff
        $stmt = $pdo->prepare("SELECT token FROM customer_support WHERE token = ? COLLATE utf8mb4_unicode_ci");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        return $support ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}
