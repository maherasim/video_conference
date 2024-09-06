<?php
include 'connection.php'; // Database connection

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the request body as JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Extract and validate the required fields
    $client_id = $data['client_id'] ?? null;
    $support_id = $data['support_id'] ?? null;
    $rating = $data['rating'] ?? null;

    // Validate client_id, support_id, and rating
    if (empty($client_id) || empty($support_id) || empty($rating)) {
        echo json_encode(['error' => 'client_id, support_id, and rating are required.']);
        exit;
    }

    if (!is_numeric($rating) || $rating < 1 || $rating > 5) {
        echo json_encode(['error' => 'Rating must be an integer between 1 and 5.']);
        exit;
    }

    // Check if client exists
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Client not found.']);
        exit;
    }

    // Check if support staff exists
    $stmt = $pdo->prepare("SELECT id FROM customer_support WHERE id = ?");
    $stmt->execute([$support_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Support staff not found.']);
        exit;
    }

    try {
        // Insert the review into the 'reviews' table
        $stmt = $pdo->prepare("INSERT INTO reviews (client_id, support_id, rating, review_timestamp) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$client_id, $support_id, $rating]);

        // Get the newly inserted review's ID (optional if needed)
        $review_id = $pdo->lastInsertId();

        // Update the support staff's total_reviews and average_rating
        $stmt = $pdo->prepare("UPDATE customer_support SET total_reviews = total_reviews + 1 WHERE id = ?");
        $stmt->execute([$support_id]);

        // Calculate the new average rating
        $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating FROM reviews WHERE support_id = ?");
        $stmt->execute([$support_id]);
        $average_rating = $stmt->fetchColumn();

        // Update the support staff's average_rating
        $stmt = $pdo->prepare("UPDATE customer_support SET average_rating = ? WHERE id = ?");
        $stmt->execute([$average_rating, $support_id]);

        // Send success response
        echo json_encode(['message' => 'Feedback submitted successfully']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
