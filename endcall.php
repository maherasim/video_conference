<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');
date_default_timezone_set('America/New_York'); // Set time zone to New York

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $call_id = $data['call_id'] ?? '';
    $user_role = $data['user_role'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $token = $data['token'] ?? ''; // Assuming token is used for verification, if needed

    // Validate input
    if (empty($call_id) || empty($user_role) || empty($user_id) || empty($token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, User Role, User ID, and Token are required.']);
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

        // Start a transaction to ensure atomicity
        $pdo->beginTransaction();

        // Get the current timestamp in New York timezone
        $currentDateTime = date('Y-m-d H:i:s'); // Current time in New York timezone

        // Update the call status to 'completed' and set the call_end_time
        $stmt = $pdo->prepare("
            UPDATE calls 
            SET call_status = 'completed', call_end_time = ? 
            WHERE call_id = ?
        ");
        $stmt->execute([$currentDateTime, $call_id]);

        // Get the support_id from the calls table
        $support_id = $call['support_id'];

        // Update the status of the support staff to 'available' in the customer_support table
        $stmt = $pdo->prepare("
            UPDATE customer_support 
            SET status = 'available' 
            WHERE uuid = ?
        ");
        $stmt->execute([$support_id]);

        // Commit the transaction
        $pdo->commit();

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Call ended']);
    } catch (PDOException $e) {
        // Roll back the transaction in case of error
        $pdo->rollBack();
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to end the call: ' . $e->getMessage()]);
    }
}
?>
