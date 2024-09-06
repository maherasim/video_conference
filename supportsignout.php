<?php
// supportsignout.php
include 'connection.php'; // Ensure this line is at the top

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $email = $data['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Valid email is required.']);
        exit;
    }

    try {
        // Prepare statement to find support staff by email
        $stmt = $pdo->prepare("SELECT * FROM customer_support WHERE email = ?");
        $stmt->execute([$email]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support) {
            echo json_encode(['error' => 'Support staff not found.']);
            exit;
        }

        // Update signout timestamp and status
        $stmt = $pdo->prepare("UPDATE customer_support SET signout_timestamp = NOW(), current_status = 'offline' WHERE email = ?");
        $stmt->execute([$email]);

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
