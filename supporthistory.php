<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

// Check if the request method is GET
if ($_SERVER['REQUEST_METHOD'] == 'GET') {

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

    try {
        // Verify the support token (JWT token check)
        if (!verify_support_jwt_token($token, $pdo)) {
            http_response_code(401); // Unauthorized
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        // Prepare SQL query for fetching call history
        $stmt = $pdo->prepare("
            SELECT c.call_id, cl.name AS client_name, c.call_status, c.call_start_time, c.call_end_time, f.rating, f.feedback
            FROM calls c
            LEFT JOIN users cl ON c.client_id = cl.id
            LEFT JOIN feedback f ON c.call_id = f.call_id
            WHERE c.support_id = (SELECT id FROM customer_support WHERE token = ?)
        ");

        // Check for query preparation errors
        if (!$stmt) {
            $errorInfo = $pdo->errorInfo();
            error_log("Query preparation failed: " . json_encode($errorInfo));
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Query preparation failed.']);
            exit;
        }

        // Log the query with the token
        error_log("
            SQL Query: SELECT c.call_id, cl.name AS client_name, c.call_status, c.call_start_time, c.call_end_time, f.rating, f.feedback
            FROM calls c
            LEFT JOIN users cl ON c.client_id = cl.id
            LEFT JOIN feedback f ON c.call_id = f.call_id
            WHERE c.support_id = (SELECT id FROM customer_support WHERE token = '$token')
        ");

        // Execute the query
        $executed = $stmt->execute([$token]);

        // Check for query execution errors
        if (!$executed) {
            $errorInfo = $stmt->errorInfo();
            error_log("Query execution failed: " . json_encode($errorInfo));
            http_response_code(500); // Internal Server Error
            echo json_encode(['status' => 'error', 'message' => 'Query execution failed.']);
            exit;
        }

        // Fetch the results
        $callHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log the result
        if (empty($callHistory)) {
            error_log("No results found for the token: $token");
        } else {
            error_log("Query results: " . json_encode($callHistory));
        }

        // Respond with the call history
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'call_history' => $callHistory
        ]);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        error_log('Database Error: ' . $e->getMessage()); // Log database error
    }
}

// Function to verify the support token from the 'customer_support' table
function verify_support_jwt_token($token, $pdo) {
    try {
        // Verify token in the 'customer_support' table
        $stmt = $pdo->prepare("SELECT token FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log token verification result
        if ($support) {
            error_log("Token verified for support ID: " . json_encode($support));
            return true;
        } else {
            error_log("Token verification failed for token: $token");
            return false;
        }

    } catch (PDOException $e) {
        error_log('Token verification failed: ' . $e->getMessage());
        return false;
    }
}
