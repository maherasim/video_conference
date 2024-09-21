<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $call_id = $data['call_id'] ?? '';
    $user_role = $data['user_role'] ?? '';
    $user_id = $data['user_id'] ?? '';
    $token = $data['token'] ?? '';

    // Validate input
    if (empty($call_id) || empty($user_role) || empty($user_id) || empty($token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, User Role, User ID, and token are required.']);
        exit;
    }

    try {
        // Verify the token based on the user role (either client or support)
        if ($user_role === 'client') {
            if (!verify_jwt_token($user_id, $token, $pdo, 'users')) {
                http_response_code(401); // Unauthorized
                echo json_encode(['status' => 'error', 'message' => 'Invalid client token.']);
                exit;
            }
        } elseif ($user_role === 'support') {
            if (!verify_jwt_token($user_id, $token, $pdo, 'customer_support')) {
                http_response_code(401); // Unauthorized
                echo json_encode(['status' => 'error', 'message' => 'Invalid support token.']);
                exit;
            }
        } else {
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'Invalid user role.']);
            exit;
        }

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

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'message' => 'Call ended']);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to end the call: ' . $e->getMessage()]);
    }
}

// Function to verify the token based on user type (client or support)
function verify_jwt_token($user_id, $token, $pdo, $table) {
    try {
        // Check in the specified table for the token
        $stmt = $pdo->prepare("SELECT remember_token FROM $table WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If user is found and token matches, return true
        if ($user && $user['remember_token'] === $token) {
            return true;
        }

        // If not found or token doesn't match, return false
        return false;
    } catch (PDOException $e) {
        // Handle any potential database errors
        return false;
    }
}
?>
