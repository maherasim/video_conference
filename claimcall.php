<?php 
require 'vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'connection.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Log incoming data
    error_log("Incoming data: " . json_encode($data)); // Log incoming data

    $call_id = $data['call_id'] ?? '';
    $support_id = $data['support_id'] ?? '';
    $support_token = $data['support_token'] ?? '';

    // Log validation results
    error_log("Parsed values - Call ID: $call_id, Support ID: $support_id, Support Token: $support_token");

    // Validate input
    if (empty($call_id) || empty($support_id) || empty($support_token)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Call ID, Support ID, and Support Token are required.']);
        exit;
    }

    try {
        // Verify the support token
        if (!verify_support_token($support_id, $support_token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token.']);
            exit;
        }

        // Check the support staff's status
        $stmt = $pdo->prepare("SELECT status FROM customer_support WHERE uuid = ?");
        $stmt->execute([$support_id]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support) {
            http_response_code(404); // Not Found
            echo json_encode(['status' => 'error', 'message' => 'Support not found.']);
            exit;
        }

        if ($support['status'] !== 'available') {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Support is already in another call.']);
            exit;
        }

        // Check if the call exists and is not claimed by another support
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE call_id = ? AND call_status = 'waiting'");
        $stmt->execute([$call_id]);
        $call = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$call) {
            http_response_code(409); // Conflict
            echo json_encode(['status' => 'error', 'message' => 'Call already claimed by another support or does not exist.']);
            exit;
        }

        // Log claiming details before executing the update
        error_log("Claiming call ID: $call_id by support ID: $support_id"); // Log claim details

        // Claim the call by updating the status and assigning support_id
        $stmt = $pdo->prepare("UPDATE calls SET support_id = ?, call_status = 'claimed', updated_at = NOW() WHERE call_id = ?");
        $stmt->execute([$support_id, $call_id]);

        // Update the customer_support status to 'ongoing'
        $stmt = $pdo->prepare("UPDATE customer_support SET status = 'ongoing' WHERE uuid = ?");
        $stmt->execute([$support_id]);

        // Fetch client and support names for response
        $client_stmt = $pdo->prepare("SELECT name FROM users WHERE uuid = ?");
        $client_stmt->execute([$call['client_id']]);
        $client = $client_stmt->fetch(PDO::FETCH_ASSOC);

        $support_stmt = $pdo->prepare("SELECT name FROM customer_support WHERE uuid = ?");
        $support_stmt->execute([$support_id]);
        $support = $support_stmt->fetch(PDO::FETCH_ASSOC);

        // Log the fetched client and support details
        error_log("Fetched client details: " . json_encode($client));
        error_log("Fetched support details: " . json_encode($support));

        // Initialize variables to prevent undefined variable warnings
        $client_name = isset($client['name']) ? $client['name'] : 'Unknown Client';
        $support_name = isset($support['name']) ? $support['name'] : 'Unknown Support';

        // Respond with success
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Call claimed successfully.',
            'call_id' => $call_id,
            'client_name' => $client_name,
            'support_name' => $support_name
        ]);

    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage()); // Log the database error
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Internal Server Error, please try again later.']);
    }
}

// Function to verify support token from the 'customer_support' table
function verify_support_token($support_id, $token, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT remember_token FROM customer_support WHERE uuid = ?");
        $stmt->execute([$support_id]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support || $support['remember_token'] !== $token) {
            return false;
        }

        return true;
    } catch (PDOException $e) {
        error_log('Token verification error: ' . $e->getMessage()); // Log the error
        return false;
    }
}
