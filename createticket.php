<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the input from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Extract parameters from the request body
    $client_uuid = $data['client_uuid'] ?? ''; // Changed from client_id
    $issue_description = $data['issue_description'] ?? '';
    $status = $data['status'] ?? 'pending'; // Default to 'pending' if not provided

    // Validate input
    if (empty($client_uuid) || empty($issue_description)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Client UUID and issue description are required.']);
        exit;
    }

    try {
        // Generate a unique ticket ID
        $ticket_id = 'ticket_' . uniqid();

        // Insert the ticket into the database
        $stmt = $pdo->prepare("
            INSERT INTO tickets (ticket_id, client_uuid, status, issue_description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$ticket_id, $client_uuid, $status, $issue_description]);

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
?>
