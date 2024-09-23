<?php
include 'connection.php';  
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader)) {
        http_response_code(401);  
        echo json_encode(['status' => 'error', 'message' => 'Authorization header missing']);
        exit;
    }

    list($bearer, $support_token) = explode(' ', $authHeader);

    if (strcasecmp($bearer, 'Bearer') != 0 || empty($support_token)) {
        http_response_code(401);  
        echo json_encode(['status' => 'error', 'message' => 'Invalid Authorization header']);
        exit;
    }

    try {
        if (!verify_support_jwt_token($support_token, $pdo)) {
            http_response_code(401);  
            echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
            exit;
        }

        // Fetch only incoming calls with the status 'waiting'
        $stmt = $pdo->prepare("SELECT c.call_id, u.name as client_name, c.call_status 
                               FROM calls c 
                               LEFT JOIN users u ON c.client_id = u.id
                               WHERE c.call_status = 'waiting'");
        $stmt->execute();
        $incomingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ongoing calls logic remains the same
        $stmt = $pdo->prepare("SELECT c.call_id, u.name as client_name, c.call_status 
                               FROM calls c 
                               JOIN users u ON c.client_id = u.id");
        $stmt->execute();
        $ongoingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);  
        echo json_encode([
            'status' => 'success',
            'incoming_calls' => $incomingCalls,
            'ongoing_calls' => $ongoingCalls
        ]);
    } catch (PDOException $e) {
        http_response_code(500); 
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function verify_support_jwt_token($token, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT token FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $support ? true : false;
    } catch (PDOException $e) {
        return false;
    }
}
