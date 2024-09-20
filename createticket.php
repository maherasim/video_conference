<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

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

    $client_id = $data['client_id'] ?? '';
    $issue_description = $data['issue_description'] ?? '';
    $status = $data['status'] ?? 'pending'; // Default to 'pending' if not provided

    // Validate input
    if (empty($client_id) || empty($issue_description)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Client ID and issue description are required.']);
        exit;
    }

    // Verify client token
    if (!verify_client_jwt_token($client_id, $token, $pdo)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    try {
        // Generate a unique ticket ID
        $ticket_id = 'ticket_' . uniqid();

        // Insert the ticket into the database
        $stmt = $pdo->prepare("
            INSERT INTO tickets (ticket_id, client_id, status, issue_description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $client_id, $status, $issue_description]);

        // Respond with success
        http_response_code(201); // Created
        echo json_encode([
            'status' => 'success',
            'ticket_id' => $ticket_id,
            'message' => 'Thank you for staying on the line. Your ticket has been created and will be addressed shortly.'
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to create a ticket at this time. Please try again later.']);
    }
}

// Function to verify client JWT token
function verify_client_jwt_token($client_id, $token, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        return $client && $client['remember_token'] === $token;
    } catch (PDOException $e) {
        return false; // Handle any potential database errors
    }
}
