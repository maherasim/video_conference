<?php
// supportsignout.php
include 'connection.php'; // Ensure this line is at the top

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $token = $data['token'] ?? '';

    // Validate token
    if (empty($token)) {
        echo json_encode(['error' => 'Token is required.']);
        exit;
    }

    try {
        // Prepare statement to find support staff by token
        $stmt = $pdo->prepare("SELECT * FROM customer_support WHERE token = ?");
        $stmt->execute([$token]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support) {
            echo json_encode(['error' => 'Invalid token or support staff not found.']);
            exit;
        }

        // Update signout timestamp and status
        $stmt = $pdo->prepare("UPDATE customer_support SET signout_timestamp = NOW(), current_status = 'offline', token = NULL WHERE token = ?");
        $stmt->execute([$token]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Signed out successfully']);
        } else {
            echo json_encode(['error' => 'Failed to sign out.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
