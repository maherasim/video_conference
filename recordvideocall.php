<?php
include 'connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $client_id = $data['client_id'] ?? '';
    $support_id = $data['support_id'] ?? '';
    $start_timestamp = $data['start_timestamp'] ?? '';
    $end_timestamp = $data['end_timestamp'] ?? '';
    $client_review_id = $data['client_review_id'] ?? null;

    // Validate input
    if (empty($client_id) || empty($support_id) || empty($start_timestamp) || empty($end_timestamp)) {
        echo json_encode(['error' => 'All fields are required.']);
        exit;
    }

    // Validate date formats and end_timestamp > start_timestamp
    if (!strtotime($start_timestamp) || !strtotime($end_timestamp) || strtotime($end_timestamp) <= strtotime($start_timestamp)) {
        echo json_encode(['error' => 'Invalid date or end timestamp is before start timestamp.']);
        exit;
    }

    try {
        // Check if client and support exist
        $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        if ($stmt->rowCount() == 0) {
            echo json_encode(['error' => 'Client not found.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT id FROM customer_support WHERE id = ?");
        $stmt->execute([$support_id]);
        if ($stmt->rowCount() == 0) {
            echo json_encode(['error' => 'Customer support not found.']);
            exit;
        }

        if (!empty($client_review_id)) {
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE id = ?");
            $stmt->execute([$client_review_id]);
            if ($stmt->rowCount() == 0) {
                echo json_encode(['error' => 'Review not found.']);
                exit;
            }
        }

        // Calculate duration in seconds
        $duration = strtotime($end_timestamp) - strtotime($start_timestamp);

        // Insert the video call record
        $stmt = $pdo->prepare("INSERT INTO video_calls (client_id, support_id, start_timestamp, end_timestamp, duration, client_review_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $support_id, $start_timestamp, $end_timestamp, $duration, $client_review_id]);

        echo json_encode(['message' => 'Video call recorded successfully']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
