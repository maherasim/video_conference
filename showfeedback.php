<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

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

    // Verify the support token (JWT token check)
 
        // Fetch all feedback associated with the support user's calls without type checks
        $stmt = $pdo->prepare("
            SELECT f.call_id, c.name AS client_name, f.rating, f.feedback
            FROM feedback f
            JOIN calls ca ON f.call_id = ca.call_id
            JOIN users c ON f.client_id = c.id
            WHERE ca.support_id = ?
        ");
        $stmt->execute([$support_id]);
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'feedback_list' => $feedback_list]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve feedback: ' . $e->getMessage()]);
    }
}
 
