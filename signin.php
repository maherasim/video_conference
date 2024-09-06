<?php
// signin.php
include 'connection.php'; // Ensure this line is at the top

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // Validate input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['error' => 'Email is required and must be valid.']);
        exit;
    }

    if (empty($password)) {
        echo json_encode(['error' => 'Password is required.']);
        exit;
    }

    try {
        // Prepare and execute the query using PDO
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Generate a token (simple example)
            $token = bin2hex(random_bytes(16));

            // Store the token in the database
            $updateStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE email = ?");
            $updateStmt->execute([$token, $email]);

            echo json_encode(['token' => $token]);
        } else {
            echo json_encode(['error' => 'The provided credentials are incorrect.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
