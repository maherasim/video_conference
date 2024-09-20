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

    // Check if ticket_id is provided in the request
    if (!isset($_GET['ticket_id'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Ticket ID is required']);
        exit;
    }

    $ticket_id = $_GET['ticket_id'];

    try {
        // Fetch the ticket details from the database
        $stmt = $pdo->prepare("
            SELECT ticket_id, status, issue_description, created_at, updated_at
            FROM tickets
            WHERE ticket_id = ?
        ");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            // Respond with the ticket details
            http_response_code(200); // OK
            echo json_encode([
                'status' => 'success',
                'ticket_status' => [
                    'ticket_id' => $ticket['ticket_id'],
                    'status' => $ticket['status'],
                    'issue_description' => $ticket['issue_description'],
                    'created_at' => $ticket['created_at'],
                    'updated_at' => $ticket['updated_at']
                ]
            ]);
        } else {
            // Ticket not found
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve ticket status: ' . $e->getMessage()]);
    }
}

// Function to verify the support JWT token
function verify_support_jwt_token($token, $pdo) {
    try {
        // Use the token field for verification for support staff
        $stmt = $pdo->prepare("SELECT id FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        return $support ? $support['id'] : false; // Return support ID if found
    } catch (PDOException $e) {
        return false; // Handle any potential database errors
    }
}
