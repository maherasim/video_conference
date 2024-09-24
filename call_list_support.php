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

        // Fetch incoming calls with the status 'waiting' and handle collation mismatch between client_id and uuid
        $stmt = $pdo->prepare("SELECT c.call_id, COALESCE(u.name, 'Unknown Client') as client_name, c.call_status 
                               FROM calls c 
                               LEFT JOIN users u ON c.client_id COLLATE utf8mb4_unicode_ci = u.uuid COLLATE utf8mb4_unicode_ci
                               WHERE c.call_status = 'waiting'");
        $stmt->execute();
        $incomingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch ongoing calls where call_status is 'ongoing', 'inprogress', or 'claimed', matching client_id with uuid from users
        $stmt = $pdo->prepare("SELECT c.call_id, COALESCE(u.name, 'Unknown Client') as client_name, c.call_status 
                               FROM calls c 
                               JOIN users u ON c.client_id COLLATE utf8mb4_unicode_ci = u.uuid COLLATE utf8mb4_unicode_ci
                               WHERE c.call_status IN ('ongoing', 'inprogress', 'claimed')");
        $stmt->execute();
        $ongoingCalls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Replace 'inprogress' and 'claimed' with 'ongoing' in the response
        foreach ($ongoingCalls as &$call) {
            if ($call['call_status'] === 'inprogress' || $call['call_status'] === 'claimed') {
                $call['call_status'] = 'ongoing';
            }
        }

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

?>