<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/security.php';
require_once '../includes/functions.php';

// Start secure session
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Access denied']));
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance();
$security = new Security();

// Validate CSRF token
if (!$security->validateCSRFToken($_POST['csrf_token'], 'view_application')) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Invalid security token']));
}

// Get application ID
$app_id = intval($_POST['application_id']);

// Fetch application details
$db->prepare("
    SELECT a.*, b.bus_number as new_bus_number
    FROM applications a
    LEFT JOIN buses b ON a.new_bus_id = b.id
    WHERE a.id = :id AND a.student_id = :student_id
");
$db->bind(':id', $app_id);
$db->bind(':student_id', $user_id);
$application = $db->single();

if ($application) {
    // Format dates
    $functions = new Functions();
    $application['submitted_at'] = $functions->formatDate($application['submitted_at']);
    $application['processed_at'] = $application['processed_at'] ? $functions->formatDate($application['processed_at']) : null;
    $application['start_date'] = $application['start_date'] ? $functions->formatDate($application['start_date'], 'F j, Y') : null;
    $application['end_date'] = $application['end_date'] ? $functions->formatDate($application['end_date'], 'F j, Y') : null;
    
    // Log view
    $functions->logActivity($user_id, 'APPLICATION_VIEWED', "Viewed application ID: $app_id");
    
    header('Content-Type: application/json');
    echo json_encode($application);
} else {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Application not found']);
}
?>