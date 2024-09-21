<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        // Fetch all feedback data (call_id, client_name, rating, feedback)
        $stmt = $pdo->prepare("
            SELECT f.call_id, c.name AS client_name, f.rating, f.feedback
            FROM feedback f
            JOIN calls ca ON f.call_id = ca.call_id
            JOIN users c ON f.client_id = c.id
        ");
        $stmt->execute();
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'feedback_list' => $feedback_list]);
    } catch (PDOException $e) {
        // Return an error message
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve feedback: ' . $e->getMessage()]);
    }
}
