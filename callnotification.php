<?php
include 'connection.php';

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);

// Check for required fields
if (!isset($data['client_id'])) {
    // Set HTTP status code to 400 Bad Request
    http_response_code(400);
    echo json_encode(['error' => 'Client ID is required.']);
    exit;
}

$client_id = $data['client_id'];
$support_id = $data['support_id'] ?? null;
$accepted = $data['accepted'] ?? false; // true or false

if ($accepted) {
    // Update the notification as accepted for the support agent
    $stmt = $pdo->prepare('UPDATE call_notifications SET accepted = 1 WHERE client_id = ? AND support_id IS NULL');
    $stmt->execute([$client_id]);

    // Assign the call to the support agent
    $stmt = $pdo->prepare('INSERT INTO call_notifications (client_id, support_id, accepted) VALUES (?, ?, 1)');
    $stmt->execute([$client_id, $support_id]);

    // Set HTTP status code to 200 OK
    http_response_code(200);
    echo json_encode(['message' => 'Call assigned and accepted.']);
} else {
    // Insert a new call notification
    $stmt = $pdo->prepare('INSERT INTO call_notifications (client_id, support_id) VALUES (?, ?)');
    $stmt->execute([$client_id, null]);

    // Set HTTP status code to 201 Created
    http_response_code(201);
    echo json_encode(['message' => 'Call notification created.']);
}
?>
