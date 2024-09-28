<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

// Verify the Bearer token for support staff
function verify_support_jwt_token($support_id, $token, $pdo) {
    try {
        // Use the token field for verification for support staff
        $stmt = $pdo->prepare("SELECT id FROM customer_support WHERE uuid = ? AND token = ?");
        $stmt->execute([$support_id, $token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        return $support ? true : false; // Return true if found
    } catch (PDOException $e) {
        return false; // Handle any potential database errors
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Check for the Authorization header (Bearer token)
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    // Validate the Bearer token format
    if (strpos($authHeader, 'Bearer ') !== 0) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Authorization Bearer token required.']);
        exit;
    }

    // Extract the token from the header
    $jwt_token = str_replace('Bearer ', '', $authHeader);

    // Split the token to get support_id and token (assuming they're part of the token in some format)
    // You should modify this according to your actual token structure.
    list($support_id, $support_token) = explode('.', $jwt_token);

    // Verify the token
    if (!verify_support_jwt_token($support_id, $support_token, $pdo)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid support token.']);
        exit;
    }

    // Proceed with fetching tickets after token verification
    try {
        // Retrieve all tickets from the database
        $stmt = $pdo->prepare("SELECT * FROM tickets");
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($tickets) {
            http_response_code(200); // OK
            echo json_encode(['status' => 'success', 'data' => $tickets]);
        } else {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'No tickets found.']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve tickets: ' . $e->getMessage()]);
    }
}
?>
