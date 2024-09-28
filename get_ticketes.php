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

        // Fetch the tickets for authorized support staff
        $stmt = $pdo->prepare("
            SELECT ticket_id, clients_id, status, issue_description
            FROM tickets
        ");
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if tickets are found
        if (empty($tickets)) {
            http_response_code(404); // Not Found
            echo json_encode([
                'status' => 'success',
                'tickets' => [], // Return empty array if no records found
                'message' => 'No tickets found.'
            ]);
        } else {
            // Modify the result to rename 'clients_id' to 'client_id'
            $modifiedTickets = array_map(function ($ticket) {
                $ticket['client_id'] = $ticket['clients_id']; // Rename clients_id to client_id
                unset($ticket['clients_id']); // Remove the original clients_id key
                return $ticket;
            }, $tickets);

            // Respond with the modified list of tickets
            http_response_code(200); // OK
            echo json_encode([
                'status' => 'success',
                'tickets' => $modifiedTickets
            ]);
        }
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
?>
