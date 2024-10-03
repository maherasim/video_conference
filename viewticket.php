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

    // Get the ticket_id from query parameters
    $ticket_id = $_GET['ticket_id'] ?? '';

    if (empty($ticket_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Ticket ID is required']);
        exit;
    }

    // Verify the client token
    if (!verify_client_jwt_token($token, $pdo)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    try {
        // Fetch the ticket status and client's name by joining the users table
        $stmt = $pdo->prepare("
            SELECT 
                t.ticket_id, 
                t.status, 
                t.issue_description, 
                t.resolution_details, 
                t.clients_id, 
                t.created_at, 
                t.updated_at,
                u.name AS client_name
            FROM tickets t
            JOIN users u ON t.clients_id = u.uuid
            WHERE t.ticket_id = ?
        ");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ticket) {
            // Respond with success and the ticket status, including the client's name
            http_response_code(200); // OK
            echo json_encode([
                'status' => 'success',
                'ticket_status' => [
                    'ticket_id' => $ticket['ticket_id'],
                    'client_id' => $ticket['clients_id'],
                    'client_name' => $ticket['client_name'], // Fetching client's name
                    'status' => $ticket['status'],
                    'issue_description' => $ticket['issue_description'],
                    'resolution_details' => $ticket['resolution_details'],
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
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve ticket status. Please try again later.']);
    }
}

// Function to verify client JWT token
function verify_client_jwt_token($token, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client ? true : false;
    } catch (PDOException $e) {
        return false; // Handle any potential database errors
    }
}
