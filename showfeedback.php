<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

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

    // Verify the support token (JWT token check)
    $support_id = verify_support_jwt_token($token, $pdo);
    if (!$support_id) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    try {
        // Fetch feedback associated with the support user's calls
        $stmt = $pdo->prepare("
            SELECT f.call_id, c.name AS client_name, f.rating, f.feedback
            FROM feedback f
            JOIN calls ca ON f.call_id = ca.call_id
            JOIN users c ON f.client_id = c.id
            WHERE ca.support_id = ?
        ");
        $stmt->execute([$support_id]);
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'feedback_list' => $feedback_list]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve feedback: ' . $e->getMessage()]);
    }
}

// Function to verify the support JWT token
function verify_support_jwt_token($token, $pdo) {
    try {
        // Use the token field for verification for support staff
        $stmt = $pdo->prepare("SELECT uuid FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        return $support ? $support['id'] : false; // Return support ID if found
    } catch (PDOException $e) {
        return false; // Handle any potential database errors
    }
}
