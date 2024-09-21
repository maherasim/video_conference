<?php
include 'connection.php'; // Ensure this line is at the top
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    try {
        // Fetch all data from the feedback table including client_id, feedback, rating, and client name from users table
        $stmt = $pdo->prepare("
            SELECT f.call_id, f.feedback, f.rating, f.client_id, u.name AS client_name
            FROM feedback f
            JOIN users u ON f.client_id = u.uuid
        ");
        $stmt->execute();
        $feedback_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Respond with success
        http_response_code(200); // OK
        echo json_encode(['status' => 'success', 'feedback_list' => $feedback_list]);
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['status' => 'error', 'message' => 'Unable to retrieve feedback: ' . $e->getMessage()]);
    }
}
