<?php  
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the input from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Extract parameters from the request body
    $client_uuid = $data['client_id'] ?? ''; // Expecting client_id (uuid) in the input
    $client_token = $data['client_token'] ?? ''; // Getting token from body
    $issue_description = $data['issue_description'] ?? '';
    $status = $data['status'] ?? 'pending'; // Default to 'pending' if not provided

    // Validate input
    if (empty($client_uuid) || empty($client_token) || empty($issue_description)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Client UUID, token, and issue description are required.']);
        exit;
    }

    // Verify client token
    $user_id = verify_client_uuid($client_uuid, $client_token, $pdo);
    if ($user_id === false) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    try {
        // Generate a unique ticket ID
        $ticket_id = 'ticket_' . uniqid();

        // Insert the ticket into the database with the client_uuid instead of user_id
        $stmt = $pdo->prepare("
            INSERT INTO tickets (ticket_id, client_id, status, issue_description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $client_uuid, $status, $issue_description]); // Use client_uuid here

        // Respond with success
        http_response_code(200); // Created
        echo json_encode([
            'status' => 'success',
            'ticket_id' => $ticket_id,
            'message' => 'Your ticket has been created and will be addressed shortly.'
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to create a ticket at this time. Please try again later.']);
    }
}

// Function to verify client UUID and token
function verify_client_uuid($client_uuid, $client_token, $pdo) {
    try {
        // Fetch the user ID and remember_token using uuid
        $stmt = $pdo->prepare("SELECT id, remember_token FROM users WHERE uuid = ?");
        $stmt->execute([$client_uuid]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // Compare the token from the request body with the one stored in the database
        if ($client && $client['remember_token'] === $client_token) {
            return $client['id']; // Return the user ID for any further operations if needed
        }
    } catch (PDOException $e) {
        // Handle any potential database errors
    }
    return false; // Invalid UUID or token
}
