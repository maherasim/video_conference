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
    case 'showfeedback':
        include 'showfeedback.php';
        break;
    case 'create_ticket':
        include 'createticket.php';
        break;
    case 'update_ticket':
        include 'update_ticket.php';
        break;

   case 'view_ticket':
        include 'viewticket.php';
        break;
            
        
    case 'startcall':
        include 'startcall.php';
        break;
    case 'endcall':
        include 'endcall.php';
        break;
    case 'claimcall':
            include 'claimcall.php';
            break;
    case 'call_list':
            include 'call_list_support.php';
            break;
   case 'call_support_history':
                include 'supporthistory.php';
                break;
    case 'call_cleint_history':
            include 'clienthistory.php';
            break;
    case 'recordvideocall':
        include 'recordvideocall.php';
        break;

    case 'callnotification': 
        include 'callnotification.php';
        break;

    default:
        echo json_encode(['error' => 'Invalid action.']);
        break;
}

 
?>
