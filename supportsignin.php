<?php
// supportsignin.php
include 'connection.php'; // Ensure this line is at the top

header('Content-Type: application/json'); // Ensure the response is JSON

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $email = $data['email'] ?? '';
    $password = $data['password'] ?? ''; // Get the password from the input

    // Validate email and password
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Valid email is required.']);
        exit;
    }

    if (empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Password is required.']);
        exit;
    }

    try {
        // Prepare statement to find support staff by email
        $stmt = $pdo->prepare("SELECT * FROM customer_support WHERE email = ?");
        $stmt->execute([$email]);
        $support = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$support) {
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Support staff not found.']);
            exit;
        }

        // Verify the password using password_verify (assuming the password is hashed)
        if (!password_verify($password, $support['password'])) { // Assuming 'password' field stores the hashed password
            http_response_code(401); // Unauthorized
            echo json_encode(['error' => 'Incorrect password.']);
            exit;
        }

        // Generate a token
        $token = bin2hex(random_bytes(16));

        // Update signin timestamp, status, and store the token
        $stmt = $pdo->prepare("UPDATE customer_support SET signin_timestamp = NOW(), current_status = 'online', token = ? WHERE email = ?");
        $stmt->execute([$token, $email]);

        if ($stmt->rowCount() > 0) {
            http_response_code(200); // OK
            echo json_encode([
                'token' => $token,
                'status'=>'success',
                'name' => $support['name'] ,
                'support_id' => $support['uuid'] 
            ]);
        } else {
            http_response_code(500); // Internal Server Error
            echo json_encode(['error' => 'Failed to sign in.']);
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
