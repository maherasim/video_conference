<?php
// signin.php
include 'connection.php'; // Ensure this line is at the top

header('Content-Type: application/json'); // Ensure the response is JSON

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // Validate input
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Email is required and must be valid.']);
        exit;
    }

    if (empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Password is required.']);
        exit;
    }

    try {
        // Prepare and execute the query using PDO
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify the password and respond accordingly
        if ($user && password_verify($password, $user['password'])) {
            // Generate a token (simple example)
            $token = bin2hex(random_bytes(16));

            // Store the token in the database
            $updateStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE email = ?");
            $updateStmt->execute([$token, $email]);

            // Return the token and user ID in the response
            http_response_code(200); // OK
            echo json_encode([
                'token' => $token,
                'status' =>'success',
                'name' => $user['name'] ,
                'client_id' => $user['uuid'] 
            ]);
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'The provided credentials are incorrect.']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
