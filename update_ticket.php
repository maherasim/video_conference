<?php 
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $support_id = $data['support_id'] ?? '';
    $support_token = $data['support_token'] ?? '';
    $ticket_id = $data['ticket_id'] ?? '';
    $status = $data['status'] ?? '';
    $resolution_details = $data['resolution_details'] ?? '';

    // Validate input
    if (empty($support_id) || empty($support_token) || empty($ticket_id) || empty($status)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Support ID, token, ticket ID, and status are required.']);
        exit;
    }

    // Verify support token (JWT token check)
    if (!verify_support_jwt_token($support_id, $support_token, $pdo)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid support token.']);
        exit;
    }

    try {
        // Check if the ticket's status is already 'Resolved'
        $stmt = $pdo->prepare("SELECT status FROM tickets WHERE ticket_id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Ticket not found.']);
            exit;
        }

        // Check if the ticket is already resolved
        if ($ticket['status'] === 'Resolved') {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Ticket is already resolved.']);
            exit;
        }

        // Proceed to update the ticket's status
        $stmt = $pdo->prepare("
            UPDATE tickets 
            SET status = ?, issue_description = ?, updated_at = NOW() 
            WHERE ticket_id = ?
        ");
        $stmt->execute([$status, $resolution_details, $ticket_id]);

        // Check if the ticket was updated
        if ($stmt->rowCount() > 0) {
            http_response_code(200); // OK
            echo json_encode(['status' => 'success', 'message' => 'Ticket status updated successfully.']);
        } else {
            // Ticket not found or update failed
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Unable to update ticket status. Please try again later.']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to update ticket status: ' . $e->getMessage()]);
    }
}

// Function to verify the support JWT token
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


?>