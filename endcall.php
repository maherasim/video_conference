<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $call_id = $data['call_id'] ?? '';
    $user_role = $data['user_role'] ?? '';
    $user_id = $data['user_id'] ?? '';

    // Validate input
    if (empty($call_id) || empty($user_role) || empty($user_id)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, User Role, and User ID are required.']);
        exit;
    }

    try {
        // Check if the call exists
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ?");
        $stmt->execute([$call_id]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Call not found.']);
            exit;
        }

        // Check if the call status is already 'completed'
        if ($call['call_status'] === 'completed') {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'Call is already completed and cannot be ended again.']);
            exit;
        }

        // Update the call status to 'completed' and set the call_end_time
        $stmt = $pdo->prepare("
            UPDATE calls 
            SET call_status = 'completed', call_end_time = NOW() 
            WHERE call_id = ?
        ");
        $stmt->execute([$call_id]);

        // Broadcast the call ended status via WebSocket
        $wsMessage = json_encode(['call_id' => $call_id, 'status' => 'completed']);
        // Assuming you have a WebSocket server running, send this message to the connected clients
        $webSocketServer->broadcast($wsMessage); // Adjust this line based on your WebSocket setup

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Call ended']);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to end the call: ' . $e->getMessage()]);
    }
}
?>
