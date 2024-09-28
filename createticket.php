<?php 
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the input from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Extract parameters from the request body
    $client_uuid = $data['client_id'] ?? ''; // Expecting client_uuid in the input
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
    $client_uuid_verified = verify_client_uuid($client_uuid, $client_token, $pdo);
    if ($client_uuid_verified === false) {
        http_response_code(401); // Unauthorized
        echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
        exit;
    }

    // Check if any support agents are available
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS available_agents FROM customer_support WHERE status = 'available'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['available_agents'] > 0) {
            // If any agent is available, do not create the ticket
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'There are available support agents. No need to create a ticket.']);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Error checking support agent availability: ' . $e->getMessage()]);
        exit;
    }

    // If no agents are available, proceed to create the ticket
    try {
        // Generate a unique ticket ID
        $ticket_id = 'ticket_' . uniqid();

        // Insert the ticket into the database
        $stmt = $pdo->prepare("
            INSERT INTO tickets (ticket_id, clients_id, status, issue_description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $client_uuid_verified, $status, $issue_description]);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'ticket_id' => $ticket_id,
            'message' => 'Your ticket has been created and will be addressed shortly.'
        ]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'Unable to create a ticket at this time. Please try again later.',
            'error' => $e->getMessage() // Show the actual error
        ]);
    }
}

// Function to verify client UUID and token
function verify_client_uuid($client_uuid, $client_token, $pdo) {
    try {
        // Fetch the user UUID and remember_token using uuid
        $stmt = $pdo->prepare("SELECT uuid, remember_token FROM users WHERE uuid = ?");
        $stmt->execute([$client_uuid]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // Compare the token from the request body with the one stored in the database
        if ($client && $client['remember_token'] === $client_token) {
            return $client['uuid']; // Return the user UUID instead of ID
        }
    } catch (PDOException $e) {
        // Handle any potential database errors
    }
    return false; // Invalid UUID or token
}

?>