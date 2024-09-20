<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $call_id = $data['call_id'] ?? '';
    $client_id = $data['client_id'] ?? '';
    $support_id = $data['support_id'] ?? '';
    $rating = $data['rating'] ?? '';
    $feedback = $data['feedback'] ?? '';

    // Validate input
    if (empty($call_id) || empty($client_id) || empty($support_id) || empty($rating) || empty($feedback)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'All fields (call_id, client_id, support_id, rating, feedback) are required.']);
        exit;
    }

    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Rating must be a number between 1 and 5.']);
        exit;
    }

    try {
        // Check if the call exists and belongs to the given client and support
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ? AND client_id = ? AND support_id = ?");
        $stmt->execute([$call_id, $client_id, $support_id]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Call not found or does not match the client/support.']);
            exit;
        }

        // Insert feedback into the feedback table
        $stmt = $pdo->prepare("
            INSERT INTO feedback (call_id, client_id, support_id, rating, feedback, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$call_id, $client_id, $support_id, $rating, $feedback]);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Thank you for your feedback!']);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to submit feedback: ' . $e->getMessage()]);
    }
}
