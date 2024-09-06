<?php
// signout.php
include 'connection.php'; // Ensure this line is at the top

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $token = $data['token'] ?? '';

    if (empty($token)) {
        echo json_encode(['error' => 'No token provided.']);
        exit;
    }

    try {
        // Prepare statement to find user by token using PDO
        $stmt = $pdo->prepare("SELECT id FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['error' => 'No active session found.']);
            exit;
        }

        // User found, now clear the token
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['message' => 'Successfully signed out.']);
        } else {
            echo json_encode(['error' => 'Failed to sign out.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
