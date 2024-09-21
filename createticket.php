<?php
 include 'connection.php'; // Ensure this line is at the top
 header('Content-Type: application/json');
 
 if ($_SERVER['REQUEST_METHOD'] == 'POST') {
     // Get the input from the request body
     $input = file_get_contents('php://input');
     $data = json_decode($input, true);
 
     // Extract parameters from the request body
     $client_id = $data['client_id'] ?? '';
     $client_token = $data['client_token'] ?? ''; // Getting token from body
     $issue_description = $data['issue_description'] ?? '';
     $status = $data['status'] ?? 'pending'; // Default to 'pending' if not provided
 
     // Validate input
     if (empty($client_id) || empty($client_token) || empty($issue_description)) {
         http_response_code(400); // Bad Request
         echo json_encode(['status' => 'error', 'message' => 'Client ID, token, and issue description are required.']);
         exit;
     }
 
     // Verify client token
     if (!verify_client_jwt_token($client_id, $client_token, $pdo)) {
         http_response_code(401); // Unauthorized
         echo json_encode(['status' => 'error', 'message' => 'Invalid token']);
         exit;
     }
 
     try {
         // Generate a unique ticket ID
         $ticket_id = 'ticket_' . uniqid();
 
         // Insert the ticket into the database
         $stmt = $pdo->prepare("
             INSERT INTO tickets (ticket_id, client_id, status, issue_description)
             VALUES (?, ?, ?, ?)
         ");
         $stmt->execute([$ticket_id, $client_id, $status, $issue_description]);
 
         // Respond with success
         http_response_code(200); // Created
         echo json_encode([
             'status' => 'success',
             'ticket_id' => $ticket_id,
             'message' => 'Your ticket has been created and will be addressed shortly.'
         ]);
     } catch (PDOException $e) {
         http_response_code(500); // Internal Server Error
         echo json_encode(['status' => 'error', 'message' => 'Unable to create a ticket at this time. Please try again later.']);
     }
 }
 
 // Function to verify client JWT token (with token coming from body)
 function verify_client_jwt_token($client_id, $client_token, $pdo) {
     try {
         // Fetch the stored remember_token from the users table where id matches the client_id
         $stmt = $pdo->prepare("SELECT remember_token FROM users WHERE uuid = ?");
         $stmt->execute([$client_id]);
         $client = $stmt->fetch(PDO::FETCH_ASSOC);
 
         // Compare the token from the request body with the one stored in the database
         return $client && $client['remember_token'] === $client_token;
     } catch (PDOException $e) {
         return false; // Handle any potential database errors
     }
 }
 
