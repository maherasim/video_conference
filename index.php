<?php
include 'connection.php';

// Handle requests based on action
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'signout':
        include 'signout.php';
        break;
    
    case 'signin':
        include 'signin.php';
        break;
    
    case 'supportsignin':
        include 'supportsignin.php';
        break;
    
    case 'supportsignout':
        include 'supportsignout.php';
        break;

    case 'feedback':
        include 'feedback.php';
        break;
    
    case 'recordvideocall': // Add this case for recordvideocall
        include 'recordvideocall.php';
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
        break;
}

// Close the database connection (optional, not required for PDO)
// $pdo = null; // Uncomment if using PDO
?>
